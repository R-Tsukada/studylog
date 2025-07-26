<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    /**
     * オンボーディング状態取得
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // ログイン回数を増加（セッション毎に1回のみ）
            if (!session()->has('login_counted_' . $user->id)) {
                $user->incrementLoginCount();
                session()->put('login_counted_' . $user->id, true);
            }
            
            $shouldShow = $user->shouldShowOnboarding();
            $stats = $user->getOnboardingStats();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'should_show' => $shouldShow,
                    'completed_at' => $user->onboarding_completed_at?->toISOString(),
                    'progress' => $user->onboarding_progress,
                    'skipped' => (bool) $user->onboarding_skipped,
                    'stats' => $stats
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse('状態取得中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディング進捗更新
     */
    public function updateProgress(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_step' => 'required|integer|min:1|max:4',
                'completed_steps' => 'array',
                'completed_steps.*' => 'integer|min:1|max:4',
                'step_data' => 'array',
                'timestamp' => 'string|date_format:Y-m-d\TH:i:s\Z'
            ]);
            
            $user = $request->user();
            
            // 進捗更新
            $user->updateOnboardingProgress(
                $validated['current_step'],
                $validated['completed_steps'] ?? [],
                $validated['step_data'] ?? []
            );
            
            return response()->json([
                'success' => true,
                'message' => '進捗を更新しました',
                'data' => [
                    'current_step' => $validated['current_step'],
                    'completed_steps' => $validated['completed_steps'] ?? []
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('進捗更新中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディング完了
     */
    public function complete(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'completed_steps' => 'array',
                'completed_steps.*' => 'integer|min:1|max:4',
                'total_time_spent' => 'integer|min:0',
                'step_times' => 'array',
                'feedback' => 'string|max:1000'
            ]);
            
            $user = $request->user();
            
            // 完了処理
            $user->completeOnboarding([
                'total_time_spent' => $validated['total_time_spent'] ?? 0,
                'step_times' => $validated['step_times'] ?? [],
                'feedback' => $validated['feedback'] ?? null,
                'completion_source' => 'web_app'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'オンボーディングが完了しました',
                'data' => [
                    'completed_at' => $user->fresh()->onboarding_completed_at->toISOString(),
                    'stats' => $user->getOnboardingStats()
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('完了処理中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディングスキップ
     */
    public function skip(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_step' => 'integer|min:1|max:4',
                'reason' => 'string|max:100',
                'completed_steps' => 'array',
                'completed_steps.*' => 'integer|min:1|max:4'
            ]);
            
            $user = $request->user();
            
            // スキップ処理
            $user->skipOnboarding(
                $validated['current_step'] ?? null,
                $validated['reason'] ?? 'user_choice'
            );
            
            return response()->json([
                'success' => true,
                'message' => 'オンボーディングをスキップしました',
                'data' => [
                    'skipped_at' => $user->fresh()->onboarding_completed_at->toISOString(),
                    'skipped_step' => $validated['current_step'] ?? null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('スキップ処理中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディング統計取得（管理者用）
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'group_by' => 'string|in:day,week,month'
            ]);
            
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            
            // 基本統計
            $completionRate = OnboardingLog::getCompletionRate($startDate, $endDate);
            
            // ステップ別完了率
            $stepCompletions = OnboardingLog::ofType(OnboardingLog::EVENT_STEP_COMPLETED)
                ->inPeriod($startDate, $endDate)
                ->selectRaw('step_number, COUNT(*) as completions')
                ->groupBy('step_number')
                ->orderBy('step_number')
                ->get();
            
            // 日別統計
            $dailyStats = OnboardingLog::inPeriod($startDate, $endDate)
                ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
                ->groupBy('date', 'event_type')
                ->orderBy('date')
                ->get()
                ->groupBy('date');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'completion_rate' => $completionRate,
                    'step_completions' => $stepCompletions,
                    'daily_stats' => $dailyStats,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('統計取得中にエラーが発生しました', $e);
        }
    }
    
    /**
     * エラーレスポンス生成
     */
    private function errorResponse(string $message, \Exception $e, int $statusCode = 500): JsonResponse
    {
        // ログ記録
        logger()->error('Onboarding API Error', [
            'message' => $message,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => app()->environment('production') ? null : $e->getMessage(),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'requestId' => request()->header('X-Request-ID', uniqid()),
                'version' => '1.0'
            ]
        ], $statusCode);
    }
}