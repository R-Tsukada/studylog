<?php

namespace App\Services;

use App\Models\StudySession;
use App\Models\PomodoroSession;
use App\Models\DailyStudySummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudyActivityService
{
    /**
     * ユーザーの統合学習履歴を取得
     */
    public function getUnifiedHistory($userId, $startDate = null, $endDate = null, $limit = 50): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        // 時間計測データ取得
        $timeTrackingSessions = StudySession::byUser($userId)
            ->with('subjectArea.examType')
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'time_tracking',
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea?->name,
                    'exam_type_name' => $session->subjectArea?->examType?->name,
                    'duration_minutes' => $session->duration_minutes,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at,
                    'notes' => $session->study_comment,
                    'status' => $session->ended_at ? 'completed' : 'active',
                    'was_interrupted' => false,
                    'session_details' => [
                        'method' => '自由時間計測',
                        'planned_duration' => null,
                        'actual_duration' => $session->duration_minutes,
                    ],
                    'created_at' => $session->created_at,
                ];
            });

        // ポモドーロデータ取得
        $pomodoroSessions = PomodoroSession::byUser($userId)
            ->with('subjectArea.examType')
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'pomodoro',
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea?->name,
                    'exam_type_name' => $session->subjectArea?->examType?->name,
                    'duration_minutes' => $session->actual_duration ?? $session->planned_duration,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->completed_at,
                    'notes' => $session->notes,
                    'status' => $session->is_completed ? 'completed' : 'active',
                    'was_interrupted' => $session->was_interrupted,
                    'session_details' => [
                        'method' => 'ポモドーロ',
                        'session_type' => $session->session_type,
                        'planned_duration' => $session->planned_duration,
                        'actual_duration' => $session->actual_duration,
                        'completion_rate' => $session->completion_percentage,
                    ],
                    'created_at' => $session->created_at,
                ];
            });

        // データを統合してソート
        return $timeTrackingSessions
            ->concat($pomodoroSessions)
            ->sortByDesc('started_at')
            ->take($limit)
            ->values();
    }

    /**
     * 統合学習統計を取得
     */
    public function getUnifiedStats($userId, $startDate = null, $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        // 時間計測統計
        $timeTrackingStats = $this->getTimeTrackingStats($userId, $startDate, $endDate);
        
        // ポモドーロ統計
        $pomodoroStats = $this->getPomodoroStats($userId, $startDate, $endDate);

        // 統合統計
        $totalDuration = $timeTrackingStats['total_duration'] + $pomodoroStats['total_focus_time'];
        $totalSessions = $timeTrackingStats['total_sessions'] + $pomodoroStats['total_sessions'];

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'overview' => [
                'total_study_time' => $totalDuration,
                'total_sessions' => $totalSessions,
                'average_session_length' => $totalSessions > 0 ? round($totalDuration / $totalSessions, 1) : 0,
                'study_days' => $this->getStudyDaysCount($userId, $startDate, $endDate),
            ],
            'by_method' => [
                'time_tracking' => $timeTrackingStats,
                'pomodoro' => $pomodoroStats,
            ],
            'daily_breakdown' => $this->getDailyBreakdown($userId, $startDate, $endDate),
            'subject_breakdown' => $this->getSubjectBreakdown($userId, $startDate, $endDate),
            'insights' => $this->generateInsights($timeTrackingStats, $pomodoroStats),
        ];
    }

    /**
     * 学習パターン分析とインサイト生成
     */
    public function getStudyInsights($userId): array
    {
        $recentStats = $this->getUnifiedStats($userId, Carbon::now()->subDays(30));
        $previousStats = $this->getUnifiedStats($userId, Carbon::now()->subDays(60), Carbon::now()->subDays(30));

        return [
            'preferred_method' => $this->getPreferredMethod($recentStats),
            'best_study_times' => $this->getBestStudyTimes($userId),
            'productivity_trends' => $this->getProductivityTrends($recentStats, $previousStats),
            'recommendations' => $this->generateRecommendations($userId, $recentStats),
        ];
    }

    /**
     * 次の学習セッションの推奨手法を提案
     */
    public function suggestStudyMethod($userId, $subjectAreaId = null): array
    {
        $recentSessions = $this->getUnifiedHistory($userId, Carbon::now()->subDays(7), null, 10);
        $userInsights = $this->getStudyInsights($userId);
        
        $currentHour = Carbon::now()->hour;
        $isAfternoon = $currentHour >= 13 && $currentHour <= 17;
        
        // 最近のセッションパターン分析
        $recentMethod = $recentSessions->first()['type'] ?? null;
        $avgSessionLength = $recentSessions->avg('duration_minutes') ?? 30;
        
        // 推奨ロジック
        $suggestions = [];
        
        // 長時間学習の場合は時間計測を推奨
        if ($avgSessionLength > 60) {
            $suggestions[] = [
                'method' => 'time_tracking',
                'confidence' => 0.8,
                'reason' => '最近の学習セッションが長時間のため、自由な時間計測が適しています',
            ];
        }
        
        // 午後の時間帯はポモドーロを推奨
        if ($isAfternoon) {
            $suggestions[] = [
                'method' => 'pomodoro',
                'confidence' => 0.7,
                'reason' => '午後の時間帯は集中力が下がりやすいため、ポモドーロテクニックがおすすめです',
            ];
        }
        
        // ユーザーの好みに基づく推奨
        $preferredMethod = $userInsights['preferred_method'];
        if ($preferredMethod) {
            $suggestions[] = [
                'method' => $preferredMethod,
                'confidence' => 0.6,
                'reason' => 'あなたの学習パターンに基づく推奨です',
            ];
        }
        
        // デフォルト推奨
        if (empty($suggestions)) {
            $suggestions[] = [
                'method' => 'pomodoro',
                'confidence' => 0.5,
                'reason' => '初回またはパターンが不明な場合の推奨です',
            ];
        }
        
        // 信頼度でソート
        usort($suggestions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        return [
            'recommended' => $suggestions[0],
            'alternatives' => array_slice($suggestions, 1, 2),
            'context' => [
                'time_of_day' => $currentHour,
                'recent_avg_duration' => round($avgSessionLength, 1),
                'recent_method' => $recentMethod,
            ],
        ];
    }

    // プライベートメソッド

    private function getTimeTrackingStats($userId, $startDate, $endDate): array
    {
        $sessions = StudySession::byUser($userId)
            ->completed()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get();

        return [
            'total_sessions' => $sessions->count(),
            'total_duration' => $sessions->sum('duration_minutes') ?: 0,
            'average_duration' => $sessions->avg('duration_minutes') ?: 0,
            'longest_session' => $sessions->max('duration_minutes') ?: 0,
        ];
    }

    private function getPomodoroStats($userId, $startDate, $endDate): array
    {
        $sessions = PomodoroSession::byUser($userId)
            ->completed()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get();

        $focusSessions = $sessions->where('session_type', 'focus');

        return [
            'total_sessions' => $sessions->count(),
            'focus_sessions' => $focusSessions->count(),
            'total_focus_time' => $focusSessions->sum('actual_duration') ?: 0,
            'completion_rate' => $sessions->count() > 0 ? 
                round((1 - $sessions->where('was_interrupted', true)->count() / $sessions->count()) * 100, 1) : 0,
            'average_focus_duration' => $focusSessions->avg('actual_duration') ?: 0,
        ];
    }

    private function getDailyBreakdown($userId, $startDate, $endDate): array
    {
        $timeTrackingSessions = StudySession::byUser($userId)
            ->completed()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get()
            ->groupBy(fn($session) => $session->started_at->format('Y-m-d'));

        $pomodoroSessions = PomodoroSession::byUser($userId)
            ->completed()
            ->focusSessions()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->get()
            ->groupBy(fn($session) => $session->started_at->format('Y-m-d'));

        $dailyData = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $timeTrackingTime = $timeTrackingSessions->get($dateKey, collect())->sum('duration_minutes');
            $pomodoroTime = $pomodoroSessions->get($dateKey, collect())->sum('actual_duration');

            $dailyData[] = [
                'date' => $dateKey,
                'time_tracking_minutes' => $timeTrackingTime,
                'pomodoro_minutes' => $pomodoroTime,
                'total_minutes' => $timeTrackingTime + $pomodoroTime,
            ];

            $current->addDay();
        }

        return $dailyData;
    }

    private function getSubjectBreakdown($userId, $startDate, $endDate): array
    {
        $unifiedHistory = $this->getUnifiedHistory($userId, $startDate, $endDate, 1000);
        
        return $unifiedHistory
            ->groupBy('subject_area_name')
            ->map(function ($sessions, $subjectName) {
                return [
                    'subject_name' => $subjectName ?: '未分類',
                    'total_duration' => $sessions->sum('duration_minutes'),
                    'session_count' => $sessions->count(),
                    'time_tracking_duration' => $sessions->where('type', 'time_tracking')->sum('duration_minutes'),
                    'pomodoro_duration' => $sessions->where('type', 'pomodoro')->sum('duration_minutes'),
                ];
            })
            ->sortByDesc('total_duration')
            ->values()
            ->toArray();
    }

    private function getStudyDaysCount($userId, $startDate, $endDate): int
    {
        $timeTrackingDays = StudySession::byUser($userId)
            ->completed()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->selectRaw('DATE(started_at) as study_date')
            ->distinct()
            ->count();

        $pomodoroDays = PomodoroSession::byUser($userId)
            ->completed()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->selectRaw('DATE(started_at) as study_date')
            ->distinct()
            ->count();

        // 重複を除いた実際の学習日数を計算（簡略化）
        return max($timeTrackingDays, $pomodoroDays);
    }

    private function getPreferredMethod($stats): ?string
    {
        $timeTrackingTime = $stats['by_method']['time_tracking']['total_duration'];
        $pomodoroTime = $stats['by_method']['pomodoro']['total_focus_time'];

        if ($timeTrackingTime > $pomodoroTime * 1.5) {
            return 'time_tracking';
        } elseif ($pomodoroTime > $timeTrackingTime * 1.5) {
            return 'pomodoro';
        }

        return null; // 明確な好みなし
    }

    private function getBestStudyTimes($userId): array
    {
        // 簡単な実装：時間帯別の学習時間を分析
        return [
            'morning' => ['hours' => '6-12', 'productivity' => 'high'],
            'afternoon' => ['hours' => '12-18', 'productivity' => 'medium'],
            'evening' => ['hours' => '18-24', 'productivity' => 'low'],
        ];
    }

    private function getProductivityTrends($recentStats, $previousStats): array
    {
        $recentTotal = $recentStats['overview']['total_study_time'];
        $previousTotal = $previousStats['overview']['total_study_time'];

        $change = $previousTotal > 0 ? (($recentTotal - $previousTotal) / $previousTotal) * 100 : 0;

        return [
            'trend' => $change > 5 ? 'improving' : ($change < -5 ? 'declining' : 'stable'),
            'change_percentage' => round($change, 1),
            'recent_total' => $recentTotal,
            'previous_total' => $previousTotal,
        ];
    }

    private function generateRecommendations($userId, $stats): array
    {
        $recommendations = [];

        // 学習時間に基づく推奨
        if ($stats['overview']['total_study_time'] < 300) { // 5時間未満
            $recommendations[] = [
                'type' => 'increase_time',
                'message' => '学習時間を増やすことをお勧めします。短時間でも毎日続けることが大切です。',
                'priority' => 'high',
            ];
        }

        // 手法に基づく推奨
        $timeTrackingRate = $stats['by_method']['time_tracking']['total_duration'] / 
                           max($stats['overview']['total_study_time'], 1);

        if ($timeTrackingRate > 0.8) {
            $recommendations[] = [
                'type' => 'try_pomodoro',
                'message' => 'ポモドーロテクニックを試してみませんか？集中力向上に効果的です。',
                'priority' => 'medium',
            ];
        } elseif ($timeTrackingRate < 0.2) {
            $recommendations[] = [
                'type' => 'try_time_tracking',
                'message' => '長時間の集中が必要な学習には、自由な時間計測も効果的です。',
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }

    private function generateInsights($timeTrackingStats, $pomodoroStats): array
    {
        $insights = [];

        // 完了率の分析
        if (isset($pomodoroStats['completion_rate'])) {
            if ($pomodoroStats['completion_rate'] < 70) {
                $insights[] = 'ポモドーロセッションの完了率が低めです。時間設定を見直してみませんか？';
            } elseif ($pomodoroStats['completion_rate'] > 90) {
                $insights[] = '素晴らしいポモドーロ完了率です！この調子で続けていきましょう。';
            }
        }

        // セッション長の分析
        if ($timeTrackingStats['average_duration'] > 120) {
            $insights[] = '長時間の集中学習ができています。適度な休憩も忘れずに。';
        }

        return $insights;
    }

    // 草表示機能メソッド

    /**
     * 学習セッション完了時のデイリーサマリー更新
     */
    public function updateDailySummaryFromStudySession(StudySession $studySession): DailyStudySummary
    {
        $studyDate = $studySession->started_at->toDateString();
        
        return DB::transaction(function () use ($studySession, $studyDate) {
            $summary = DailyStudySummary::firstOrCreate([
                'user_id' => $studySession->user_id,
                'study_date' => $studyDate,
            ], [
                'total_minutes' => 0,
                'session_count' => 0,
                'study_session_minutes' => 0,
                'pomodoro_minutes' => 0,
                'total_focus_sessions' => 0,
                'grass_level' => 0,
                'subject_breakdown' => [],
            ]);

            $summary->updateFromStudySession($studySession);
            $summary->increment('session_count');
            $summary->save();

            // キャッシュクリア
            $this->clearUserGrassCache($studySession->user_id);

            return $summary;
        });
    }

    /**
     * ポモドーロセッション完了時のデイリーサマリー更新
     */
    public function updateDailySummaryFromPomodoro(PomodoroSession $pomodoroSession): DailyStudySummary
    {
        $studyDate = $pomodoroSession->started_at->toDateString();
        
        return DB::transaction(function () use ($pomodoroSession, $studyDate) {
            $summary = DailyStudySummary::firstOrCreate([
                'user_id' => $pomodoroSession->user_id,
                'study_date' => $studyDate,
            ], [
                'total_minutes' => 0,
                'session_count' => 0,
                'study_session_minutes' => 0,
                'pomodoro_minutes' => 0,
                'total_focus_sessions' => 0,
                'grass_level' => 0,
                'subject_breakdown' => [],
            ]);

            $summary->updateFromPomodoroSession($pomodoroSession);
            $summary->increment('session_count');
            $summary->save();

            // キャッシュクリア
            $this->clearUserGrassCache($pomodoroSession->user_id);

            return $summary;
        });
    }

    /**
     * 草表示データ取得（期間指定）
     */
    public function getGrassData(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subYear()->toDateString();
        $endDate = $endDate ?? Carbon::now()->toDateString();
        
        $cacheKey = "grass_data:{$userId}:{$startDate}:{$endDate}";
        
        return Cache::remember($cacheKey, now()->addHours(1), function () use ($userId, $startDate, $endDate) {
            return $this->buildGrassData($userId, $startDate, $endDate);
        });
    }

    /**
     * 草表示データの構築
     */
    private function buildGrassData(int $userId, string $startDate, string $endDate): array
    {
        // 入力検証
        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format provided');
        }
        
        if ($start > $end) {
            throw new \InvalidArgumentException('Start date cannot be after end date');
        }
        
        // 期間制限（最大1年間）
        if ($start->diffInDays($end) > 365) {
            throw new \InvalidArgumentException('Date range cannot exceed 365 days');
        }

        $summaries = DailyStudySummary::byUser($userId)
            ->dateRange($startDate, $endDate)
            ->orderBy('study_date')
            ->get()
            ->keyBy(function ($item) {
                return $item->study_date->format('Y-m-d');
            });

        $grassData = [];
        $current = $start;

        while ($current <= $end) {
            $dateKey = $current->toDateString();
            $summary = $summaries->get($dateKey);

            if ($summary) {
                $grassData[] = $summary->toGrassData();
            } else {
                $grassData[] = [
                    'date' => $dateKey,
                    'total_minutes' => 0,
                    'level' => 0,
                    'study_session_minutes' => 0,
                    'pomodoro_minutes' => 0,
                    'session_count' => 0,
                    'focus_sessions' => 0,
                ];
            }
            
            $current->addDay();
        }

        return [
            'data' => $grassData,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'stats' => $this->calculateGrassStats($grassData),
        ];
    }

    /**
     * 月別統計データ取得
     */
    public function getMonthlyStats(int $userId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $cacheKey = "monthly_stats:{$userId}:{$year}:{$month}";
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($userId, $startDate, $endDate) {
            $summaries = DailyStudySummary::byUser($userId)
                ->dateRange($startDate->toDateString(), $endDate->toDateString())
                ->get();

            $totalMinutes = $summaries->sum('total_minutes');
            $studyDays = $summaries->where('total_minutes', '>', 0)->count();
            
            return [
                'year' => $startDate->year,
                'month' => $startDate->month,
                'total_study_time' => $totalMinutes,
                'study_days' => $studyDays,
                'average_daily_time' => $studyDays > 0 ? round($totalMinutes / $studyDays, 1) : 0,
                'grass_level_distribution' => $summaries->groupBy('grass_level')->map->count(),
                'best_day' => $summaries->sortByDesc('total_minutes')->first()?->toGrassData(),
            ];
        });
    }

    /**
     * 特定日の詳細データ取得
     */
    public function getDayDetail(int $userId, string $date): array
    {
        $summary = DailyStudySummary::byUser($userId)
            ->where('study_date', $date)
            ->first();

        if (!$summary) {
            return [
                'date' => $date,
                'summary' => null,
                'sessions' => [],
                'pomodoro_sessions' => [],
            ];
        }

        // その日の学習セッション詳細
        $sessions = StudySession::byUser($userId)
            ->whereDate('started_at', $date)
            ->with('subjectArea.examType')
            ->orderBy('started_at')
            ->get();

        // その日のポモドーロセッション詳細
        $pomodoroSessions = PomodoroSession::byUser($userId)
            ->whereDate('started_at', $date)
            ->where('session_type', 'focus')
            ->where('is_completed', true)
            ->with('subjectArea.examType')
            ->orderBy('started_at')
            ->get();

        return [
            'date' => $date,
            'summary' => $summary->toGrassData(),
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'study_session',
                    'subject_area' => $session->subjectArea?->name,
                    'exam_type' => $session->subjectArea?->examType?->name,
                    'duration_minutes' => $session->duration_minutes,
                    'started_at' => $session->started_at,
                    'ended_at' => $session->ended_at,
                    'notes' => $session->study_comment,
                ];
            }),
            'pomodoro_sessions' => $pomodoroSessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'pomodoro',
                    'subject_area' => $session->subjectArea?->name,
                    'exam_type' => $session->subjectArea?->examType?->name,
                    'duration_minutes' => $session->actual_duration,
                    'started_at' => $session->started_at,
                    'completed_at' => $session->completed_at,
                    'notes' => $session->notes,
                ];
            }),
        ];
    }

    /**
     * ユーザーの草表示キャッシュクリア
     */
    public function clearUserGrassCache(int $userId): void
    {
        try {
            // Redis使用時のパターンベースクリア
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $patterns = [
                    "grass_data:{$userId}:*",
                    "monthly_stats:{$userId}:*",
                ];

                foreach ($patterns as $pattern) {
                    $keys = Cache::getRedis()->keys($pattern);
                    if (!empty($keys)) {
                        Cache::deleteMultiple($keys);
                    }
                }
            } else {
                // 他のキャッシュドライバー用の個別削除
                $dateRanges = [
                    Carbon::now()->subYear()->format('Y-m-d'),
                    Carbon::now()->format('Y-m-d')
                ];
                
                // よく使われるキャッシュキーを個別に削除
                foreach ($dateRanges as $startDate) {
                    foreach ($dateRanges as $endDate) {
                        Cache::forget("grass_data:{$userId}:{$startDate}:{$endDate}");
                    }
                }
                
                // 月別統計のキャッシュクリア
                for ($year = Carbon::now()->year - 1; $year <= Carbon::now()->year; $year++) {
                    for ($month = 1; $month <= 12; $month++) {
                        Cache::forget("monthly_stats:{$userId}:{$year}:{$month}");
                    }
                }
            }
        } catch (\Exception $e) {
            // キャッシュクリアに失敗してもメイン機能には影響させない
            \Log::warning('草表示キャッシュクリアに失敗しました:', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 草表示統計計算
     */
    private function calculateGrassStats(array $grassData): array
    {
        $totalDays = count($grassData);
        $studyDays = count(array_filter($grassData, fn($day) => $day['total_minutes'] > 0));
        $totalMinutes = array_sum(array_column($grassData, 'total_minutes'));
        
        $levelCounts = array_count_values(array_column($grassData, 'level'));
        
        return [
            'total_days' => $totalDays,
            'study_days' => $studyDays,
            'study_rate' => $totalDays > 0 ? round(($studyDays / $totalDays) * 100, 1) : 0.0,
            'total_study_time' => $totalMinutes,
            'totalHours' => round($totalMinutes / 60, 1), // フロントエンド互換
            'average_daily_time' => $studyDays > 0 ? round($totalMinutes / $studyDays, 1) : 0.0,
            'studyDays' => $studyDays, // フロントエンド互換
            'level_distribution' => [
                'level_0' => $levelCounts[0] ?? 0,
                'level_1' => $levelCounts[1] ?? 0,
                'level_2' => $levelCounts[2] ?? 0,
                'level_3' => $levelCounts[3] ?? 0,
            ],
            'longest_streak' => $this->calculateLongestStreak($grassData),
            'current_streak' => $this->calculateCurrentStreak($grassData),
            'longestStreak' => $this->calculateLongestStreak($grassData), // フロントエンド互換
            'currentStreak' => $this->calculateCurrentStreak($grassData), // フロントエンド互換
        ];
    }

    /**
     * 最長連続学習日数計算
     */
    private function calculateLongestStreak(array $grassData): int
    {
        $maxStreak = 0;
        $currentStreak = 0;
        
        foreach ($grassData as $day) {
            if ($day['total_minutes'] > 0) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }
        
        return $maxStreak;
    }

    /**
     * 現在の連続学習日数計算
     */
    private function calculateCurrentStreak(array $grassData): int
    {
        $streak = 0;
        
        // 最新の日から遡って計算
        for ($i = count($grassData) - 1; $i >= 0; $i--) {
            if ($grassData[$i]['total_minutes'] > 0) {
                $streak++;
            } else {
                break;
            }
        }
        
        return $streak;
    }
}