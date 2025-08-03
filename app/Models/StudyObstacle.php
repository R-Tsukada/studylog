<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class StudyObstacle extends Model
{
    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_RARELY = 'rarely';

    protected $fillable = [
        'obstacle_title',
        'obstacle_description',
        'severity_level',
        'solution_title',
        'solution_description',
        'occurrence_frequency',
        'last_occurred_at',
        // user_id, exam_type_id, obstacle_category_id, is_active, is_resolved, 
        // effectiveness_rating, resolved_atは除外
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_resolved' => 'boolean',
        'severity_level' => 'integer',
        'effectiveness_rating' => 'integer',
        'last_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
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

    public function obstacleCategory(): BelongsTo
    {
        return $this->belongsTo(ObstacleCategory::class);
    }

    public function solutionSteps(): HasMany
    {
        return $this->hasMany(StudyObstacleSolutionStep::class)
            ->orderBy('step_order');
    }

    // セキュアなスコープ
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('is_resolved', true);
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('obstacle_category_id', $categoryId);
    }

    public function scopeBySeverity(Builder $query, string $order = 'desc'): Builder
    {
        return $query->orderBy('severity_level', $order);
    }

    public function scopeByFrequency(Builder $query, string $frequency): Builder
    {
        return $query->where('occurrence_frequency', $frequency);
    }

    // ビジネスロジック
    public function canBeEditedBy(?User $user): bool
    {
        return $user && $user->id === $this->user_id;
    }

    public function markAsResolved(): bool
    {
        $this->is_resolved = true;
        $this->resolved_at = now();
        return $this->save();
    }

    public function recordOccurrence(): bool
    {
        $this->last_occurred_at = now();
        return $this->save();
    }

    public function rateEffectiveness(int $rating): bool
    {
        if ($rating >= 1 && $rating <= 5) {
            $this->effectiveness_rating = $rating;
            return $this->save();
        }
        return false;
    }
}
