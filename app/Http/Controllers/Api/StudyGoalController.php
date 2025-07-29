<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use App\Models\StudyGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudyGoalController extends Controller
{
    /**
     * ユーザーの学習目標一覧を取得
     */
    public function index(): JsonResponse
    {
        try {
            $goals = StudyGoal::forUser(auth()->id())
                ->with('examType')
                ->orderBy('is_active', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'goals' => $goals->map(function ($goal) {
                    return [
                        'id' => $goal->id,
                        'exam_type_id' => $goal->exam_type_id,
                        'exam_type_name' => $goal->examType->name ?? null,
                        'daily_minutes_goal' => $goal->daily_minutes_goal,
                        'weekly_minutes_goal' => $goal->weekly_minutes_goal,
                        'exam_date' => $goal->exam_date?->format('Y-m-d'),
                        'is_active' => $goal->is_active,
                        'created_at' => $goal->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習目標の取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 新しい学習目標を作成
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'exam_type_id' => 'nullable|exists:exam_types,id',
                'daily_minutes_goal' => 'required|integer|min:1|max:1440', // 最大24時間
                'weekly_minutes_goal' => 'nullable|integer|min:1|max:10080', // 最大7日×24時間
                'exam_date' => 'nullable|date|after:today',
                'is_active' => 'boolean',
            ]);

            // セキュリティチェック：ExamType所有権の検証
            if (isset($validated['exam_type_id'])) {
                if (!$this->validateExamTypeOwnership($validated['exam_type_id'], auth()->id())) {
                    return response()->json([
                        'success' => false,
                        'message' => '指定された試験タイプへのアクセス権限がありません',
                    ], 403);
                }
            }

            // アクティブな目標が作成される場合、他のアクティブな目標を無効化
            if ($validated['is_active'] ?? true) {
                StudyGoal::forUser(auth()->id())
                    ->active()
                    ->update(['is_active' => false]);
            }

            $goal = StudyGoal::create([
                'user_id' => auth()->id(),
                'exam_type_id' => $validated['exam_type_id'] ?? null,
                'daily_minutes_goal' => $validated['daily_minutes_goal'],
                'weekly_minutes_goal' => $validated['weekly_minutes_goal'] ?? null,
                'exam_date' => $validated['exam_date'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $goal->load('examType');

            return response()->json([
                'success' => true,
                'message' => '学習目標を設定しました',
                'goal' => [
                    'id' => $goal->id,
                    'exam_type_id' => $goal->exam_type_id,
                    'exam_type_name' => $goal->examType->name ?? null,
                    'daily_minutes_goal' => $goal->daily_minutes_goal,
                    'weekly_minutes_goal' => $goal->weekly_minutes_goal,
                    'exam_date' => $goal->exam_date?->format('Y-m-d'),
                    'is_active' => $goal->is_active,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データに問題があります',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習目標の作成中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習目標の詳細を取得
     */
    public function show(StudyGoal $goal): JsonResponse
    {
        try {
            // 自分の目標のみアクセス可能
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'アクセス権限がありません',
                ], 403);
            }

            $goal->load('examType');

            return response()->json([
                'success' => true,
                'goal' => [
                    'id' => $goal->id,
                    'exam_type_id' => $goal->exam_type_id,
                    'exam_type_name' => $goal->examType->name ?? null,
                    'daily_minutes_goal' => $goal->daily_minutes_goal,
                    'weekly_minutes_goal' => $goal->weekly_minutes_goal,
                    'exam_date' => $goal->exam_date?->format('Y-m-d'),
                    'is_active' => $goal->is_active,
                    'created_at' => $goal->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習目標の取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習目標を更新
     */
    public function update(Request $request, StudyGoal $goal): JsonResponse
    {
        try {
            // 自分の目標のみアクセス可能
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'アクセス権限がありません',
                ], 403);
            }

            $validated = $request->validate([
                'exam_type_id' => 'nullable|exists:exam_types,id',
                'daily_minutes_goal' => 'required|integer|min:1|max:1440',
                'weekly_minutes_goal' => 'nullable|integer|min:1|max:10080',
                'exam_date' => 'nullable|date|after:today',
                'is_active' => 'boolean',
            ]);

            // セキュリティチェック：ExamType所有権の検証
            if (isset($validated['exam_type_id'])) {
                if (!$this->validateExamTypeOwnership($validated['exam_type_id'], auth()->id())) {
                    return response()->json([
                        'success' => false,
                        'message' => '指定された試験タイプへのアクセス権限がありません',
                    ], 403);
                }
            }

            // アクティブに変更される場合、他のアクティブな目標を無効化
            if (($validated['is_active'] ?? $goal->is_active) && ! $goal->is_active) {
                StudyGoal::forUser(auth()->id())
                    ->active()
                    ->where('id', '!=', $goal->id)
                    ->update(['is_active' => false]);
            }

            // トランザクション内でStudyGoal更新と試験日同期を実行
            DB::transaction(function () use ($goal, $validated) {
                $goal->update([
                    'exam_type_id' => $validated['exam_type_id'] ?? $goal->exam_type_id,
                    'daily_minutes_goal' => $validated['daily_minutes_goal'],
                    'weekly_minutes_goal' => $validated['weekly_minutes_goal'] ?? $goal->weekly_minutes_goal,
                    'exam_date' => $validated['exam_date'] ?? $goal->exam_date,
                    'is_active' => $validated['is_active'] ?? $goal->is_active,
                ]);

                // 試験日同期ロジック：StudyGoal → ExamType
                $this->syncExamDateToExamType($goal, auth()->id());
            });

            $goal->load('examType');

            return response()->json([
                'success' => true,
                'message' => '学習目標を更新しました',
                'goal' => [
                    'id' => $goal->id,
                    'exam_type_id' => $goal->exam_type_id,
                    'exam_type_name' => $goal->examType->name ?? null,
                    'daily_minutes_goal' => $goal->daily_minutes_goal,
                    'weekly_minutes_goal' => $goal->weekly_minutes_goal,
                    'exam_date' => $goal->exam_date?->format('Y-m-d'),
                    'is_active' => $goal->is_active,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データに問題があります',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習目標の更新中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習目標を削除
     */
    public function destroy(StudyGoal $goal): JsonResponse
    {
        try {
            // 自分の目標のみアクセス可能
            if ($goal->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'アクセス権限がありません',
                ], 403);
            }

            $goal->delete();

            return response()->json([
                'success' => true,
                'message' => '学習目標を削除しました',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習目標の削除中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * アクティブな学習目標を取得
     */
    public function active(): JsonResponse
    {
        try {
            $activeGoal = StudyGoal::forUser(auth()->id())
                ->active()
                ->with('examType')
                ->first();

            if (! $activeGoal) {
                return response()->json([
                    'success' => true,
                    'goal' => null,
                    'message' => 'アクティブな学習目標がありません',
                ]);
            }

            return response()->json([
                'success' => true,
                'goal' => [
                    'id' => $activeGoal->id,
                    'exam_type_id' => $activeGoal->exam_type_id,
                    'exam_type_name' => $activeGoal->examType->name ?? null,
                    'daily_minutes_goal' => $activeGoal->daily_minutes_goal,
                    'weekly_minutes_goal' => $activeGoal->weekly_minutes_goal,
                    'exam_date' => $activeGoal->exam_date?->format('Y-m-d'),
                    'is_active' => $activeGoal->is_active,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'アクティブな学習目標の取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ExamTypeの所有権を検証
     * セキュリティ強化：他ユーザーのExamTypeへの不正アクセスを防止
     */
    private function validateExamTypeOwnership(?int $examTypeId, int $userId): bool
    {
        if ($examTypeId === null) {
            return true; // exam_type_id が null の場合は検証をスキップ
        }

        return ExamType::where('id', $examTypeId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * StudyGoalの試験日をExamTypeに同期
     * アクティブなStudyGoalの試験日でExamTypeを更新
     */
    private function syncExamDateToExamType(StudyGoal $goal, int $userId): void
    {
        // exam_type_idが設定されておらず、アクティブでない場合は同期しない
        if (!$goal->exam_type_id || !$goal->is_active) {
            return;
        }

        $examType = ExamType::where('id', $goal->exam_type_id)
            ->where('user_id', $userId)
            ->first();

        if (!$examType) {
            return; // ExamTypeが存在しない、または所有権がない場合は何もしない
        }

        // 試験日に変更がある場合のみ更新
        if ($examType->exam_date !== $goal->exam_date) {
            $oldDate = $examType->exam_date;
            
            $examType->update(['exam_date' => $goal->exam_date]);

            // ログ出力
            \Log::info("StudyGoal {$goal->id} の試験日変更に伴い、ExamType {$examType->id} の試験日を同期更新しました", [
                'study_goal_id' => $goal->id,
                'exam_type_id' => $examType->id,
                'old_date' => $oldDate,
                'new_date' => $goal->exam_date,
                'user_id' => $userId,
            ]);
        }
    }
}
