<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StudyObstacleSolutionStep extends Model
{
    protected $fillable = [
        'step_order',
        'description',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        // study_obstacle_id, is_completed, completed_atは除外
    ];

    protected $casts = [
        'step_order' => 'integer',
        'is_completed' => 'boolean',
        'estimated_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function studyObstacle(): BelongsTo
    {
        return $this->belongsTo(StudyObstacle::class);
    }

    // スコープ
    public function scopeOrderedByStep(Builder $query): Builder
    {
        return $query->orderBy('step_order');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    // ビジネスロジック
    public function markAsCompleted(?int $actualDuration = null): bool
    {
        $this->is_completed = true;
        $this->completed_at = now();
        if ($actualDuration !== null) {
            $this->actual_duration_minutes = $actualDuration;
        }
        return $this->save();
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $user && $this->studyObstacle && $this->studyObstacle->canBeEditedBy($user);
    }
}
