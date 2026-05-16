<?php

namespace App\Services;

use App\Jobs\SendMonthlyReminderJob;
use App\Models\MonthlyReport;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 月次レポートサービス
 *
 * バッチ処理のビジネスロジックをコマンドから分離するためのサービスクラス。
 * MonthlyReportCommand は薄いラッパーとして振る舞い、
 * 集計・保存・ジョブディスパッチはすべてここで行う。
 *
 * 設計上の工夫:
 * - 集計クエリは単一の selectRaw で集約し、N+1 問題を回避
 * - 未払いユーザー取得は with(['orders' => ...]) で Eager Loading
 * - DB::transaction() で集計保存の原子性を保証
 * - updateOrCreate で再実行時の二重登録を防止
 */
class MonthlyReportService
{
    /**
     * 月次レポート処理を実行する。
     *
     * @param  string $targetMonth 処理対象月 (YYYY-MM)
     * @return array{
     *   total_orders: int,
     *   total_amount: int,
     *   paid_orders: int,
     *   unpaid_orders: int,
     *   unpaid_users_count: int
     * }
     */
    public function execute(string $targetMonth): array
    {
        [$startDate, $endDate] = $this->getMonthRange($targetMonth);

        // トランザクション内で集計・保存を行う
        $report = DB::transaction(function () use ($targetMonth, $startDate, $endDate) {
            return $this->aggregateAndSave($targetMonth, $startDate, $endDate);
        });

        // キュージョブのディスパッチはトランザクション外で行う
        // （トランザクションのロールバック時にジョブが残ることを防ぐため）
        $unpaidUsersCount = $this->dispatchReminderJobs($targetMonth, $startDate, $endDate);

        return [
            'total_orders'       => $report->total_orders,
            'total_amount'       => $report->total_amount,
            'paid_orders'        => $report->paid_orders,
            'unpaid_orders'      => $report->unpaid_orders,
            'unpaid_users_count' => $unpaidUsersCount,
        ];
    }

    /**
     * 前月の注文を集計し monthly_reports テーブルに保存する。
     *
     * updateOrCreate を使うことで --force オプション時の再実行も安全。
     */
    private function aggregateAndSave(
        string $targetMonth,
        Carbon $startDate,
        Carbon $endDate
    ): MonthlyReport {
        // 単一クエリで全集計を取得（N+1 回避）
        $stats = Order::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(amount), 0) as total_amount,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status = "unpaid" THEN 1 ELSE 0 END) as unpaid_orders
            ')
            ->first();

        return MonthlyReport::updateOrCreate(
            ['target_month' => $targetMonth],
            [
                'total_orders'   => (int) ($stats->total_orders ?? 0),
                'total_amount'   => (int) ($stats->total_amount ?? 0),
                'paid_orders'    => (int) ($stats->paid_orders ?? 0),
                'unpaid_orders'  => (int) ($stats->unpaid_orders ?? 0),
            ]
        );
    }

    /**
     * 前月に未払い注文があるユーザーに対してリマインドメール送信ジョブをディスパッチする。
     *
     * Eager Loading（with）を使うことで N+1 問題を回避。
     * ループ内で各ユーザーの未払い注文リストを追加クエリなしで参照できる。
     *
     * @return int ディスパッチしたジョブ数（＝対象ユーザー数）
     */
    private function dispatchReminderJobs(
        string $targetMonth,
        Carbon $startDate,
        Carbon $endDate
    ): int {
        // whereHas で未払い注文を持つユーザーを絞り込み、
        // with でその未払い注文を一括取得（N+1 回避）
        $unpaidUsers = User::whereHas('orders', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'unpaid');
        })
        ->with(['orders' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'unpaid')
                ->orderBy('created_at');
        }])
        ->get();

        foreach ($unpaidUsers as $user) {
            SendMonthlyReminderJob::dispatch($user, $targetMonth);
        }

        return $unpaidUsers->count();
    }

    /**
     * 対象月の開始日時・終了日時（JST）を返す。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getMonthRange(string $targetMonth): array
    {
        $start = Carbon::createFromFormat('Y-m', $targetMonth, 'Asia/Tokyo')
            ->startOfMonth()
            ->startOfDay();

        $end = $start->copy()->endOfMonth()->endOfDay();

        return [$start, $end];
    }
}
