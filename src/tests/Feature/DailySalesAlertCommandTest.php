<?php

namespace Tests\Feature;

use App\Models\BatchExecutionLog;
use App\Models\DailySummary;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 日次売上アラートバッチの Feature テスト
 *
 * Slack 本文の未払い合計金額表示と、既存の集計・保存・冪等性・オプションを検証する。
 */
class DailySalesAlertCommandTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'https://hooks.slack.com/services/T000/B000/TEST';

    private const TARGET_DATE = '2026-08-16';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            self::WEBHOOK_URL => Http::response('ok', 200),
        ]);
    }

    public function test_未払いがあるときSlack本文に合計金額が3桁区切りで出る(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser();
        $this->createOrder($user, 7_800, 'paid', '10:00');
        $this->createOrder($user, 13_200, 'paid', '11:30');
        $this->createOrder($user, 4_500, 'unpaid', '14:15');
        $this->createOrder($user, 9_600, 'paid', '16:45');
        $this->createOrder($user, 3_000, 'unpaid', '20:00');

        $this->artisan('app:daily-sales-alert', ['--date' => self::TARGET_DATE])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request['text'];

            return $request->url() === self::WEBHOOK_URL
                && str_contains($text, ':warning: 未払い注文が *2件* あります。')
                && str_contains($text, ':yen: 未払い合計金額：*¥7,500*')
                && str_contains($text, ':yen: 総売上金額：*¥38,100*');
        });
    }

    public function test_未払いが0件のときは金額行を出さず未払いなしメッセージを維持する(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser();
        $this->createOrder($user, 12_000, 'paid', '10:00');
        $this->createOrder($user, 8_000, 'paid', '15:00');

        $this->artisan('app:daily-sales-alert', ['--date' => self::TARGET_DATE])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request['text'];

            return $request->url() === self::WEBHOOK_URL
                && str_contains($text, ':white_check_mark: 未払い注文はありません。')
                && ! str_contains($text, '未払い合計金額')
                && ! str_contains($text, ':warning:');
        });
    }

    public function test_Slack_Webhook未設定でもバッチは成功終了し通知しない(): void
    {
        config(['services.slack.webhook_url' => '']);

        $user = $this->createUser();
        $this->createOrder($user, 5_000, 'unpaid', '12:00');

        $this->artisan('app:daily-sales-alert', ['--date' => self::TARGET_DATE])
            ->assertSuccessful();

        Http::assertNothingSent();

        $summary = DailySummary::whereDate('date', self::TARGET_DATE)->first();
        $this->assertNotNull($summary);
        $this->assertSame(1, $summary->unpaid_count);
        $this->assertSame(5000, $summary->total_amount);
    }

    public function test_集計結果の保存内容は既存カラムのままである(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser();
        $this->createOrder($user, 7_800, 'paid', '10:00');
        $this->createOrder($user, 4_500, 'unpaid', '14:15');
        $this->createOrder($user, 3_000, 'unpaid', '20:00');

        $this->artisan('app:daily-sales-alert', ['--date' => self::TARGET_DATE])
            ->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('daily_sales_summaries', 'unpaid_amount'));

        $summary = DailySummary::whereDate('date', self::TARGET_DATE)->first();
        $this->assertNotNull($summary);
        $this->assertSame(15300, $summary->total_amount);
        $this->assertSame(3, $summary->order_count);
        $this->assertSame(1, $summary->paid_count);
        $this->assertSame(2, $summary->unpaid_count);
    }

    public function test_同日の再実行は冪等にスキップしforceで再実行できる(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser();
        $this->createOrder($user, 4_500, 'unpaid', '14:15');

        $this->artisan('app:daily-sales-alert', ['--date' => self::TARGET_DATE])
            ->assertSuccessful();

        $this->artisan('app:daily-sales-alert', ['--date' => self::TARGET_DATE])
            ->expectsOutputToContain('既に処理済み')
            ->assertSuccessful();

        Http::assertSentCount(1);

        BatchExecutionLog::create([
            'command_name'   => 'app:daily-sales-alert',
            'execution_date' => '2026-08-10',
            'status'         => 'success',
            'executed_at'    => now(),
        ]);

        $this->artisan('app:daily-sales-alert', [
            '--date'  => '2026-08-10',
            '--force' => true,
        ])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertTrue(
            BatchExecutionLog::hasSucceeded('app:daily-sales-alert', '2026-08-10')
        );
        $this->assertNotNull(DailySummary::whereDate('date', '2026-08-10')->first());
    }

    public function test_dateオプションは指定日だけを集計する(): void
    {
        config(['services.slack.webhook_url' => self::WEBHOOK_URL]);

        $user = $this->createUser();
        $this->createOrder($user, 4_500, 'unpaid', '14:15', self::TARGET_DATE);
        $this->createOrder($user, 99_000, 'unpaid', '10:00', '2026-08-10');

        $this->artisan('app:daily-sales-alert', ['--date' => '2026-08-10'])
            ->assertSuccessful();

        $summary = DailySummary::whereDate('date', '2026-08-10')->first();
        $this->assertNotNull($summary);
        $this->assertSame(99000, $summary->total_amount);
        $this->assertSame(1, $summary->unpaid_count);
        $this->assertNull(DailySummary::whereDate('date', self::TARGET_DATE)->first());

        Http::assertSent(function ($request) {
            $text = $request['text'];

            return str_contains($text, '2026年08月10日')
                && str_contains($text, ':yen: 未払い合計金額：*¥99,000*')
                && ! str_contains($text, '¥4,500');
        });
    }

    private function createUser(): User
    {
        return User::create([
            'name'     => 'テストユーザー',
            'email'    => 'test@example.com',
            'password' => 'password',
        ]);
    }

    private function createOrder(
        User $user,
        int $amount,
        string $status,
        string $time,
        string $date = self::TARGET_DATE,
    ): Order {
        $createdAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $date.' '.$time,
            'Asia/Tokyo'
        );

        return Order::create([
            'user_id'    => $user->id,
            'amount'     => $amount,
            'status'     => $status,
            'created_at' => $createdAt,
        ]);
    }
}
