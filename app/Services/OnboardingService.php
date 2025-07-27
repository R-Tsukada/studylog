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
        $dateFormat = match ($groupBy) {
            'week' => 'YEARWEEK(created_at)',
            'month' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default => 'DATE(created_at)',
        };

        return DB::table('onboarding_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                {$dateFormat} as date,
                event_type,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users
            ")
            ->groupBy(DB::raw($dateFormat), 'event_type')
            ->orderBy(DB::raw($dateFormat))
            ->get()
            ->groupBy('date');
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
    public function updateUserProgressSafely(User $user, int $currentStep, array $completedSteps = [], array $stepData = []): bool
    {
        return DB::transaction(function () use ($user, $currentStep, $completedSteps, $stepData) {
            // 楽観的ロック（updated_atをチェック）
            $currentTimestamp = $user->updated_at;

            $user->updateOnboardingProgress($currentStep, $completedSteps, $stepData);

            // 他のプロセスで更新されていないかチェック
            $user->refresh();
            if ($user->updated_at->ne($currentTimestamp)) {
                // 楽観的ロック違反時の処理は、実際のプロジェクトに応じて調整
                logger()->warning('Onboarding concurrent update detected', [
                    'user_id' => $user->id,
                    'current_step' => $currentStep,
                ]);
            }

            return true;
        });
    }

    /**
     * キャッシュクリア
     */
    public function clearAnalyticsCache(): void
    {
        Cache::tags(['onboarding'])->flush();
    }
}
