<?php

namespace App\Services;

use App\Events\DailySalesReported;
use App\Models\DailySummary;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 日次売上サービス
 *
 * 指定日の注文データを集計・保存し、DailySalesReported イベントを発火する。
 * イベントを受け取った SendSalesAlertToSlack リスナーが Slack 通知を担当する。
 *
 * 設計上の工夫:
 * - 月次バッチと同様に単一の selectRaw で集約し N+1 を回避
 * - updateOrCreate で再実行時の二重保存を防止（--force 時も安全）
 * - Slack 通知ロジックはイベント/リスナーに委譲し、集計と通知の責務を分離
 * - DB::transaction() で集計保存の原子性を保証し、コミット後にイベントを発火
 */
class DailySalesService
{
    /**
     * 日次売上集計処理を実行する。
     *
     * @param  string $targetDate 処理対象日 (YYYY-MM-DD)
     * @return array{
     *   total_amount: int,
     *   order_count: int,
     *   paid_count: int,
     *   unpaid_count: int
     * }
     */
    public function execute(string $targetDate): array
    {
        [$startOfDay, $endOfDay] = $this->getDayRange($targetDate);

        $summary = DB::transaction(function () use ($targetDate, $startOfDay, $endOfDay) {
            return $this->aggregateAndSave($targetDate, $startOfDay, $endOfDay);
        });

        // トランザクションのコミット後にイベントを発火する。
        // ロールバック時に Slack 通知だけが飛ぶ事故を防ぐため、意図的にトランザクション外に置く。
        event(new DailySalesReported($summary));

        return [
            'total_amount' => $summary->total_amount,
            'order_count'  => $summary->order_count,
            'paid_count'   => $summary->paid_count,
            'unpaid_count' => $summary->unpaid_count,
        ];
    }

    /**
     * 集計結果のプレビューを返す（ドライラン用）。
     *
     * DB への保存もイベント発火も行わず、対象日の集計値のみを算出する。
     * 副作用がないため、動作確認や環境検証で安全に実行できる。
     *
     * @param  string $targetDate 処理対象日 (YYYY-MM-DD)
     * @return array{
     *   total_amount: int,
     *   order_count: int,
     *   paid_count: int,
     *   unpaid_count: int
     * }
     */
    public function preview(string $targetDate): array
    {
        [$startOfDay, $endOfDay] = $this->getDayRange($targetDate);

        return $this->aggregateStats($startOfDay, $endOfDay);
    }

    /**
     * 指定日の注文を集計し daily_sales_summaries テーブルに保存する。
     *
     * updateOrCreate を使うことで --force オプション時の再実行も安全。
     */
    private function aggregateAndSave(
        string $targetDate,
        Carbon $startOfDay,
        Carbon $endOfDay
    ): DailySummary {
        return DailySummary::updateOrCreate(
            ['date' => Carbon::parse($targetDate)->startOfDay()],
            $this->aggregateStats($startOfDay, $endOfDay)
        );
    }

    /**
     * 指定期間の注文を単一クエリで集計し、集計値の配列を返す。
     *
     * 保存やイベント発火を含まない純粋な集計処理。execute() と preview() の両方から利用する。
     *
     * @return array{
     *   total_amount: int,
     *   order_count: int,
     *   paid_count: int,
     *   unpaid_count: int
     * }
     */
    private function aggregateStats(Carbon $startOfDay, Carbon $endOfDay): array
    {
        $stats = Order::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw("
                COUNT(*) as order_count,
                COALESCE(SUM(amount), 0) as total_amount,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count
            ")
            ->first();

        return [
            'total_amount' => (int) ($stats->total_amount ?? 0),
            'order_count'  => (int) ($stats->order_count ?? 0),
            'paid_count'   => (int) ($stats->paid_count ?? 0),
            'unpaid_count' => (int) ($stats->unpaid_count ?? 0),
        ];
    }

    /**
     * 対象日の開始・終了日時（JST）を返す。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDayRange(string $targetDate): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $targetDate, 'Asia/Tokyo')->startOfDay();
        $end   = $start->copy()->endOfDay();

        return [$start, $end];
    }
}
