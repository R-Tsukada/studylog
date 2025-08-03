<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ObstacleCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // created_atのみ使用（updated_atは使用しない）
    const UPDATED_AT = null;

    public function studyObstacles(): HasMany
    {
        return $this->hasMany(StudyObstacle::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderedBySort(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
