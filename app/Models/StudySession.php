<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class StudySession extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'subject_area_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'study_comment'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer'
    ];

    // リレーション
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectArea(): BelongsTo
    {
        return $this->belongsTo(SubjectArea::class);
    }

    // スコープ
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('started_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('started_at', now()->month)
                    ->whereYear('started_at', now()->year);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $limit = null)
    {
        $query = $query->orderBy('started_at', 'desc');
        if ($limit !== null) {
            $query = $query->limit($limit);
        }
        return $query;
    }

    // ヘルパーメソッド
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }

    public function calculateDuration(): int
    {
        if (!$this->ended_at) {
            return 0;
        }
        
        return $this->started_at->diffInMinutes($this->ended_at);
    }

    public function endSession(?string $comment = null): bool
    {
        $this->ended_at = now();
        $this->duration_minutes = $this->calculateDuration();
        
        if ($comment) {
            $this->study_comment = $comment;
        }
        
        return $this->save();
    }

    // 現在進行中のセッションを取得
    public static function getCurrentSession($userId)
    {
        return static::active()->forUser($userId)->first();
    }

    // ユーザーの学習履歴を取得
    public static function getHistory($userId, $limit = 20)
    {
        return static::completed()
            ->forUser($userId)
            ->with('subjectArea.examType')
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
