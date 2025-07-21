<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudyGoal extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'exam_type_id',
        'daily_minutes_goal',
        'weekly_minutes_goal',
        'exam_date',
        'is_active'
    ];

    protected $casts = [
        'exam_date' => 'date',
        'is_active' => 'boolean',
        'daily_minutes_goal' => 'integer',
        'weekly_minutes_goal' => 'integer'
    ];

    // リレーション
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    // スコープ
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForExamType($query, $examTypeId)
    {
        return $query->where('exam_type_id', $examTypeId);
    }
}
