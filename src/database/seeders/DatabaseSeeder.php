<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * データベースシーダー（エントリポイント）
 *
 * `php artisan db:seed` 実行時に呼び出される。
 * UserSeeder → OrderSeeder の順で実行する（外部キー制約のため）。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('テストデータを投入します...');

        $this->call([
            UserSeeder::class,
            OrderSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('テストデータの投入が完了しました。');
        $this->command->info('バッチを実行するには: php artisan app:monthly-report');
    }
}
