<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingAnalyticsRequest;
use App\Http\Requests\OnboardingCompleteRequest;
use App\Http\Requests\OnboardingProgressRequest;
use App\Http\Requests\OnboardingSkipRequest;
use App\Models\ExamType;
use App\Models\StudyGoal;
use App\Models\SubjectArea;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                $validated['step_data'] ?? [],
                $request->userAgent(),
                $request->ip()
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

            DB::beginTransaction();

            // step_dataの処理
            $setupComplete = false;
            if (!empty($validated['step_data']['setup_step'])) {
                $setupData = $this->extractSetupStepData($validated['step_data']['setup_step']);
                $examType = $this->processExamType($user, $setupData);
                $this->createStudyGoal($user, $examType, $setupData);
                $this->createSubjectAreas($user, $examType, $setupData);
                $setupComplete = true;
            }

            // 完了処理
            $user->completeOnboarding(
                [
                    'total_time_spent' => $validated['total_time_spent'] ?? 0,
                    'step_times' => $validated['step_times'] ?? [],
                    'feedback' => $validated['feedback'] ?? null,
                    'completion_source' => 'web_app',
                ],
                $request->userAgent(),
                $request->ip()
            );

            DB::commit();
            $user->refresh();

            $responseData = [
                'completed_at' => $user->onboarding_completed_at->toISOString(),
                'stats' => $user->getOnboardingStats(),
            ];

            if ($setupComplete) {
                $responseData['setup_complete'] = true;
            }

            return $this->successResponse($responseData, $request, 'オンボーディングが完了しました');

        } catch (\Exception $e) {
            DB::rollBack();
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
                $validated['reason'] ?? 'user_choice',
                $request->userAgent(),
                $request->ip()
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

    /**
     * セットアップステップデータの抽出
     */
    private function extractSetupStepData(array $setupData): array
    {
        return [
            'exam_type' => $setupData['exam_type'] ?? null,
            'exam_date' => $setupData['exam_date'] ?? null,
            'daily_goal_minutes' => $setupData['daily_goal_minutes'] ?? null,
            'custom_exam_name' => $setupData['custom_exam_name'] ?? null,
            'custom_exam_description' => $setupData['custom_exam_description'] ?? null,
            'custom_exam_color' => $setupData['custom_exam_color'] ?? null,
            'custom_exam_notes' => $setupData['custom_exam_notes'] ?? null,
            'custom_exam_subjects' => $setupData['custom_exam_subjects'] ?? [],
            'custom_subjects' => $setupData['custom_subjects'] ?? [],
        ];
    }

    /**
     * 試験タイプの処理（作成・取得）
     */
    private function processExamType(User $user, array $setupData): ExamType
    {
        if ($setupData['exam_type'] === 'custom') {
            return $this->createCustomExamType($user, $setupData);
        } else {
            return $this->createSystemExamType($user, $setupData);
        }
    }

    /**
     * カスタム試験タイプの作成
     */
    private function createCustomExamType(User $user, array $setupData): ExamType
    {
        $examCode = $this->generateExamCode($user->id, $setupData['custom_exam_name']);

        return ExamType::create([
            'user_id' => $user->id,
            'code' => $examCode,
            'name' => $setupData['custom_exam_name'],
            'description' => $setupData['custom_exam_description'],
            'exam_date' => $setupData['exam_date'],
            'color' => $setupData['custom_exam_color'] ?? '#9333EA',
            'exam_notes' => $setupData['custom_exam_notes'],
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * システム試験タイプのユーザー固有インスタンス作成
     */
    private function createSystemExamType(User $user, array $setupData): ExamType
    {
        $examTypes = config('exams.types', []);
        $examInfo = $examTypes[$setupData['exam_type']] ?? [
            'name' => $setupData['exam_type'], 
            'description' => '',
            'color' => '#3B82F6'
        ];

        return ExamType::create([
            'user_id' => $user->id,
            'code' => $setupData['exam_type'],
            'name' => $examInfo['name'],
            'description' => $examInfo['description'],
            'exam_date' => $setupData['exam_date'],
            'color' => $examInfo['color'],
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * 学習目標の作成
     */
    private function createStudyGoal(User $user, ExamType $examType, array $setupData): void
    {
        StudyGoal::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        StudyGoal::create([
            'user_id' => $user->id,
            'exam_type_id' => $examType->id,
            'daily_minutes_goal' => $setupData['daily_goal_minutes'],
            'exam_date' => $setupData['exam_date'],
            'is_active' => true,
        ]);
    }

    /**
     * 学習分野の作成
     */
    private function createSubjectAreas(User $user, ExamType $examType, array $setupData): void
    {
        $subjectsToCreate = [];

        // カスタム試験の場合
        if ($setupData['exam_type'] === 'custom' && !empty($setupData['custom_exam_subjects'])) {
            $subjectsToCreate = $setupData['custom_exam_subjects'];
        }
        // 既定試験でカスタム学習分野がある場合
        elseif ($setupData['exam_type'] !== 'custom' && !empty($setupData['custom_subjects'])) {
            $subjectsToCreate = $setupData['custom_subjects'];
        }

        // 学習分野を作成
        foreach ($subjectsToCreate as $subject) {
            if (!empty($subject['name'])) {
                $subjectCode = $this->generateSubjectCode($subject['name'], $user->id);
                
                SubjectArea::create([
                    'user_id' => $user->id,
                    'exam_type_id' => $examType->id,
                    'code' => $subjectCode,
                    'name' => $subject['name'],
                    'is_system' => false,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }
        }
    }

    /**
     * 学習分野コードの生成
     */
    private function generateSubjectCode(string $name, int $userId): string
    {
        $maxLength = config('exams.validation.exam_code_base_length', 10);
        
        // ベースコードの生成
        $baseCode = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        $baseCode = strtolower(substr($baseCode, 0, $maxLength));
        
        // 空の場合のフォールバック
        if (empty($baseCode)) {
            $baseCode = 'subject';
        }

        $uniqueCode = $baseCode.'_'.$userId.'_'.time();

        // 重複チェックと生成（最大10回試行）
        $counter = 1;
        $finalCode = $uniqueCode;
        while (SubjectArea::where('code', $finalCode)->exists() && $counter <= 10) {
            $finalCode = $uniqueCode.'_'.$counter;
            $counter++;
        }

        // 10回試行しても重複する場合は例外をスロー
        if (SubjectArea::where('code', $finalCode)->exists()) {
            throw new \RuntimeException('学習分野コードの生成に失敗しました。しばらく時間をおいて再試行してください。');
        }

        return $finalCode;
    }

    /**
     * 試験コードの生成
     */
    private function generateExamCode(int $userId, string $examName): string
    {
        $maxLength = config('exams.validation.exam_code_base_length', 10);
        
        // ベースコードの生成
        $baseCode = strtolower(str_replace([' ', '　', '-', '_'], '', $examName));
        $baseCode = preg_replace('/[^a-z0-9]/', '', $baseCode);
        $baseCode = substr($baseCode, 0, $maxLength);
        
        // 空の場合のフォールバック
        if (empty($baseCode)) {
            $baseCode = 'custom';
        }
        
        $timestamp = time();
        $randomSuffix = mt_rand(1000, 9999);
        $candidateCode = $baseCode . '_u' . $userId . '_' . $timestamp . '_' . $randomSuffix;
        
        // 重複チェックと生成（最大10回試行）
        $counter = 1;
        $finalCode = $candidateCode;
        while (ExamType::where('code', $finalCode)->exists() && $counter <= 10) {
            $finalCode = $candidateCode . '_' . $counter;
            $counter++;
        }
        
        // 10回試行しても重複する場合は例外をスロー
        if (ExamType::where('code', $finalCode)->exists()) {
            throw new \RuntimeException('試験コードの生成に失敗しました。しばらく時間をおいて再試行してください。');
        }
        
        return $finalCode;
    }
}
