<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudyActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    protected StudyActivityService $studyActivityService;

    public function __construct(StudyActivityService $studyActivityService)
    {
        $this->studyActivityService = $studyActivityService;
    }

    /**
     * 統合学習履歴を取得
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $limit = $request->get('limit', 50);

            $history = $this->studyActivityService->getUnifiedHistory(
                $user->id,
                $startDate,
                $endDate,
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => $history->toArray(),
            ]);

        } catch (\Exception $e) {
            \Log::error('統合履歴取得エラー: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '履歴の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 統合学習統計を取得
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $stats = $this->studyActivityService->getUnifiedStats(
                $user->id,
                $startDate,
                $endDate
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            \Log::error('統合統計取得エラー: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '統計の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 学習パターン分析とインサイトを取得
     */
    public function insights(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $insights = $this->studyActivityService->getStudyInsights($user->id);

            return response()->json([
                'success' => true,
                'data' => $insights,
            ]);

        } catch (\Exception $e) {
            \Log::error('インサイト取得エラー: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'インサイトの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 学習手法の推奨を取得
     */
    public function suggest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject_area_id' => 'nullable|exists:subject_areas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            $subjectAreaId = $request->get('subject_area_id');

            $suggestion = $this->studyActivityService->suggestStudyMethod(
                $user->id,
                $subjectAreaId
            );

            return response()->json([
                'success' => true,
                'data' => $suggestion,
            ]);

        } catch (\Exception $e) {
            \Log::error('推奨取得エラー: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '推奨の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * 期間比較分析
     */
    public function comparison(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period1_start' => 'required|date',
            'period1_end' => 'required|date|after_or_equal:period1_start',
            'period2_start' => 'required|date',
            'period2_end' => 'required|date|after_or_equal:period2_start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();

            $period1Stats = $this->studyActivityService->getUnifiedStats(
                $user->id,
                $request->get('period1_start'),
                $request->get('period1_end')
            );

            $period2Stats = $this->studyActivityService->getUnifiedStats(
                $user->id,
                $request->get('period2_start'),
                $request->get('period2_end')
            );

            // 変化率計算
            $period1Total = $period1Stats['overview']['total_study_time'];
            $period2Total = $period2Stats['overview']['total_study_time'];

            $totalTimeChange = $period2Total > 0
                ? (($period1Total - $period2Total) / $period2Total) * 100
                : 0;

            $period1Sessions = $period1Stats['overview']['total_sessions'];
            $period2Sessions = $period2Stats['overview']['total_sessions'];

            $sessionCountChange = $period2Sessions > 0
                ? (($period1Sessions - $period2Sessions) / $period2Sessions) * 100
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'period1' => $period1Stats,
                    'period2' => $period2Stats,
                    'changes' => [
                        'total_study_time_change' => round($totalTimeChange, 1),
                        'session_count_change' => round($sessionCountChange, 1),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('比較分析エラー: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '比較分析の取得に失敗しました',
            ], 500);
        }
    }
}
