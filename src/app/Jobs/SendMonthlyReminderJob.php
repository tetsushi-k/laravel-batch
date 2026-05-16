<?php

namespace App\Jobs;

use App\Mail\MonthlyReminderMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 月次リマインドメール送信ジョブ
 *
 * MonthlyReportService からディスパッチされ、database キューで非同期処理される。
 * `php artisan queue:work` を起動しておくことで処理が実行される。
 *
 * リトライ設定:
 * - $tries = 3: 最大3回リトライ
 * - backoff(): 指数バックオフ（60秒, 120秒, 240秒）
 *   一時的なSMTPエラー時に間隔を空けて再送することで送信成功率を高める
 *
 * SerializesModels:
 * - User モデルをシリアライズし、ジョブ実行時に再取得する
 *   これにより、ジョブがキューに積まれた後にユーザー情報が変更されても
 *   最新の情報でメールを送信できる
 */
class SendMonthlyReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大リトライ回数
     */
    public int $tries = 3;

    /**
     * タイムアウト（秒）
     */
    public int $timeout = 30;

    public function __construct(
        public readonly User $user,
        public readonly string $targetMonth,
    ) {}

    /**
     * バックオフ設定（指数バックオフ）
     *
     * 1回目の失敗後: 60秒待機
     * 2回目の失敗後: 120秒待機
     * 3回目の失敗後: 240秒待機
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 120, 240];
    }

    /**
     * ジョブを処理する。
     *
     * SerializesModels により User はキューから取り出す際に再 fetch される。
     * その際 Eager Loading の制約が失われるため、ここで改めて絞り込んで取得する。
     */
    public function handle(): void
    {
        $start = Carbon::createFromFormat('Y-m', $this->targetMonth, 'Asia/Tokyo')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $unpaidOrders = $this->user->orders()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'unpaid')
            ->orderBy('created_at')
            ->get();

        Mail::to($this->user->email)
            ->send(new MonthlyReminderMail($this->user, $this->targetMonth, $unpaidOrders));

        Log::info("月次リマインドメール送信完了", [
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'target_month' => $this->targetMonth,
        ]);
    }

    /**
     * 全リトライが失敗した際の処理。
     * failed_jobs テーブルへの記録に加え、ログにも詳細を残す。
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("月次リマインドメール送信失敗（全リトライ消費）", [
            'user_id'      => $this->user->id,
            'email'        => $this->user->email,
            'target_month' => $this->targetMonth,
            'error'        => $exception->getMessage(),
        ]);
    }
}
