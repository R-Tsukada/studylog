<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingLog extends Model
{
    public $timestamps = false; // created_atのみ使用

    protected $fillable = [
        'user_id',
        'event_type',
        'step_number',
        'data',
        'session_id',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    // イベントタイプ定数
    const EVENT_STARTED = 'started';

    const EVENT_STEP_COMPLETED = 'step_completed';

    const EVENT_STEP_ENTERED = 'step_entered';

    const EVENT_SKIPPED = 'skipped';

    const EVENT_COMPLETED = 'completed';

    const EVENT_REOPENED = 'reopened';

    const EVENT_ERROR = 'error';

    /**
     * ユーザーリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ログ記録用のヘルパーメソッド
     */
    public static function logEvent(
        int $userId,
        string $eventType,
        ?int $stepNumber = null,
        array $data = [],
        ?string $sessionId = null,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'step_number' => $stepNumber,
            'data' => $data,
            'session_id' => $sessionId ?? session()->getId(),
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * 特定期間のイベントを取得
     */
    public function scopeInPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 特定イベントタイプでフィルタ
     */
    public function scopeOfType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * ユーザーの完了率を計算
     */
    public static function getCompletionRate(string $startDate, string $endDate): float
    {
        $stats = self::inPeriod($startDate, $endDate)
            ->whereIn('event_type', [self::EVENT_STARTED, self::EVENT_COMPLETED])
            ->selectRaw('event_type, COUNT(DISTINCT user_id) as user_count')
            ->groupBy('event_type')
            ->pluck('user_count', 'event_type');

        $started = $stats[self::EVENT_STARTED] ?? 0;
        $completed = $stats[self::EVENT_COMPLETED] ?? 0;

        return $started > 0 ? round(($completed / $started) * 100, 2) : 0;
    }
}
