<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ユーザーシーダー
 *
 * バッチ処理の動作確認用テストユーザーを5件生成する。
 * password は全ユーザー共通で "password" を使用（開発環境専用）。
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => '山田 太郎', 'email' => 'yamada@example.com'],
            ['name' => '佐藤 花子', 'email' => 'sato@example.com'],
            ['name' => '鈴木 一郎', 'email' => 'suzuki@example.com'],
            ['name' => '田中 美咲', 'email' => 'tanaka@example.com'],
            ['name' => '高橋 健太', 'email' => 'takahashi@example.com'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                ]
            );
        }

        $this->command->info('  ✓ Users: 5件を作成しました');
    }
}
