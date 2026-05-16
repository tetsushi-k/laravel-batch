<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 注文テーブルのマイグレーション
 *
 * ECサービスの注文データを格納する。
 * バッチ処理では前月分の注文を status 別に集計する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('注文者のユーザーID');
            $table->unsignedInteger('amount')->comment('注文金額（円）');
            $table->enum('status', ['paid', 'unpaid'])
                ->default('unpaid')
                ->comment('支払いステータス');
            $table->timestamps();

            // 月次集計クエリの高速化
            $table->index(['created_at', 'status']);
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
