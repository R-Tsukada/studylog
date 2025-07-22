<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomodoroSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_session_id',
        'subject_area_id',
        'session_type',
        'planned_duration',
        'actual_duration',
        'started_at',
        'completed_at',
        'is_completed',
        'was_interrupted',
        'settings',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
        'was_interrupted' => 'boolean',
        'settings' => 'array',
    ];

    // リレーション
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }

    public function subjectArea(): BelongsTo
    {
        return $this->belongsTo(SubjectArea::class);
    }

    // スコープ
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeFocusSessions($query)
    {
        return $query->where('session_type', 'focus');
    }

    public function scopeBreakSessions($query)
    {
        return $query->whereIn('session_type', ['short_break', 'long_break']);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('started_at', 'desc')->limit($limit);
    }

    // アクセサ・ミューテータ
    public function getDurationInMinutesAttribute()
    {
        return $this->actual_duration ?? $this->planned_duration;
    }

    public function getIsActiveAttribute()
    {
        return !$this->is_completed && !is_null($this->started_at);
    }

    public function getCompletionPercentageAttribute()
    {
        if (!$this->actual_duration || !$this->planned_duration) {
            return 0;
        }
        
        return min(100, round(($this->actual_duration / $this->planned_duration) * 100));
    }
}
