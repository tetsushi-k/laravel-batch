<?php

namespace App\Console\Commands;

use App\Models\BatchExecutionLog;
use App\Services\MonthlyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * 月次レポートバッチコマンド
 *
 * 処理の流れ:
 *   1. 冪等性チェック（同月の成功ログが存在すればスキップ）
 *   2. batch_execution_logs に status=running でログを記録
 *   3. MonthlyReportService::execute() を呼び出す
 *      - 前月分の注文を集計し monthly_reports に保存
 *      - 未払いユーザーへの SendMonthlyReminderJob をキューに積む
 *   4. status を success / failed に更新
 *
 * 冪等性:
 *   同月に2回実行しても重複処理しない。
 *   --force オプションで強制再実行が可能。
 *
 * スケジュール:
 *   毎月1日 09:00 JST に Scheduler から自動実行される。
 *   （routes/console.php 参照）
 */
class MonthlyReportCommand extends Command
{
    /**
     * コマンド名とシグネチャ
     *
     * --force: 同月の成功ログを無視して強制実行する
     */
    protected $signature = 'app:monthly-report
                            {--force : 同月の既存成功ログを無視して強制実行する}';

    protected $description = '前月分の注文データを集計し、未払いユーザーへリマインドメールを送信する';

    public function __construct(private readonly MonthlyReportService $service)
    {
        parent::__construct();
    }

    /**
     * コマンドの実行
     */
    public function handle(): int
    {
        $targetMonth = Carbon::now('Asia/Tokyo')->subMonth()->format('Y-m');

        $this->info("======================================");
        $this->info("  月次レポートバッチ 開始");
        $this->info("  対象月: {$targetMonth}");
        $this->info("======================================");

        // -----------------------------------------------------------
        // 冪等性チェック
        // 同月の成功ログが存在する場合は処理をスキップする。
        // --force オプション指定時はスキップしない。
        // -----------------------------------------------------------
        if (! $this->option('force') && BatchExecutionLog::hasSucceeded('app:monthly-report', $targetMonth)) {
            $this->warn("対象月 {$targetMonth} は既に処理済みです。");
            $this->warn("強制実行するには --force オプションを使用してください。");
            $this->info("  例: php artisan app:monthly-report --force");

            return self::SUCCESS;
        }

        // -----------------------------------------------------------
        // 実行ログ作成（running 状態で先に記録）
        // 処理中に重複実行されても running チェックで抑止できる。
        // -----------------------------------------------------------
        $log = BatchExecutionLog::create([
            'command_name'   => 'app:monthly-report',
            'execution_date' => $targetMonth,
            'status'         => 'running',
            'executed_at'    => now(),
        ]);

        try {
            $this->info('集計処理・メール送信ジョブのキューイングを実行中...');

            $result = $this->service->execute($targetMonth);

            // ステータスを success に更新
            $log->update([
                'status' => 'success',
                'memo'   => "集計完了: 総注文 {$result['total_orders']}件, "
                          . "未払いユーザー {$result['unpaid_users_count']}名へのリマインドジョブをキューに追加",
            ]);

            $this->info('');
            $this->info('【集計結果】');
            $this->table(
                ['項目', '値'],
                [
                    ['対象月',              $targetMonth],
                    ['総注文件数',          number_format($result['total_orders']).'件'],
                    ['総売上金額',          '¥'.number_format($result['total_amount'])],
                    ['支払済み注文',        number_format($result['paid_orders']).'件'],
                    ['未払い注文',          number_format($result['unpaid_orders']).'件'],
                    ['リマインド対象ユーザー', $result['unpaid_users_count'].'名'],
                ]
            );

            $this->info('');
            $this->info('月次レポートバッチが正常に完了しました。');
            $this->info('メール送信は queue:work で処理されます。');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'memo'   => $e->getMessage(),
            ]);

            $this->error('処理中にエラーが発生しました: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
