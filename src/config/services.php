<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    |
    | Incoming Webhook URL を設定する。
    | https://api.slack.com/messaging/webhooks から取得できる。
    | 未設定の場合、SendSalesAlertToSlack リスナーは通知をスキップする。
    |
    */
    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

];
