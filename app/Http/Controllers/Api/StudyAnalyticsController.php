<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudyActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StudyAnalyticsController extends Controller
{
    public function __construct(
        private StudyActivityService $studyActivityService
    ) {}

    /**
     * 統合学習履歴を取得
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $user = Auth::user();
            
            $history = $this->studyActivityService->getUnifiedHistory(
                $user->id,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
                $validated['limit'] ?? 50
            );

            return response()->json([
                'success' => true,
                'data' => $history,
                'meta' => [
                    'total_count' => $history->count(),
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データが正しくありません。',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('統合学習履歴取得エラー:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '学習履歴の取得中にエラーが発生しました。',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 統合学習統計を取得
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $user = Auth::user();
            
            $stats = $this->studyActivityService->getUnifiedStats(
                $user->id,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データが正しくありません。',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('統合学習統計取得エラー:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '学習統計の取得中にエラーが発生しました。',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習インサイトを取得
     */
    public function insights(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $insights = $this->studyActivityService->getStudyInsights($user->id);

            return response()->json([
                'success' => true,
                'data' => $insights
            ]);

        } catch (\Exception $e) {
            \Log::error('学習インサイト取得エラー:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '学習インサイトの取得中にエラーが発生しました。',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習手法の推奨を取得
     */
    public function suggest(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subject_area_id' => 'nullable|exists:subject_areas,id',
            ]);

            $user = Auth::user();
            
            $suggestion = $this->studyActivityService->suggestStudyMethod(
                $user->id,
                $validated['subject_area_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $suggestion
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データが正しくありません。',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('学習手法推奨取得エラー:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '学習手法推奨の取得中にエラーが発生しました。',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習効率の比較分析
     */
    public function comparison(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period1_start' => 'required|date',
                'period1_end' => 'required|date|after_or_equal:period1_start',
                'period2_start' => 'required|date',
                'period2_end' => 'required|date|after_or_equal:period2_start',
            ]);

            $user = Auth::user();
            
            $period1Stats = $this->studyActivityService->getUnifiedStats(
                $user->id,
                $validated['period1_start'],
                $validated['period1_end']
            );

            $period2Stats = $this->studyActivityService->getUnifiedStats(
                $user->id,
                $validated['period2_start'],
                $validated['period2_end']
            );

            // 比較分析
            $comparison = [
                'period1' => $period1Stats,
                'period2' => $period2Stats,
                'changes' => [
                    'total_study_time_change' => $period1Stats['overview']['total_study_time'] - $period2Stats['overview']['total_study_time'],
                    'session_count_change' => $period1Stats['overview']['total_sessions'] - $period2Stats['overview']['total_sessions'],
                    'average_session_change' => $period1Stats['overview']['average_session_length'] - $period2Stats['overview']['average_session_length'],
                    'study_days_change' => $period1Stats['overview']['study_days'] - $period2Stats['overview']['study_days'],
                ],
                'improvement_areas' => $this->identifyImprovementAreas($period1Stats, $period2Stats),
            ];

            return response()->json([
                'success' => true,
                'data' => $comparison
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データが正しくありません。',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('学習比較分析エラー:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '学習比較分析中にエラーが発生しました。',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 改善領域を特定
     */
    private function identifyImprovementAreas($period1Stats, $period2Stats): array
    {
        $areas = [];

        // 学習時間の変化
        $timeChange = $period1Stats['overview']['total_study_time'] - $period2Stats['overview']['total_study_time'];
        if ($timeChange < -60) { // 1時間以上減少
            $areas[] = [
                'area' => 'study_time',
                'message' => '学習時間が減少しています。定期的な学習習慣を見直してみませんか？',
                'severity' => 'high'
            ];
        } elseif ($timeChange > 60) { // 1時間以上増加
            $areas[] = [
                'area' => 'study_time',
                'message' => '学習時間が増加しています！この調子で続けていきましょう。',
                'severity' => 'positive'
            ];
        }

        // ポモドーロ完了率の変化
        $p1PomodoroRate = $period1Stats['by_method']['pomodoro']['completion_rate'] ?? 0;
        $p2PomodoroRate = $period2Stats['by_method']['pomodoro']['completion_rate'] ?? 0;
        
        if ($p1PomodoroRate > 0 && $p2PomodoroRate > 0) {
            $rateChange = $p1PomodoroRate - $p2PomodoroRate;
            if ($rateChange < -10) {
                $areas[] = [
                    'area' => 'pomodoro_completion',
                    'message' => 'ポモドーロの完了率が下がっています。時間設定を調整してみませんか？',
                    'severity' => 'medium'
                ];
            }
        }

        return $areas;
    }
}