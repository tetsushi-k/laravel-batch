<?php

namespace App\Listeners;

use App\Events\DailySalesReported;
use App\Models\DailySummary;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Slack売上アラート送信リスナー
 *
 * DailySalesReported イベントを受け取り、Slack Incoming Webhook へ
 * 売上サマリーを POST する。
 *
 * SLACK_WEBHOOK_URL が未設定の場合は通知をスキップする。
 * これにより、Slack を使わない環境でもバッチ自体は正常に動作する。
 */
class SendSalesAlertToSlack
{
    public function handle(DailySalesReported $event): void
    {
        $webhookUrl = config('services.slack.webhook_url');

        if (empty($webhookUrl)) {
            Log::info('SLACK_WEBHOOK_URL が未設定のため Slack 通知をスキップします。');
            return;
        }

        $summary = $event->summary;
        $message = $this->buildMessage($summary);

        $response = Http::post($webhookUrl, ['text' => $message]);

        if ($response->failed()) {
            Log::error('Slack 通知の送信に失敗しました。', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } else {
            Log::info('Slack 通知を送信しました。', ['date' => $summary->date->toDateString()]);
        }
    }

    private function buildMessage(DailySummary $summary): string
    {
        $date        = $summary->date->format('Y年m月d日');
        $totalAmount = number_format($summary->total_amount);
        $orderCount  = number_format($summary->order_count);
        $paidCount   = number_format($summary->paid_count);
        $unpaidCount = number_format($summary->unpaid_count);

        $unpaidAlert = $summary->unpaid_count > 0
            ? $this->unpaidWarningMessage($summary, $unpaidCount)
            : "\n:white_check_mark: 未払い注文はありません。";

        return <<<TEXT
        :bar_chart: *日次売上レポート（{$date}）*
        ----------------------------
        :yen: 総売上金額：*¥{$totalAmount}*
        :clipboard: 総注文件数：{$orderCount}件（支払済み: {$paidCount}件）{$unpaidAlert}
        TEXT;
    }

    /**
     * 未払いがある場合の警告文（件数 + 合計金額）を組み立てる。
     * 金額は円・3桁区切り。未払い 0 件時は呼び出さない。
     */
    private function unpaidWarningMessage(DailySummary $summary, string $unpaidCount): string
    {
        $unpaidAmount = number_format($this->unpaidAmountFor($summary));

        return "\n:warning: 未払い注文が *{$unpaidCount}件* あります。"
            ."\n:yen: 未払い合計金額：*¥{$unpaidAmount}*";
    }

    /**
     * 対象日の未払い注文合計金額を注文テーブルから算出する。
     * daily_sales_summaries の保存項目は変更しない。
     */
    private function unpaidAmountFor(DailySummary $summary): int
    {
        $targetDate = $summary->date->toDateString();
        $start      = Carbon::createFromFormat('Y-m-d', $targetDate, 'Asia/Tokyo')->startOfDay();
        $end        = $start->copy()->endOfDay();

        return (int) Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'unpaid')
            ->sum('amount');
    }
}
