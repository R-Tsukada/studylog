<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class FutureVisionBenefit extends Model
{
    protected $fillable = [
        'benefit_type',
        'title',
        'description',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    // created_atのみ使用（updated_atは使用しない）
    const UPDATED_AT = null;

    public function futureVision(): BelongsTo
    {
        return $this->belongsTo(FutureVision::class);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('benefit_type', $type);
    }

    public function scopeOrderedByDisplay(Builder $query): Builder
    {
        return $query->orderBy('display_order');
    }
}
