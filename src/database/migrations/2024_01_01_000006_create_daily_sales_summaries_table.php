<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique()->comment('集計対象日');
            $table->unsignedBigInteger('total_amount')->default(0)->comment('当日の総売上金額（円）');
            $table->unsignedInteger('order_count')->default(0)->comment('当日の総注文件数');
            $table->unsignedInteger('paid_count')->default(0)->comment('当日の支払済み件数');
            $table->unsignedInteger('unpaid_count')->default(0)->comment('当日の未払い件数');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};
