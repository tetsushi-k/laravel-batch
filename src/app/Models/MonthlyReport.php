<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 月次レポートモデル
 *
 * バッチ処理が毎月生成する集計結果。
 * target_month はユニーク制約があるため、updateOrCreate で安全に保存できる。
 *
 * @property int $id
 * @property string $target_month 集計対象月 (YYYY-MM)
 * @property int $total_orders 総注文件数
 * @property int $total_amount 総売上金額（円）
 * @property int $paid_orders 支払済み注文件数
 * @property int $unpaid_orders 未払い注文件数
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MonthlyReport extends Model
{
    protected $fillable = [
        'target_month',
        'total_orders',
        'total_amount',
        'paid_orders',
        'unpaid_orders',
    ];

    protected function casts(): array
    {
        return [
            'total_orders' => 'integer',
            'total_amount' => 'integer',
            'paid_orders' => 'integer',
            'unpaid_orders' => 'integer',
        ];
    }

    /**
     * 支払い率を計算して返す。（0〜100 の整数）
     */
    public function getPaidRateAttribute(): int
    {
        if ($this->total_orders === 0) {
            return 0;
        }

        return (int) round(($this->paid_orders / $this->total_orders) * 100);
    }
}
