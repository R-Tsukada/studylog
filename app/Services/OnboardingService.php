<?php

namespace App\Services;

use App\Models\OnboardingLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    /**
     * オンボーディング統計を取得（キャッシュ付き）
     */
    public function getAnalytics(string $startDate, string $endDate, ?string $groupBy = null, ?int $limit = null): array
    {
        // Validate date formats using strict DateTime parsing
        $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
        $startErrors = \DateTime::getLastErrors();

        if (! $startDateTime || ($startErrors && ($startErrors['warning_count'] > 0 || $startErrors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Invalid start date format provided. Expected Y-m-d format.');
        }

        $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
        $endErrors = \DateTime::getLastErrors();

        if (! $endDateTime || ($endErrors && ($endErrors['warning_count'] > 0 || $endErrors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Invalid end date format provided. Expected Y-m-d format.');
        }

        if ($startDateTime > $endDateTime) {
            throw new \InvalidArgumentException('Start date must be before or equal to end date');
        }

        $cacheKey = $this->generateCacheKey('analytics', $startDate, $endDate, $groupBy, $limit);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(config('onboarding.analytics_cache_duration', 30)),
            function () use ($startDate, $endDate, $groupBy, $limit) {
                return $this->calculateAnalytics($startDate, $endDate, $groupBy, $limit);
            }
        );
    }

    /**
     * 統計計算（プライベートメソッド）
     */
    private function calculateAnalytics(string $startDate, string $endDate, ?string $groupBy, ?int $limit): array
    {
        // 基本統計
        $completionRate = $this->getCompletionRate($startDate, $endDate);

        // ステップ別完了率（SQLインジェクション対策済み）
        $stepCompletions = $this->getStepCompletions($startDate, $endDate, $limit);

        // 日別統計
        $dailyStats = $this->getDailyStats($startDate, $endDate, $groupBy);

        // 離脱率分析
        $dropoffAnalysis = $this->getDropoffAnalysis($startDate, $endDate);

        return [
            'completion_rate' => $completionRate,
            'step_completions' => $stepCompletions,
            'daily_stats' => $dailyStats,
            'dropoff_analysis' => $dropoffAnalysis,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }

    /**
     * 完了率を取得（改善済み）
     */
    private function getCompletionRate(string $startDate, string $endDate): array
    {
        $stats = DB::table('onboarding_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                event_type,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->groupBy('event_type')
            ->whereIn('event_type', [
                OnboardingLog::EVENT_STARTED,
                OnboardingLog::EVENT_COMPLETED,
                OnboardingLog::EVENT_SKIPPED,
            ])
            ->pluck('unique_users', 'event_type');

        $started = $stats[OnboardingLog::EVENT_STARTED] ?? 0;
        $completed = $stats[OnboardingLog::EVENT_COMPLETED] ?? 0;
        $skipped = $stats[OnboardingLog::EVENT_SKIPPED] ?? 0;

        return [
            'started' => $started,
            'completed' => $completed,
            'skipped' => $skipped,
            'completion_rate' => $started > 0 ? round(($completed / $started) * 100, 2) : 0,
            'skip_rate' => $started > 0 ? round(($skipped / $started) * 100, 2) : 0,
        ];
    }

    /**
     * ステップ別完了率を取得
     */
    private function getStepCompletions(string $startDate, string $endDate, ?int $limit): Collection
    {
        $query = DB::table('onboarding_logs')
            ->where('event_type', OnboardingLog::EVENT_STEP_COMPLETED)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('step_number')
            ->selectRaw('step_number, COUNT(*) as completions')
            ->groupBy('step_number')
            ->orderBy('step_number');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * 日別統計を取得
     */
    private function getDailyStats(string $startDate, string $endDate, ?string $groupBy): Collection
    {
        $query = DB::table('onboarding_logs')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // セキュリティ強化：事前定義された安全なSQL式のみ使用
        switch ($groupBy) {
            case 'week':
                $query->selectRaw('
                    YEARWEEK(created_at) as date,
                    event_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                ')
                    ->groupBy(DB::raw('YEARWEEK(created_at)'), 'event_type')
                    ->orderBy(DB::raw('YEARWEEK(created_at)'));
                break;

            case 'month':
                $query->selectRaw('
                    DATE_FORMAT(created_at, "%Y-%m") as date,
                    event_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                ')
                    ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'), 'event_type')
                    ->orderBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'));
                break;

            default: // 'day' or null
                $query->selectRaw('
                    DATE(created_at) as date,
                    event_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                ')
                    ->groupBy(DB::raw('DATE(created_at)'), 'event_type')
                    ->orderBy(DB::raw('DATE(created_at)'));
                break;
        }

        return $query->get()->groupBy('date');
    }

    /**
     * 離脱分析を取得
     */
    private function getDropoffAnalysis(string $startDate, string $endDate): array
    {
        $stepStats = DB::table('onboarding_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('event_type', OnboardingLog::EVENT_STEP_COMPLETED)
            ->whereNotNull('step_number')
            ->selectRaw('
                step_number,
                COUNT(DISTINCT user_id) as users_reached
            ')
            ->groupBy('step_number')
            ->orderBy('step_number')
            ->get();

        $dropoffRates = [];
        $previousUsers = null;

        foreach ($stepStats as $step) {
            if ($previousUsers !== null) {
                $dropoffRate = $previousUsers > 0
                    ? round((($previousUsers - $step->users_reached) / $previousUsers) * 100, 2)
                    : 0;
                $dropoffRates["step_{$step->step_number}"] = $dropoffRate;
            }
            $previousUsers = $step->users_reached;
        }

        return [
            'step_completion_counts' => $stepStats,
            'dropoff_rates' => $dropoffRates,
        ];
    }

    /**
     * キャッシュキー生成
     */
    private function generateCacheKey(string $type, ...$params): string
    {
        return 'onboarding:'.$type.':'.md5(serialize($params));
    }

    /**
     * ユーザーのオンボーディング状態を安全に更新
     */
    public function updateUserProgressSafely(
        User $user,
        int $currentStep,
        array $completedSteps = [],
        array $stepData = [],
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): bool {
        return DB::transaction(function () use ($user, $currentStep, $completedSteps, $stepData, $userAgent, $ipAddress) {
            // Store the current version for optimistic locking
            $currentVersion = $user->updated_at;

            // Prepare the progress data
            $progress = $user->onboarding_progress ?? [];
            $progress['current_step'] = $currentStep;
            $progress['completed_steps'] = array_unique(array_merge(
                $progress['completed_steps'] ?? [],
                $completedSteps
            ));
            $progress['step_data'] = array_merge(
                $progress['step_data'] ?? [],
                $stepData
            );
            $progress['updated_at'] = now()->toISOString();

            // Attempt to update with optimistic lock
            $updated = User::where('id', $user->id)
                ->where('updated_at', $currentVersion)
                ->update([
                    'onboarding_progress' => $progress,
                    'updated_at' => now(),
                ]);

            if (! $updated) {
                logger()->warning('Onboarding concurrent update prevented', [
                    'user_id' => $user->id,
                    'current_step' => $currentStep,
                ]);
                throw new \RuntimeException('Concurrent update detected. Please try again.');
            }

            // Log the progress update for each newly completed step
            $previousCompletedSteps = $user->onboarding_progress['completed_steps'] ?? [];
            $newlyCompletedSteps = array_diff($completedSteps, $previousCompletedSteps);

            foreach ($newlyCompletedSteps as $stepNumber) {
                OnboardingLog::logEvent(
                    $user->id,
                    OnboardingLog::EVENT_STEP_COMPLETED,
                    $stepNumber,
                    array_merge(['completed_steps' => $completedSteps], $stepData),
                    null,
                    $userAgent,
                    $ipAddress
                );
            }

            // Refresh the user model to get the latest data
            $user->refresh();

            return true;
        });
    }

    /**
     * キャッシュクリア
     */
    public function clearAnalyticsCache(): void
    {
        $store = Cache::getStore();
        if ($store instanceof \Illuminate\Cache\TaggableStore) {
            Cache::tags(['onboarding'])->flush();
        }
        // Note: タグがサポートされていないドライバーの場合は、
        // 個別のキャッシュキークリアや全体フラッシュが必要に応じて実装可能
    }
}
