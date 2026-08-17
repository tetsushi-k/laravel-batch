<?php

namespace Tests\Feature;

use App\Jobs\SendMonthlyReminderJob;
use App\Mail\MonthlyReminderMail;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMonthlyReminderJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Tokyo'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_handle_sends_mail_with_only_july_unpaid_orders(): void
    {
        $user = $this->createUser('reminder@example.com');

        $this->createOrder($user, 5500, 'unpaid', '2026-07-18 12:00:00');
        $this->createOrder($user, 12000, 'unpaid', '2026-07-25 13:00:00');
        $this->createOrder($user, 15000, 'paid', '2026-07-05 10:00:00');
        $this->createOrder($user, 6800, 'unpaid', '2026-08-03 10:00:00');

        (new SendMonthlyReminderJob($user, '2026-07'))->handle();

        Mail::assertSent(MonthlyReminderMail::class, function (MonthlyReminderMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && $mail->targetMonth === '2026-07'
                && $mail->unpaidOrders->count() === 2
                && $mail->unpaidOrders->sum('amount') === 17500
                && $mail->unpaidOrders->every(fn (Order $order) => $order->status === 'unpaid'
                    && $order->created_at->year === 2026
                    && $order->created_at->month === 7);
        });
    }

    public function test_handle_skips_mail_when_unpaid_orders_become_paid(): void
    {
        $user = $this->createUser('already-paid@example.com');
        $order = $this->createOrder($user, 5500, 'unpaid', '2026-07-18 12:00:00');

        $job = new SendMonthlyReminderJob($user, '2026-07');
        $order->update(['status' => 'paid']);
        $job->handle();

        Mail::assertNothingSent();
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'name' => 'テストユーザー',
            'email' => $email,
            'password' => 'password',
        ]);
    }

    private function createOrder(User $user, int $amount, string $status, string $createdAt): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => $status,
            'created_at' => Carbon::parse($createdAt, 'Asia/Tokyo'),
        ]);
    }
}
