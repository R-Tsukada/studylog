<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use App\Models\SubjectArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserSubjectAreaController extends Controller
{
    /**
     * ユーザーの学習分野一覧を取得
     */
    public function index(): JsonResponse
    {
        try {
            $userId = auth()->id();

            // システム標準 + ユーザー固有の学習分野を取得
            $subjectAreas = SubjectArea::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('is_system', true);
            })
                ->with(['examType' => function ($query) use ($userId) {
                    $query->where(function ($subQuery) use ($userId) {
                        $subQuery->where('user_id', $userId)
                            ->orWhere('is_system', true);
                    });
                }])
                ->orderBy('is_system', 'desc')
                ->orderBy('name')
                ->get()
                ->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'exam_type_id' => $subject->exam_type_id,
                        'exam_type_name' => $subject->examType->name ?? 'Unknown',
                        'is_system' => $subject->is_system,
                        'user_id' => $subject->user_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'subject_areas' => $subjectAreas,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習分野の取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習分野を作成
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'exam_type_id' => 'required|exists:exam_types,id',
                'name' => 'required|string|max:255',
            ]);

            $userId = auth()->id();

            // 指定された試験タイプがユーザーのものか確認
            $examType = ExamType::where('id', $validated['exam_type_id'])
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('is_system', true);
                })
                ->first();

            if (! $examType) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された試験タイプが見つかりません',
                ], 404);
            }

            // 同じ試験タイプ内で同じ名前の学習分野が既に存在するかチェック
            $exists = SubjectArea::where('exam_type_id', $validated['exam_type_id'])
                ->where('name', $validated['name'])
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('is_system', true);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'この試験タイプには既に同じ名前の学習分野が存在します',
                ], 422);
            }

            // コードを自動生成
            $code = $this->generateSubjectCode($validated['name'], $userId);

            $subjectArea = SubjectArea::create([
                'user_id' => $userId,
                'exam_type_id' => $validated['exam_type_id'],
                'code' => $code,
                'name' => $validated['name'],
                'is_system' => false,
            ]);

            $subjectArea->load('examType');

            return response()->json([
                'success' => true,
                'message' => '学習分野を作成しました',
                'subject_area' => [
                    'id' => $subjectArea->id,
                    'name' => $subjectArea->name,
                    'exam_type_id' => $subjectArea->exam_type_id,
                    'exam_type_name' => $subjectArea->examType->name,
                    'is_system' => $subjectArea->is_system,
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習分野の作成中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習分野を更新
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'exam_type_id' => 'required|exists:exam_types,id',
                'name' => 'required|string|max:255',
            ]);

            $userId = auth()->id();

            $subjectArea = SubjectArea::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $subjectArea) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された学習分野が見つかりません',
                ], 404);
            }

            // システム標準データは編集不可
            if ($subjectArea->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'システム標準の学習分野は編集できません',
                ], 403);
            }

            // 指定された試験タイプがユーザーのものか確認
            $examType = ExamType::where('id', $validated['exam_type_id'])
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('is_system', true);
                })
                ->first();

            if (! $examType) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された試験タイプが見つかりません',
                ], 404);
            }

            // 名前の重複チェック（自分以外）
            $exists = SubjectArea::where('exam_type_id', $validated['exam_type_id'])
                ->where('name', $validated['name'])
                ->where('id', '!=', $id)
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('is_system', true);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'この試験タイプには既に同じ名前の学習分野が存在します',
                ], 422);
            }

            $subjectArea->update([
                'exam_type_id' => $validated['exam_type_id'],
                'name' => $validated['name'],
            ]);

            $subjectArea->load('examType');

            return response()->json([
                'success' => true,
                'message' => '学習分野を更新しました',
                'subject_area' => [
                    'id' => $subjectArea->id,
                    'name' => $subjectArea->name,
                    'exam_type_id' => $subjectArea->exam_type_id,
                    'exam_type_name' => $subjectArea->examType->name,
                    'is_system' => $subjectArea->is_system,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習分野の更新中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習分野を削除
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $userId = auth()->id();

            $subjectArea = SubjectArea::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $subjectArea) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された学習分野が見つかりません',
                ], 404);
            }

            // システム標準データは削除不可
            if ($subjectArea->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'システム標準の学習分野は削除できません',
                ], 403);
            }

            // 関連する学習履歴がある場合は警告
            $hasStudySessions = $subjectArea->studySessions()->exists();

            if ($hasStudySessions) {
                return response()->json([
                    'success' => false,
                    'message' => 'この学習分野には学習履歴が存在します。削除すると関連データも削除されます。',
                ], 409);
            }

            $subjectAreaName = $subjectArea->name;
            $subjectArea->delete();

            return response()->json([
                'success' => true,
                'message' => "学習分野「{$subjectAreaName}」を削除しました",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習分野の削除中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 学習分野コードを自動生成
     */
    private function generateSubjectCode(string $name, int $userId): string
    {
        // 名前から英数字のみを抽出してコードベースを作成
        $baseCode = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        if (empty($baseCode)) {
            $baseCode = 'subject';
        }

        // 最大10文字まで
        $baseCode = strtolower(substr($baseCode, 0, 10));

        // ユーザーIDと現在時刻を使ってユニークにする
        $uniqueCode = $baseCode.'_'.$userId.'_'.time();

        // 重複チェック（念のため）
        $counter = 1;
        $finalCode = $uniqueCode;
        while (SubjectArea::where('code', $finalCode)->exists()) {
            $finalCode = $uniqueCode.'_'.$counter;
            $counter++;
        }

        return $finalCode;
    }
}
