<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class FutureVision extends Model
{
    // Mass Assignment攻撃防止：機密情報を除外
    protected $fillable = [
        'title',
        'description',
        'salary_increase',
        'target_score', 
        'achievement_deadline',
        'exam_type_id',
        // user_id, is_active, is_public, priorityは除外！
    ];

    protected $casts = [
        'achievement_deadline' => 'date',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'salary_increase' => 'integer',
        'target_score' => 'integer',
        'priority' => 'integer',
    ];

    protected $hidden = [
        // 公開時に隠すべき情報は必要に応じて追加
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

    public function benefits(): HasMany
    {
        return $this->hasMany(FutureVisionBenefit::class)
            ->orderBy('display_order');
    }

    public function careerDetails(): HasOne
    {
        return $this->hasOne(FutureVisionCareerDetail::class);
    }

    // セキュアなスコープ（ユーザー認可チェック付き）
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    public function scopeByPriority(Builder $query, string $order = 'desc'): Builder
    {
        return $query->orderBy('priority', $order);
    }

    // カーソルベースページネーション用
    public function scopeAfterCursor(Builder $query, ?int $cursor): Builder
    {
        if ($cursor) {
            return $query->where('id', '>', $cursor);
        }
        return $query;
    }

    // ビジネスロジック
    public function canBeViewedBy(?User $user): bool
    {
        // 公開設定または所有者
        return $this->is_public || ($user && $user->id === $this->user_id);
    }

    public function canBeEditedBy(?User $user): bool
    {
        // 所有者のみ
        return $user && $user->id === $this->user_id;
    }

    // 安全な属性設定メソッド
    public function setUserIdSafely(int $userId): void
    {
        // 認証済みユーザーのIDのみ設定可能
        if (auth()->check() && auth()->id() === $userId) {
            $this->user_id = $userId;
        }
    }

    public function togglePublicSafely(): bool
    {
        // 所有者のみが公開設定を変更可能
        if ($this->canBeEditedBy(auth()->user())) {
            $this->is_public = !$this->is_public;
            return $this->save();
        }
        return false;
    }
}
