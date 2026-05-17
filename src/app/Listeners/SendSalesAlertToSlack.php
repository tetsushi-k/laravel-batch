<?php

namespace App\Listeners;

use App\Events\DailySalesReported;
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

    private function buildMessage(mixed $summary): string
    {
        $date        = $summary->date->format('Y年m月d日');
        $totalAmount = number_format($summary->total_amount);
        $orderCount  = number_format($summary->order_count);
        $paidCount   = number_format($summary->paid_count);
        $unpaidCount = number_format($summary->unpaid_count);

        $unpaidAlert = $summary->unpaid_count > 0
            ? "\n:warning: 未払い注文が *{$unpaidCount}件* あります。"
            : "\n:white_check_mark: 未払い注文はありません。";

        return <<<TEXT
        :bar_chart: *日次売上レポート（{$date}）*
        ----------------------------
        :yen: 総売上金額：*¥{$totalAmount}*
        :clipboard: 総注文件数：{$orderCount}件（支払済み: {$paidCount}件）{$unpaidAlert}
        TEXT;
    }
}
