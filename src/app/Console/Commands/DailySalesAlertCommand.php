<?php

namespace App\Console\Commands;

use App\Models\BatchExecutionLog;
use App\Services\DailySalesService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * 日次売上アラートバッチコマンド
 *
 * 処理の流れ:
 *   1. 冪等性チェック（同日の成功ログが存在すればスキップ）
 *   2. batch_execution_logs に status=running でログを記録
 *   3. DailySalesService::execute() を呼び出す
 *      - 前日の注文を集計し daily_sales_summaries に保存
 *      - DailySalesReported イベントを発火（Slack通知は SendSalesAlertToSlack リスナーが担当）
 *   4. status を success / failed に更新
 *
 * 冪等性:
 *   同日に2回実行しても重複処理しない。
 *   --force オプションで強制再実行が可能。
 *
 * スケジュール:
 *   毎朝 09:00 JST に Scheduler から自動実行される。
 *   （routes/console.php 参照）
 */
class DailySalesAlertCommand extends Command
{
    /**
     * コマンド名とシグネチャ
     *
     * --date:  処理対象日を指定する（デフォルト: 昨日）。YYYY-MM-DD 形式。
     * --force: 同日の既存成功ログを無視して強制実行する。
     */
    protected $signature = 'app:daily-sales-alert
                            {--date=    : 処理対象日 (YYYY-MM-DD)。省略時は前日}
                            {--force    : 同日の既存成功ログを無視して強制実行する}
                            {--dry-run  : DB保存・Slack通知を行わず集計結果のみ表示する}';

    protected $description = '前日の売上データを集計し、Slack へアラートを送信する';

    public function __construct(private readonly DailySalesService $service)
    {
        parent::__construct();
    }

    /**
     * コマンドの実行
     */
    public function handle(): int
    {
        $targetDate = $this->option('date')
            ?? Carbon::yesterday('Asia/Tokyo')->toDateString();

        $this->info("======================================");
        $this->info("  日次売上アラートバッチ 開始");
        $this->info("  対象日: {$targetDate}");
        $this->info("======================================");

        // -----------------------------------------------------------
        // ドライラン
        // DB への保存も Slack 通知も行わず、集計結果のみを表示する。
        // 副作用がないため、環境検証や動作確認に安全に利用できる。
        // -----------------------------------------------------------
        if ($this->option('dry-run')) {
            $this->warn('【DRY RUN】DB保存・Slack通知は行いません。');

            $result = $this->service->preview($targetDate);

            $this->renderResultTable($targetDate, $result);

            $this->info('');
            $this->info('ドライランが正常に完了しました。');

            return self::SUCCESS;
        }

        // -----------------------------------------------------------
        // 冪等性チェック
        // 同日の成功ログが存在する場合は処理をスキップする。
        // --force オプション指定時はスキップしない。
        // -----------------------------------------------------------
        if (! $this->option('force') && BatchExecutionLog::hasSucceeded('app:daily-sales-alert', $targetDate)) {
            $this->warn("対象日 {$targetDate} は既に処理済みです。");
            $this->warn("強制実行するには --force オプションを使用してください。");
            $this->info("  例: php artisan app:daily-sales-alert --force");

            return self::SUCCESS;
        }

        // -----------------------------------------------------------
        // 実行ログ作成（running 状態で先に記録）
        // 処理中に重複実行されても running チェックで抑止できる。
        // -----------------------------------------------------------
        $log = BatchExecutionLog::create([
            'command_name'   => 'app:daily-sales-alert',
            'execution_date' => $targetDate,
            'status'         => 'running',
            'executed_at'    => now(),
        ]);

        try {
            $this->info('売上集計・Slack通知イベントの発火を実行中...');

            $result = $this->service->execute($targetDate);

            $log->update([
                'status' => 'success',
                'memo'   => "集計完了: 総売上 ¥{$result['total_amount']}, "
                          . "注文数 {$result['order_count']}件 "
                          . "（支払済: {$result['paid_count']}件, 未払: {$result['unpaid_count']}件）",
            ]);

            $this->renderResultTable($targetDate, $result);

            $this->info('');
            $this->info('日次売上アラートバッチが正常に完了しました。');
            $this->info('Slack通知は SLACK_WEBHOOK_URL が設定されている場合のみ送信されます。');

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

    /**
     * 集計結果をテーブル形式で表示する。
     *
     * @param  array{total_amount:int, order_count:int, paid_count:int, unpaid_count:int} $result
     */
    private function renderResultTable(string $targetDate, array $result): void
    {
        $this->info('');
        $this->info('【集計結果】');
        $this->table(
            ['項目', '値'],
            [
                ['対象日',        $targetDate],
                ['総売上金額',    '¥'.number_format($result['total_amount'])],
                ['総注文件数',    number_format($result['order_count']).'件'],
                ['支払済み件数',  number_format($result['paid_count']).'件'],
                ['未払い件数',    number_format($result['unpaid_count']).'件'],
            ]
        );
    }
}
