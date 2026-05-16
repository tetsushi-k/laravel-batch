<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * バッチ実行ログテーブルのマイグレーション
 *
 * 冪等性担保の核となるテーブル。
 * command_name + execution_date + status の組み合わせでチェックし、
 * 同月に同じコマンドが成功済みであれば処理をスキップする。
 * --force オプション使用時は running に上書きして再実行を許可する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command_name')
                ->comment('実行されたArtisanコマンド名');
            $table->string('execution_date', 7)
                ->comment('処理対象月 (YYYY-MM)');
            $table->enum('status', ['running', 'success', 'failed'])
                ->default('running')
                ->comment('実行ステータス');
            $table->timestamp('executed_at')
                ->comment('実行開始日時');
            $table->text('memo')
                ->nullable()
                ->comment('エラーメッセージや備考');
            $table->timestamps();

            // 冪等性チェック用インデックス
            $table->index(['command_name', 'execution_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_execution_logs');
    }
};
