<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 注文モデル
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount 注文金額（円）
 * @property string $status 'paid' | 'unpaid'
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * この注文を行ったユーザーを返す。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 支払い済みかどうかを判定する。
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * 未払いかどうかを判定する。
     */
    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }
}
