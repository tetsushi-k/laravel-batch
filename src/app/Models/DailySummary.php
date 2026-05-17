<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 日次売上サマリーモデル
 *
 * DailySalesAlertCommand が集計した結果を保存する。
 * date カラムにユニークインデックスがあるため、
 * updateOrCreate を使うことで同日の二重保存を防ぐ。
 *
 * @property int $id
 * @property \Carbon\Carbon $date 集計対象日
 * @property int $total_amount 総売上金額（円）
 * @property int $order_count 総注文件数
 * @property int $paid_count 支払済み件数
 * @property int $unpaid_count 未払い件数
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DailySummary extends Model
{
    protected $table = 'daily_sales_summaries';

    protected $fillable = [
        'date',
        'total_amount',
        'order_count',
        'paid_count',
        'unpaid_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
