<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 月次リマインドメール
 *
 * 未払い注文があるユーザーに送信するリマインドメール。
 * SendMonthlyReminderJob から Mail::to()->send() で使用される。
 *
 * Blade テンプレート: resources/views/emails/monthly_reminder.blade.php
 *
 * ローカル確認方法:
 *   .env に Mailtrap の SMTP 設定を記入し、
 *   `php artisan queue:work` を起動後にバッチを実行する。
 */
class MonthlyReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $targetMonth,
        public readonly Collection $unpaidOrders,
    ) {}

    /**
     * メールの件名・送信者などのメタ情報を定義する。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[お支払いリマインド] {$this->targetMonth} 分の未払いご注文があります",
        );
    }

    /**
     * メール本文の Blade テンプレートを指定する。
     * $user, $targetMonth は自動的にビューに渡される（public プロパティ）。
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.monthly_reminder',
        );
    }
}
