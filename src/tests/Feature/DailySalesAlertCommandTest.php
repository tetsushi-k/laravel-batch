<?php

namespace Tests\Feature;

use App\Events\DailySalesReported;
use App\Models\BatchExecutionLog;
use App\Models\DailySummary;
use App\Models\Order;
use App\Models\User;
use App\Services\DailySalesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DailySalesAlertCommandTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'https://hooks.slack.com/services/T000/B000/TEST';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00', 'Asia/Tokyo'));
        Http::fake([
            self::WEBHOOK_URL => Http::response('ok', 200),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_date_option_aggregates_only_that_day(): void
    {
        $user = $this->createUser('daily-agg@example.com');

        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');
        $this->createOrder($user, 13200, 'paid', '2026-08-16 11:30:00');
        $this->createOrder($user, 4500, 'unpaid', '2026-08-16 14:15:00');
        $this->createOrder($user, 9600, 'paid', '2026-08-16 16:45:00');
        $this->createOrder($user, 3000, 'unpaid', '2026-08-16 20:00:00');

        $this->createOrder($user, 99000, 'paid', '2026-08-15 23:00:00');
        $this->createOrder($user, 1000, 'unpaid', '2026-08-17 00:00:00');

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])->assertSuccessful();

        $this->assertDatabaseCount('daily_sales_summaries', 1);
        $summary = DailySummary::query()->first();
        $this->assertSame('2026-08-16', $summary->date->toDateString());
        $this->assertSame(38100, $summary->total_amount);
        $this->assertSame(5, $summary->order_count);
        $this->assertSame(3, $summary->paid_count);
        $this->assertSame(2, $summary->unpaid_count);
        $this->assertFalse(Schema::hasColumn('daily_sales_summaries', 'unpaid_amount'));
    }

    public function test_without_date_uses_yesterday(): void
    {
        $user = $this->createUser('yesterday@example.com');
        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');
        $this->createOrder($user, 1000, 'unpaid', '2026-08-17 09:00:00');

        $this->artisan('app:daily-sales-alert')->assertSuccessful();

        $summary = DailySummary::query()->first();
        $this->assertSame('2026-08-16', $summary->date->toDateString());
        $this->assertSame(7800, $summary->total_amount);
        $this->assertSame(1, $summary->order_count);
        $this->assertSame(1, $summary->paid_count);
        $this->assertSame(0, $summary->unpaid_count);
    }

    public function test_empty_slack_url_succeeds_without_http_and_saves_summary(): void
    {
        $user = $this->createUser('no-slack@example.com');
        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('daily_sales_summaries', 1);
        $summary = DailySummary::query()->first();
        $this->assertSame('2026-08-16', $summary->date->toDateString());
        $this->assertSame(7800, $summary->total_amount);
    }

    public function test_webhook_slack_text_contains_unpaid_amount_when_unpaid_exists(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser('slack-warning@example.com');
        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');
        $this->createOrder($user, 13200, 'paid', '2026-08-16 11:30:00');
        $this->createOrder($user, 4500, 'unpaid', '2026-08-16 14:15:00');
        $this->createOrder($user, 9600, 'paid', '2026-08-16 16:45:00');
        $this->createOrder($user, 3000, 'unpaid', '2026-08-16 20:00:00');

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';

            return $request->url() === self::WEBHOOK_URL
                && str_contains($text, '2026年08月16日')
                && str_contains($text, ':yen: 総売上金額：*¥38,100*')
                && str_contains($text, ':warning: 未払い注文が *2件* あります。')
                && str_contains($text, ':yen: 未払い合計金額：*¥7,500*');
        });
    }

    public function test_all_paid_slack_text_omits_unpaid_amount_line(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser('all-paid@example.com');
        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');
        $this->createOrder($user, 13200, 'paid', '2026-08-16 11:30:00');

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';

            return str_contains($text, ':white_check_mark: 未払い注文はありません。')
                && ! str_contains($text, '未払い合計金額')
                && ! str_contains($text, ':warning:');
        });
    }

    public function test_dry_run_shows_totals_without_saving_or_notifying(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser('dry-run@example.com');
        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');
        $this->createOrder($user, 4500, 'unpaid', '2026-08-16 14:15:00');
        $this->createOrder($user, 3000, 'unpaid', '2026-08-16 20:00:00');

        $this->artisan('app:daily-sales-alert', [
            '--date' => '2026-08-16',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('¥15,300')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('daily_sales_summaries', 0);
        $this->assertDatabaseCount('batch_execution_logs', 0);
    }

    public function test_idempotent_skip_and_force_re_sends_slack_without_duplicating_summary(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser('daily-force@example.com');
        $this->createOrder($user, 7800, 'paid', '2026-08-16 10:00:00');

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])->assertSuccessful();
        Http::assertSentCount(1);

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])
            ->expectsOutputToContain('既に処理済み')
            ->assertSuccessful();
        Http::assertSentCount(1);

        $this->artisan('app:daily-sales-alert', [
            '--date' => '2026-08-16',
            '--force' => true,
        ])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(1, DailySummary::query()->count());
    }

    public function test_failed_aggregation_records_failed_log_and_does_not_dispatch_event(): void
    {
        Event::fake([DailySalesReported::class]);

        $this->mock(DailySalesService::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new RuntimeException('集計に失敗しました'));
        });

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-16'])->assertFailed();

        $this->assertDatabaseHas('batch_execution_logs', [
            'command_name' => 'app:daily-sales-alert',
            'execution_date' => '2026-08-16',
            'status' => 'failed',
            'memo' => '集計に失敗しました',
        ]);
        Event::assertNotDispatched(DailySalesReported::class);
        Http::assertNothingSent();
        $this->assertDatabaseCount('daily_sales_summaries', 0);
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
