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
    ];

    protected $casts = [
        'study_date' => 'date',
        'total_minutes' => 'integer',
        'session_count' => 'integer',
        'subject_breakdown' => 'array',
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
}
