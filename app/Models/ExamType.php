<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'user_id',
        'is_system',
        'exam_date',
        'exam_notes',
        'color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'exam_date' => 'date',
    ];

    /**
     * この試験タイプに属する学習分野
     */
    public function subjectAreas(): HasMany
    {
        return $this->hasMany(SubjectArea::class);
    }

    /**
     * この試験タイプに対する学習目標
     */
    public function studyGoals(): HasMany
    {
        return $this->hasMany(StudyGoal::class);
    }

    /**
     * アクティブな試験タイプのみを取得するスコープ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
