<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 注文シーダー
 *
 * 月次バッチ・日次バッチ双方の動作確認用注文データを生成する。
 * - 前月分: 15件（paid 10件 + unpaid 5件） … 月次バッチ用
 * - 今月分: 5件（動作確認用・集計対象外）
 * - 昨日分: 5件（paid 3件 + unpaid 2件） … 日次バッチ用
 *
 * 未払い注文（unpaid）が存在するユーザーに対して
 * 月次バッチ実行時にリマインドメールが送信される。
 * 昨日分の未払いは日次バッチ実行時に Slack 通知の警告マークが付く。
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('  ! ユーザーが存在しません。UserSeeder を先に実行してください。');
            return;
        }

        $lastMonth = Carbon::now('Asia/Tokyo')->subMonth();
        $thisMonth = Carbon::now('Asia/Tokyo');
        $yesterday = Carbon::yesterday('Asia/Tokyo');

        // 前月分の注文データ（集計対象）
        $lastMonthOrders = [
            // user1: paid 3件
            ['user_id' => $users[0]->id, 'amount' => 15000, 'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(5)],
            ['user_id' => $users[0]->id, 'amount' => 8500,  'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(12)],
            ['user_id' => $users[0]->id, 'amount' => 3200,  'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(20)],
            // user2: paid 2件 + unpaid 2件（リマインド対象）
            ['user_id' => $users[1]->id, 'amount' => 22000, 'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(3)],
            ['user_id' => $users[1]->id, 'amount' => 9800,  'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(15)],
            ['user_id' => $users[1]->id, 'amount' => 5500,  'status' => 'unpaid', 'created_at' => $lastMonth->copy()->setDay(18)],
            ['user_id' => $users[1]->id, 'amount' => 12000, 'status' => 'unpaid', 'created_at' => $lastMonth->copy()->setDay(25)],
            // user3: paid 3件
            ['user_id' => $users[2]->id, 'amount' => 4800,  'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(7)],
            ['user_id' => $users[2]->id, 'amount' => 17500, 'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(14)],
            ['user_id' => $users[2]->id, 'amount' => 6200,  'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(22)],
            // user4: paid 2件 + unpaid 1件（リマインド対象）
            ['user_id' => $users[3]->id, 'amount' => 33000, 'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(2)],
            ['user_id' => $users[3]->id, 'amount' => 11000, 'status' => 'paid',   'created_at' => $lastMonth->copy()->setDay(16)],
            ['user_id' => $users[3]->id, 'amount' => 8800,  'status' => 'unpaid', 'created_at' => $lastMonth->copy()->setDay(28)],
            // user5: unpaid 2件（リマインド対象）
            ['user_id' => $users[4]->id, 'amount' => 19800, 'status' => 'unpaid', 'created_at' => $lastMonth->copy()->setDay(10)],
            ['user_id' => $users[4]->id, 'amount' => 7200,  'status' => 'unpaid', 'created_at' => $lastMonth->copy()->setDay(23)],
        ];

        // 今月分の注文データ（集計対象外・確認用）
        $thisMonthOrders = [
            ['user_id' => $users[0]->id, 'amount' => 12000, 'status' => 'paid',   'created_at' => $thisMonth->copy()->setDay(1)],
            ['user_id' => $users[1]->id, 'amount' => 6800,  'status' => 'unpaid', 'created_at' => $thisMonth->copy()->setDay(3)],
            ['user_id' => $users[2]->id, 'amount' => 25000, 'status' => 'paid',   'created_at' => $thisMonth->copy()->setDay(5)],
            ['user_id' => $users[3]->id, 'amount' => 4500,  'status' => 'unpaid', 'created_at' => $thisMonth->copy()->setDay(7)],
            ['user_id' => $users[4]->id, 'amount' => 15500, 'status' => 'paid',   'created_at' => $thisMonth->copy()->setDay(9)],
        ];

        // 昨日分の注文データ（日次バッチの集計対象）
        // paid 3件・unpaid 2件で、Slack 通知に未払い件数の警告が出る
        $yesterdayOrders = [
            ['user_id' => $users[0]->id, 'amount' => 7800,  'status' => 'paid',   'created_at' => $yesterday->copy()->setTime(10, 0)],
            ['user_id' => $users[1]->id, 'amount' => 13200, 'status' => 'paid',   'created_at' => $yesterday->copy()->setTime(11, 30)],
            ['user_id' => $users[2]->id, 'amount' => 4500,  'status' => 'unpaid', 'created_at' => $yesterday->copy()->setTime(14, 15)],
            ['user_id' => $users[3]->id, 'amount' => 9600,  'status' => 'paid',   'created_at' => $yesterday->copy()->setTime(16, 45)],
            ['user_id' => $users[4]->id, 'amount' => 3000,  'status' => 'unpaid', 'created_at' => $yesterday->copy()->setTime(20, 0)],
        ];

        $allOrders = array_merge($lastMonthOrders, $thisMonthOrders, $yesterdayOrders);

        foreach ($allOrders as $order) {
            Order::create($order);
        }

        $lastMonthStr = $lastMonth->format('Y-m');
        $yesterdayStr = $yesterday->toDateString();
        $unpaidCount = collect($lastMonthOrders)->where('status', 'unpaid')->count();
        $yesterdayUnpaidCount = collect($yesterdayOrders)->where('status', 'unpaid')->count();

        $this->command->info("  ✓ Orders: 前月({$lastMonthStr}) 15件 + 今月 5件 + 昨日({$yesterdayStr}) 5件 = 合計25件を作成しました");
        $this->command->info("  ✓ うち前月の未払い: {$unpaidCount}件（月次バッチのリマインド対象: user2, user4, user5）");
        $this->command->info("  ✓ うち昨日の未払い: {$yesterdayUnpaidCount}件（日次バッチで Slack に警告通知）");
    }
}
