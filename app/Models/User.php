<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nickname',
        'email',
        'password',
        'google_id',
        'avatar',
        'email_verified_at',
        'role',
        'onboarding_completed_at',
        'onboarding_progress',
        'onboarding_skipped',
        'onboarding_version',
        'login_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'onboarding_completed_at' => 'datetime',
            'onboarding_progress' => 'array',
            'onboarding_skipped' => 'boolean',
        ];
    }

    // リレーション
    public function studySessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    public function studyGoals(): HasMany
    {
        return $this->hasMany(StudyGoal::class);
    }

    public function onboardingLogs(): HasMany
    {
        return $this->hasMany(OnboardingLog::class);
    }

    // ヘルパーメソッド
    public function isGoogleUser(): bool
    {
        return ! is_null($this->google_id);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(string $permission): bool
    {
        $allowedPermissions = config('onboarding.analytics_permissions', ['view_analytics', 'admin']);

        return in_array($permission, $allowedPermissions) && $this->hasRole('admin');
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        // Gravatar URLをフォールバックとして使用
        $hash = md5(strtolower(trim($this->email)));

        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=100";
    }

    // オンボーディング関連メソッド

    /**
     * オンボーディングを表示すべきかチェック
     */
    public function shouldShowOnboarding(): bool
    {
        // 1. 既に完了している場合は表示しない
        if ($this->onboarding_completed_at) {
            return false;
        }

        // 2. 登録から指定日数以内のユーザーのみ
        $showWithinDays = config('onboarding.show_within_days', 30);
        $daysSinceRegistration = $this->created_at->diffInDays(now());
        if ($daysSinceRegistration > $showWithinDays) {
            return false;
        }

        // 3. ログイン回数が指定回数以下（新規ユーザー判定）
        $maxLoginCount = config('onboarding.max_login_count', 5);
        if ($this->login_count > $maxLoginCount) {
            return false;
        }

        return true;
    }

    /**
     * ログイン回数をインクリメント
     */
    public function incrementLoginCount(): void
    {
        $this->increment('login_count');
    }

    /**
     * オンボーディング進捗を更新
     */
    public function updateOnboardingProgress(
        int $currentStep,
        array $completedSteps = [],
        array $stepData = []
    ): void {
        $totalSteps = config('onboarding.total_steps', 4);

        // 入力値検証
        if ($currentStep < 1 || $currentStep > $totalSteps) {
            throw new \InvalidArgumentException("Invalid step number: {$currentStep}");
        }

        $progress = $this->onboarding_progress ?? [];

        $progress['current_step'] = $currentStep;
        $progress['completed_steps'] = array_unique(array_merge(
            $progress['completed_steps'] ?? [],
            array_filter($completedSteps, fn ($step) => $step >= 1 && $step <= $totalSteps)
        ));
        $progress['step_data'] = array_merge(
            $progress['step_data'] ?? [],
            $stepData
        );
        $progress['last_activity_at'] = now()->toISOString();

        // 開始時刻が未設定の場合は設定
        if (! isset($progress['started_at'])) {
            $progress['started_at'] = now()->toISOString();

            // 開始ログ記録
            OnboardingLog::logEvent($this->id, OnboardingLog::EVENT_STARTED);
        }

        // ステップ完了ログ記録（重複を避ける）- updateの前に実行
        $existingCompletedSteps = $this->getOriginal('onboarding_progress')['completed_steps'] ?? [];
        $newlyCompletedSteps = array_diff($completedSteps, $existingCompletedSteps);

        $this->update(['onboarding_progress' => $progress]);

        foreach ($newlyCompletedSteps as $step) {
            OnboardingLog::logEvent(
                $this->id,
                OnboardingLog::EVENT_STEP_COMPLETED,
                $step,
                ['timestamp' => now()->toISOString()]
            );
        }
    }

    /**
     * オンボーディング完了処理
     */
    public function completeOnboarding(array $completionData = []): void
    {
        $totalSteps = config('onboarding.total_steps', 4);

        $progress = $this->onboarding_progress ?? [];
        $progress['completed_steps'] = range(1, $totalSteps);
        $progress['completed_at'] = now()->toISOString();
        $progress['completion_data'] = $completionData;

        $this->update([
            'onboarding_completed_at' => now(),
            'onboarding_progress' => $progress,
            'onboarding_skipped' => false,
        ]);

        // 完了ログ記録
        OnboardingLog::logEvent(
            $this->id,
            OnboardingLog::EVENT_COMPLETED,
            null,
            array_merge(['completion_method' => 'normal'], $completionData)
        );
    }

    /**
     * オンボーディングスキップ処理
     */
    public function skipOnboarding(?int $currentStep = null, string $reason = 'user_choice'): void
    {
        $this->update([
            'onboarding_completed_at' => now(),
            'onboarding_skipped' => true,
        ]);

        // スキップログ記録
        OnboardingLog::logEvent(
            $this->id,
            OnboardingLog::EVENT_SKIPPED,
            $currentStep,
            [
                'skip_method' => $reason,
                'completed_steps' => $this->onboarding_progress['completed_steps'] ?? [],
            ]
        );
    }

    /**
     * オンボーディング統計取得
     */
    public function getOnboardingStats(): array
    {
        $progress = $this->onboarding_progress ?? [];
        $totalSteps = config('onboarding.total_steps', 4);

        return [
            'is_completed' => ! is_null($this->onboarding_completed_at),
            'is_skipped' => $this->onboarding_skipped,
            'completed_steps' => $progress['completed_steps'] ?? [],
            'current_step' => $progress['current_step'] ?? 1,
            'started_at' => $progress['started_at'] ?? null,
            'total_steps' => $totalSteps,
            'completion_rate' => count($progress['completed_steps'] ?? []) / $totalSteps * 100,
            'version' => $this->onboarding_version,
        ];
    }
}
