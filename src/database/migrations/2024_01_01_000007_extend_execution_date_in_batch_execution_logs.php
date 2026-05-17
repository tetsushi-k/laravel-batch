<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * batch_execution_logs.execution_date のカラム長を拡張するマイグレーション
 *
 * 月次バッチは YYYY-MM（7文字）を使用していたが、
 * 日次バッチの追加により YYYY-MM-DD（10文字）も格納する必要が生じたため、
 * VARCHAR(7) → VARCHAR(10) に変更する。
 * 既存の月次データには影響しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_execution_logs', function (Blueprint $table) {
            $table->string('execution_date', 10)
                ->comment('処理対象日 (YYYY-MM or YYYY-MM-DD)')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('batch_execution_logs', function (Blueprint $table) {
            $table->string('execution_date', 7)
                ->comment('処理対象月 (YYYY-MM)')
                ->change();
        });
    }
};
