<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes（Scheduler 定義）
|--------------------------------------------------------------------------
|
| Laravel 11 では Kernel.php が廃止され、スケジューラーはここで定義する。
| Schedule::command() の timezone() は config/app.php の timezone を
| 上書きする形で JST を明示的に指定している。
|
*/

/**
 * 月次レポートバッチ
 *
 * 毎月1日 09:00 JST に自動実行する。
 * withoutOverlapping(): 前回の処理が完了していない場合は実行をスキップ。
 * appendOutputTo(): 実行結果をログファイルに追記。
 */
Schedule::command('app:monthly-report')
    ->monthlyOn(1, '09:00')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/batch-monthly-report.log'));
