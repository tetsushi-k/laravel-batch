<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 注文シーダー
 *
 * バッチ処理の動作確認用注文データを生成する。
 * - 前月分: 15件（paid 10件 + unpaid 5件）
 * - 今月分: 5件（動作確認用）
 *
 * 未払い注文（unpaid）が存在するユーザーに対して
 * バッチ実行時にリマインドメールが送信される。
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

        $allOrders = array_merge($lastMonthOrders, $thisMonthOrders);

        foreach ($allOrders as $order) {
            Order::create($order);
        }

        $lastMonthStr = $lastMonth->format('Y-m');
        $unpaidCount = collect($lastMonthOrders)->where('status', 'unpaid')->count();

        $this->command->info("  ✓ Orders: 前月({$lastMonthStr}) 15件 + 今月 5件 = 合計20件を作成しました");
        $this->command->info("  ✓ うち前月の未払い注文: {$unpaidCount}件（リマインド対象: user2, user4, user5）");
    }
}
