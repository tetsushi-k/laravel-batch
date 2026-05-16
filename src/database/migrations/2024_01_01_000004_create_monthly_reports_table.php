<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 月次レポートテーブルのマイグレーション
 *
 * バッチ処理が毎月生成する集計結果を格納する。
 * target_month にユニーク制約を設けることで、同月の二重登録を防ぐ。
 * updateOrCreate を使用して再実行時も安全に上書きできる設計。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('target_month', 7)
                ->unique()
                ->comment('集計対象月 (YYYY-MM)');
            $table->unsignedInteger('total_orders')
                ->default(0)
                ->comment('総注文件数');
            $table->unsignedBigInteger('total_amount')
                ->default(0)
                ->comment('総売上金額（円）');
            $table->unsignedInteger('paid_orders')
                ->default(0)
                ->comment('支払済み注文件数');
            $table->unsignedInteger('unpaid_orders')
                ->default(0)
                ->comment('未払い注文件数');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
