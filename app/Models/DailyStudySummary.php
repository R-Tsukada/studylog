<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyStudySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_date',
        'total_minutes',
        'session_count',
        'subject_breakdown',
        'study_session_minutes',    // 新規追加
        'pomodoro_minutes',         // 新規追加
        'total_focus_sessions',     // 新規追加
        'grass_level',              // 新規追加
        'streak_days',              // 新規追加
    ];

    protected $casts = [
        'study_date' => 'date',
        'total_minutes' => 'integer',
        'session_count' => 'integer',
        'subject_breakdown' => 'array',
        'study_session_minutes' => 'integer',
        'pomodoro_minutes' => 'integer',
        'total_focus_sessions' => 'integer',
        'grass_level' => 'integer',
        'streak_days' => 'integer',
    ];

    /**
     * このサマリーが属するユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 日付範囲でフィルター
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('study_date', [$startDate, $endDate]);
    }

    /**
     * ユーザーでフィルター
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 最新順で取得
     */
    public function scopeRecent($query, $limit = null)
    {
        $query = $query->orderBy('study_date', 'desc');
        
        if ($limit !== null) {
            $query = $query->limit($limit);
        }
        
        return $query;
    }

    /**
     * 新規スコープ
     */
    public function scopeWithGrassLevel($query, $level)
    {
        return $query->where('grass_level', $level);
    }

    public function scopeActiveStudyDays($query)
    {
        return $query->where('total_minutes', '>', 0);
    }

    /**
     * 新規メソッド
     */
    public function updateFromStudySession($studySession): void
    {
        $this->study_session_minutes += $studySession->duration_minutes;
        $this->recalculateTotal();
        $this->updateSubjectBreakdown($studySession);
    }

    public function updateFromPomodoroSession($pomodoroSession): void
    {
        if ($pomodoroSession->session_type === 'focus' && $pomodoroSession->is_completed) {
            $this->pomodoro_minutes += $pomodoroSession->actual_duration;
            $this->total_focus_sessions += 1;
            $this->recalculateTotal();
            $this->updateSubjectBreakdownFromPomodoro($pomodoroSession);
        }
    }

    public function updateSubjectBreakdown($studySession): void
    {
        $breakdown = $this->subject_breakdown ?? [];
        $subjectName = $studySession->subjectArea->name ?? 'その他';
        
        if (!isset($breakdown[$subjectName])) {
            $breakdown[$subjectName] = 0;
        }
        
        $breakdown[$subjectName] += $studySession->duration_minutes;
        $this->subject_breakdown = $breakdown;
    }

    public function updateSubjectBreakdownFromPomodoro($pomodoroSession): void
    {
        $breakdown = $this->subject_breakdown ?? [];
        $subjectName = $pomodoroSession->subjectArea->name ?? 'その他';
        
        if (!isset($breakdown[$subjectName])) {
            $breakdown[$subjectName] = 0;
        }
        
        $breakdown[$subjectName] += $pomodoroSession->actual_duration;
        $this->subject_breakdown = $breakdown;
    }

    private function recalculateTotal(): void
    {
        $this->total_minutes = $this->study_session_minutes + $this->pomodoro_minutes;
        $this->grass_level = $this->calculateGrassLevel($this->total_minutes);
    }

    public function calculateGrassLevel(int $totalMinutes): int
    {
        if ($totalMinutes === 0) return 0;
        if ($totalMinutes <= 60) return 1;
        if ($totalMinutes <= 120) return 2;
        return 3;
    }

    /**
     * 草表示用のデータ配列を取得
     */
    public function toGrassData(): array
    {
        return [
            'date' => $this->study_date->format('Y-m-d'),
            'total_minutes' => $this->total_minutes,
            'level' => $this->grass_level,
            'study_session_minutes' => $this->study_session_minutes,
            'pomodoro_minutes' => $this->pomodoro_minutes,
            'session_count' => $this->session_count,
            'focus_sessions' => $this->total_focus_sessions,
        ];
    }
}
