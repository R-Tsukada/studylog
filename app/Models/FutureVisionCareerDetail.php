<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FutureVisionCareerDetail extends Model
{
    protected $fillable = [
        'position_upgrade',
        'company_opportunities', 
        'skill_improvement_areas',
    ];

    public function futureVision(): BelongsTo
    {
        return $this->belongsTo(FutureVision::class);
    }
}
