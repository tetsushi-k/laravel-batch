<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * バッチ実行ログモデル
 *
 * 冪等性担保の中核。バッチ実行前にこのテーブルを参照し、
 * 同月の成功ログが存在する場合は処理をスキップする。
 *
 * status の遷移:
 *   running（開始時）→ success（正常完了）| failed（例外発生）
 *
 * @property int $id
 * @property string $command_name 実行コマンド名
 * @property string $execution_date 処理対象月 (YYYY-MM)
 * @property string $status 'running' | 'success' | 'failed'
 * @property \Carbon\Carbon $executed_at 実行開始日時
 * @property string|null $memo エラーメッセージや備考
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BatchExecutionLog extends Model
{
    protected $fillable = [
        'command_name',
        'execution_date',
        'status',
        'executed_at',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    /**
     * 指定コマンド・対象月の成功ログが存在するか判定する。
     * コマンドクラスの冪等性チェックで使用する。
     */
    public static function hasSucceeded(string $commandName, string $executionDate): bool
    {
        return self::where('command_name', $commandName)
            ->where('execution_date', $executionDate)
            ->where('status', 'success')
            ->exists();
    }
}
