<?php

namespace App\Events;

use App\Models\DailySummary;

/**
 * 日次売上集計完了イベント
 *
 * DailySalesService が集計・保存を完了した後に発火する。
 * このイベントを受け取った Listener（SendSalesAlertToSlack）が
 * Slack への通知を担当することで、集計ロジックと通知ロジックを分離している。
 */
class DailySalesReported
{
    public function __construct(
        public readonly DailySummary $summary,
    ) {}
}
