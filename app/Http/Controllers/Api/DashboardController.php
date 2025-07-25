<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyGoal;
use App\Models\StudySession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * ダッシュボードデータを取得
     */
    public function index(): JsonResponse
    {
        try {
            $userId = auth()->id();

            $todayTotalTime = $this->getTotalTodayStudyTime($userId);

            $dashboardData = [
                'continuous_days' => $this->calculateContinuousDays($userId),
                'today_study_time' => $todayTotalTime['formatted_time'],
                'today_study_details' => $todayTotalTime, // 詳細情報
                'today_session_count' => $this->getTodaySessionCount($userId),
                'achievement_rate' => $this->calculateAchievementRate($userId),
                'this_week_total' => $this->getThisWeekTotal($userId),
                'this_month_total' => $this->getThisMonthTotal($userId),
                'recent_subjects' => $this->getRecentSubjects($userId),
                'active_goals' => $this->getActiveGoals($userId),
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ダッシュボードデータの取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 連続学習日数を計算
     */
    private function calculateContinuousDays(int $userId): int
    {
        $continuousDays = 0;
        $currentDate = today();

        // 今日から過去に向かって、学習記録がある日を数える
        while (true) {
            $hasStudyOnDate = StudySession::completed()
                ->forUser($userId)
                ->whereDate('started_at', $currentDate)
                ->exists();

            if ($hasStudyOnDate) {
                $continuousDays++;
                $currentDate = $currentDate->subDay();
            } else {
                // 今日に学習記録がない場合は、昨日から開始
                if ($continuousDays === 0 && $currentDate->isToday()) {
                    $currentDate = $currentDate->subDay();

                    continue;
                }
                break;
            }

            // 無限ループ防止（最大1年分まで）
            if ($continuousDays >= 365) {
                break;
            }
        }

        return $continuousDays;
    }

    /**
     * 今日の学習時間を取得（分）- 学習セッションのみ
     */
    private function getTodayStudyTime(int $userId): int
    {
        return StudySession::completed()
            ->forUser($userId)
            ->today()
            ->sum('duration_minutes');
    }

    /**
     * 今日の合計学習時間を取得（学習セッション＋ポモドーロ合算）
     */
    private function getTotalTodayStudyTime(int $userId): array
    {
        // 学習セッションの時間
        $studySessionTime = $this->getTodayStudyTime($userId);

        // ポモドーロセッションの時間（完了したfocusセッションのみ）
        $pomodoroTime = \App\Models\PomodoroSession::where('user_id', $userId)
            ->where('session_type', 'focus')
            ->where('is_completed', true)
            ->whereDate('started_at', today())
            ->sum('actual_duration');

        $totalTime = $studySessionTime + $pomodoroTime;

        return [
            'total_minutes' => $totalTime,
            'study_session_minutes' => $studySessionTime,
            'pomodoro_minutes' => $pomodoroTime,
            'formatted_time' => $this->formatMinutesToHours($totalTime),
        ];
    }

    /**
     * 今日の学習セッション回数を取得
     */
    private function getTodaySessionCount(int $userId): int
    {
        return StudySession::completed()
            ->forUser($userId)
            ->today()
            ->count();
    }

    /**
     * 目標達成率を計算（日次目標ベース - 学習セッション＋ポモドーロ合算）
     */
    private function calculateAchievementRate(int $userId): int
    {
        // アクティブな学習目標を取得
        $activeGoal = StudyGoal::where('user_id', $userId)
            ->where('is_active', true)
            ->whereNotNull('daily_minutes_goal')
            ->first();

        if (! $activeGoal || $activeGoal->daily_minutes_goal <= 0) {
            return 0; // 目標が設定されていない場合
        }

        // 学習セッションの今日の合計時間
        $studySessionTime = $this->getTodayStudyTime($userId);

        // ポモドーロセッションの今日の合計時間（完了したfocusセッションのみ）
        $pomodoroTime = \App\Models\PomodoroSession::where('user_id', $userId)
            ->where('session_type', 'focus')
            ->where('is_completed', true)
            ->whereDate('started_at', today())
            ->sum('actual_duration');

        // 両方の時間を合算
        $totalTodayTime = $studySessionTime + $pomodoroTime;
        $achievementRate = ($totalTodayTime / $activeGoal->daily_minutes_goal) * 100;

        return min(100, round($achievementRate)); // 100%を上限とする
    }

    /**
     * 今週の合計学習時間を取得
     */
    private function getThisWeekTotal(int $userId): array
    {
        $totalMinutes = StudySession::completed()
            ->forUser($userId)
            ->thisWeek()
            ->sum('duration_minutes');

        $sessionCount = StudySession::completed()
            ->forUser($userId)
            ->thisWeek()
            ->count();

        return [
            'total_minutes' => $totalMinutes,
            'session_count' => $sessionCount,
            'formatted_time' => $this->formatMinutesToHours($totalMinutes),
        ];
    }

    /**
     * 今月の合計学習時間を取得
     */
    private function getThisMonthTotal(int $userId): array
    {
        $totalMinutes = StudySession::completed()
            ->forUser($userId)
            ->thisMonth()
            ->sum('duration_minutes');

        $sessionCount = StudySession::completed()
            ->forUser($userId)
            ->thisMonth()
            ->count();

        return [
            'total_minutes' => $totalMinutes,
            'session_count' => $sessionCount,
            'formatted_time' => $this->formatMinutesToHours($totalMinutes),
        ];
    }

    /**
     * 最近学習した分野を取得（学習セッション＋ポモドーロ統合）
     */
    private function getRecentSubjects(int $userId): array
    {
        // 学習セッション取得
        $studySessions = StudySession::completed()
            ->forUser($userId)
            ->with('subjectArea.examType')
            ->orderBy('started_at', 'desc')
            ->limit(10) // 多めに取得して後でフィルタ
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'study_session',
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at,
                    'last_studied_at' => $session->started_at->format('Y-m-d'),
                    'duration_minutes' => $session->duration_minutes,
                    'notes' => $session->study_comment, // 学習セッションのコメントをnotesとして統一
                ];
            });

        // ポモドーロセッション取得（完了したfocusセッションのみ）
        $pomodoroSessions = \App\Models\PomodoroSession::where('user_id', $userId)
            ->where('session_type', 'focus')
            ->where('is_completed', true)
            ->with('subjectArea.examType') // リレーションも取得
            ->orderBy('started_at', 'desc')
            ->limit(10) // 多めに取得して後でフィルタ
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'type' => 'pomodoro_session',
                    'subject_area_name' => $session->subjectArea ? $session->subjectArea->name : 'ポモドーロ学習',
                    'exam_type_name' => $session->subjectArea && $session->subjectArea->examType ? $session->subjectArea->examType->name : null,
                    'started_at' => $session->started_at,
                    'last_studied_at' => $session->started_at->format('Y-m-d'),
                    'duration_minutes' => $session->actual_duration,
                    'notes' => $session->notes, // メモを追加
                ];
            });

        // 両方を統合して時系列でソート
        $allSessions = $studySessions->concat($pomodoroSessions)
            ->sortByDesc('started_at')
            ->take(5) // 最新5件を取得
            ->values();

        return $allSessions->toArray();
    }

    /**
     * アクティブな学習目標を取得
     */
    private function getActiveGoals(int $userId): array
    {
        $goals = StudyGoal::where('user_id', $userId)
            ->where('is_active', true)
            ->with('examType')
            ->get();

        return $goals->map(function ($goal) {
            $daysUntilExam = null;
            if ($goal->exam_date) {
                $daysUntilExam = today()->diffInDays(Carbon::parse($goal->exam_date), false);
                if ($daysUntilExam < 0) {
                    $daysUntilExam = null; // 過去の試験日は表示しない
                }
            }

            return [
                'exam_type_name' => $goal->examType->name,
                'daily_minutes_goal' => $goal->daily_minutes_goal,
                'weekly_minutes_goal' => $goal->weekly_minutes_goal,
                'exam_date' => $goal->exam_date,
                'days_until_exam' => $daysUntilExam,
            ];
        })->toArray();
    }

    /**
     * 分を時間形式にフォーマット
     */
    private function formatMinutesToHours(int $minutes): string
    {
        if ($minutes === 0) {
            return '0分';
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}時間{$remainingMinutes}分";
        } elseif ($hours > 0) {
            return "{$hours}時間";
        } else {
            return "{$remainingMinutes}分";
        }
    }

    /**
     * 詳細統計データを取得（将来の拡張用）
     */
    public function statistics(): JsonResponse
    {
        try {
            $userId = auth()->id();

            $stats = [
                'daily_breakdown' => $this->getDailyBreakdown($userId),
                'subject_breakdown' => $this->getSubjectBreakdown($userId),
                'weekly_trend' => $this->getWeeklyTrend($userId),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '統計データの取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 過去7日間の日別学習時間を取得
     */
    private function getDailyBreakdown(int $userId): array
    {
        $breakdown = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $minutes = StudySession::completed()
                ->forUser($userId)
                ->whereDate('started_at', $date)
                ->sum('duration_minutes');

            $breakdown[] = [
                'date' => $date->format('Y-m-d'),
                'minutes' => $minutes,
                'formatted_time' => $this->formatMinutesToHours($minutes),
            ];
        }

        return $breakdown;
    }

    /**
     * 分野別学習時間を取得
     */
    private function getSubjectBreakdown(int $userId): array
    {
        $sessions = StudySession::completed()
            ->forUser($userId)
            ->with('subjectArea')
            ->whereDate('started_at', '>=', today()->subDays(30))
            ->get();

        $breakdown = $sessions->groupBy('subjectArea.name')
            ->map(function ($sessions, $subjectName) {
                $totalMinutes = $sessions->sum('duration_minutes');

                return [
                    'subject_name' => $subjectName,
                    'total_minutes' => $totalMinutes,
                    'session_count' => $sessions->count(),
                    'formatted_time' => $this->formatMinutesToHours($totalMinutes),
                ];
            })->values()
            ->sortByDesc('total_minutes')
            ->toArray();

        return $breakdown;
    }

    /**
     * 過去4週間の週別トレンドを取得
     */
    private function getWeeklyTrend(int $userId): array
    {
        $trend = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = today()->subWeeks($i)->startOfWeek();
            $weekEnd = today()->subWeeks($i)->endOfWeek();

            $minutes = StudySession::completed()
                ->forUser($userId)
                ->whereBetween('started_at', [$weekStart, $weekEnd])
                ->sum('duration_minutes');

            $trend[] = [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'minutes' => $minutes,
                'formatted_time' => $this->formatMinutesToHours($minutes),
            ];
        }

        return $trend;
    }
}
