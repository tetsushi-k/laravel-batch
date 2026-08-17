<?php

namespace Tests\Feature;

use App\Jobs\SendMonthlyReminderJob;
use App\Models\BatchExecutionLog;
use App\Models\MonthlyReport;
use App\Models\Order;
use App\Models\User;
use App\Services\MonthlyReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class MonthlyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Tokyo'));
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_aggregates_only_previous_month_orders(): void
    {
        $user = $this->createUser('monthly-agg@example.com');

        $this->createOrder($user, 15000, 'paid', '2026-07-05 10:00:00');
        $this->createOrder($user, 8500, 'paid', '2026-07-12 11:00:00');
        $this->createOrder($user, 5500, 'unpaid', '2026-07-18 12:00:00');
        $this->createOrder($user, 12000, 'unpaid', '2026-07-25 13:00:00');

        $this->createOrder($user, 99999, 'paid', '2026-06-30 23:00:00');
        $this->createOrder($user, 88888, 'unpaid', '2026-08-01 00:00:00');

        $this->artisan('app:monthly-report')->assertSuccessful();

        $this->assertDatabaseCount('monthly_reports', 1);
        $this->assertDatabaseHas('monthly_reports', [
            'target_month' => '2026-07',
            'total_orders' => 4,
            'total_amount' => 41000,
            'paid_orders' => 2,
            'unpaid_orders' => 2,
        ]);
        $this->assertDatabaseHas('batch_execution_logs', [
            'command_name' => 'app:monthly-report',
            'execution_date' => '2026-07',
            'status' => 'success',
        ]);
    }

    public function test_dispatches_reminder_jobs_only_for_users_with_july_unpaid(): void
    {
        $julyUnpaid = $this->createUser('july-unpaid@example.com');
        $julyUnpaidAndAugust = $this->createUser('july-and-august@example.com');
        $paidOnly = $this->createUser('paid-only@example.com');
        $augustUnpaidOnly = $this->createUser('august-unpaid@example.com');

        $this->createOrder($julyUnpaid, 5500, 'unpaid', '2026-07-18 12:00:00');

        $this->createOrder($julyUnpaidAndAugust, 12000, 'unpaid', '2026-07-25 13:00:00');
        $this->createOrder($julyUnpaidAndAugust, 6800, 'unpaid', '2026-08-03 10:00:00');

        $this->createOrder($paidOnly, 15000, 'paid', '2026-07-05 10:00:00');
        $this->createOrder($paidOnly, 8500, 'paid', '2026-07-12 11:00:00');

        $this->createOrder($augustUnpaidOnly, 4500, 'unpaid', '2026-08-07 09:00:00');

        $this->artisan('app:monthly-report')->assertSuccessful();

        Queue::assertPushed(SendMonthlyReminderJob::class, 2);
        Queue::assertPushed(SendMonthlyReminderJob::class, function (SendMonthlyReminderJob $job) use ($julyUnpaid) {
            return $job->user->is($julyUnpaid) && $job->targetMonth === '2026-07';
        });
        Queue::assertPushed(SendMonthlyReminderJob::class, function (SendMonthlyReminderJob $job) use ($julyUnpaidAndAugust) {
            return $job->user->is($julyUnpaidAndAugust) && $job->targetMonth === '2026-07';
        });
        Queue::assertNotPushed(SendMonthlyReminderJob::class, function (SendMonthlyReminderJob $job) use ($paidOnly) {
            return $job->user->is($paidOnly);
        });
        Queue::assertNotPushed(SendMonthlyReminderJob::class, function (SendMonthlyReminderJob $job) use ($augustUnpaidOnly) {
            return $job->user->is($augustUnpaidOnly);
        });
    }

    public function test_second_run_skips_and_force_re_runs_without_duplicating_report(): void
    {
        $user = $this->createUser('idempotent@example.com');
        $this->createOrder($user, 15000, 'paid', '2026-07-05 10:00:00');
        $this->createOrder($user, 5500, 'unpaid', '2026-07-18 12:00:00');

        $this->artisan('app:monthly-report')->assertSuccessful();

        $this->artisan('app:monthly-report')
            ->expectsOutputToContain('既に処理済み')
            ->assertSuccessful();

        $this->artisan('app:monthly-report', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, MonthlyReport::query()->count());
        $this->assertSame(2, BatchExecutionLog::query()->where('status', 'success')->count());
    }

    public function test_failed_aggregation_records_failed_log_and_does_not_dispatch(): void
    {
        $this->mock(MonthlyReportService::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new RuntimeException('集計に失敗しました'));
        });

        $this->artisan('app:monthly-report')->assertFailed();

        $this->assertDatabaseCount('monthly_reports', 0);
        $this->assertDatabaseHas('batch_execution_logs', [
            'command_name' => 'app:monthly-report',
            'execution_date' => '2026-07',
            'status' => 'failed',
            'memo' => '集計に失敗しました',
        ]);
        Queue::assertNothingPushed();
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
