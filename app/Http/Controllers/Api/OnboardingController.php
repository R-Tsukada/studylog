<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingAnalyticsRequest;
use App\Http\Requests\OnboardingCompleteRequest;
use App\Http\Requests\OnboardingProgressRequest;
use App\Http\Requests\OnboardingSkipRequest;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService
    ) {}

    /**
     * オンボーディング状態取得
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // ログイン回数を増加（セッション毎に1回のみ）
            if (! session()->has('login_counted_'.$user->id)) {
                $user->incrementLoginCount();
                session()->put('login_counted_'.$user->id, true);
            }

            $shouldShow = $user->shouldShowOnboarding();
            $stats = $user->getOnboardingStats();

            return $this->successResponse([
                'should_show' => $shouldShow,
                'completed_at' => $user->onboarding_completed_at?->toISOString(),
                'progress' => $user->onboarding_progress,
                'skipped' => (bool) $user->onboarding_skipped,
                'stats' => $stats,
            ], $request);

        } catch (\Exception $e) {
            return $this->errorResponse('状態取得中にエラーが発生しました', $e, $request);
        }
    }

    /**
     * オンボーディング進捗更新
     */
    public function updateProgress(OnboardingProgressRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user();

            // 安全な進捗更新（トランザクション付き）
            $this->onboardingService->updateUserProgressSafely(
                $user,
                $validated['current_step'],
                $validated['completed_steps'] ?? [],
                $validated['step_data'] ?? []
            );

            return $this->successResponse([
                'current_step' => $validated['current_step'],
                'completed_steps' => $validated['completed_steps'] ?? [],
            ], $request, '進捗を更新しました');

        } catch (\Exception $e) {
            return $this->errorResponse('進捗更新中にエラーが発生しました', $e, $request);
        }
    }

    /**
     * オンボーディング完了
     */
    public function complete(OnboardingCompleteRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user();

            // 完了処理
            $user->completeOnboarding([
                'total_time_spent' => $validated['total_time_spent'] ?? 0,
                'step_times' => $validated['step_times'] ?? [],
                'feedback' => $validated['feedback'] ?? null,
                'completion_source' => 'web_app',
            ]);

            $user->refresh();

            return $this->successResponse([
                'completed_at' => $user->onboarding_completed_at->toISOString(),
                'stats' => $user->getOnboardingStats(),
            ], $request, 'オンボーディングが完了しました');

        } catch (\Exception $e) {
            return $this->errorResponse('完了処理中にエラーが発生しました', $e, $request);
        }
    }

    /**
     * オンボーディングスキップ
     */
    public function skip(OnboardingSkipRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user();

            // スキップ処理
            $user->skipOnboarding(
                $validated['current_step'] ?? null,
                $validated['reason'] ?? 'user_choice'
            );

            $user->refresh();

            return $this->successResponse([
                'skipped_at' => $user->onboarding_completed_at->toISOString(),
                'skipped_step' => $validated['current_step'] ?? null,
            ], $request, 'オンボーディングをスキップしました');

        } catch (\Exception $e) {
            return $this->errorResponse('スキップ処理中にエラーが発生しました', $e, $request);
        }
    }

    /**
     * オンボーディング統計取得（管理者用）
     */
    public function analytics(OnboardingAnalyticsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $analytics = $this->onboardingService->getAnalytics(
                $validated['start_date'],
                $validated['end_date'],
                $validated['group_by'] ?? null,
                $validated['limit'] ?? null
            );

            return $this->successResponse($analytics, $request);

        } catch (\Exception $e) {
            return $this->errorResponse('統計取得中にエラーが発生しました', $e, $request);
        }
    }

    /**
     * 成功レスポンス生成
     */
    private function successResponse(array $data, Request $request, ?string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toISOString(),
                'requestId' => $request->header('X-Request-ID', uniqid()),
                'version' => config('onboarding.version', '1.0'),
            ],
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }

    /**
     * エラーレスポンス生成
     */
    private function errorResponse(string $message, \Exception $e, Request $request, int $statusCode = 500): JsonResponse
    {
        // ログ記録
        logger()->error('Onboarding API Error', [
            'message' => $message,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $request->user()?->id,
            'request_data' => $request->except(['password', 'token']),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => app()->environment('production') ? null : $e->getMessage(),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'requestId' => $request->header('X-Request-ID', uniqid()),
                'version' => config('onboarding.version', '1.0'),
            ],
        ], $statusCode);
    }
}
