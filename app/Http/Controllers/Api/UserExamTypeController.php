<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserExamTypeController extends Controller
{
    /**
     * ユーザーの試験タイプ一覧を取得
     */
    public function index(): JsonResponse
    {
        try {
            $userId = auth()->id();

            // システム標準 + ユーザー固有の試験タイプを取得
            $examTypes = ExamType::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('is_system', true);
            })
                ->with(['subjectAreas' => function ($query) use ($userId) {
                    $query->where(function ($subQuery) use ($userId) {
                        $subQuery->where('user_id', $userId)
                            ->orWhere('is_system', true);
                    });
                }])
                ->orderBy('is_system', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'exam_types' => $examTypes,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '試験タイプの取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 試験タイプを作成
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'exam_date' => 'nullable|date',
                'exam_notes' => 'nullable|string|max:2000',
                'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            $userId = auth()->id();

            // 同じ名前の試験タイプが既に存在するかチェック
            $exists = ExamType::where('name', $validated['name'])
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('is_system', true);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'この名前の試験タイプは既に存在します',
                ], 422);
            }

            // コードを自動生成（名前の先頭文字 + ユニークID）
            $code = $this->generateExamCode($validated['name'], $userId);

            $examType = ExamType::create([
                'user_id' => $userId,
                'code' => $code,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'exam_date' => $validated['exam_date'] ?? null,
                'exam_notes' => $validated['exam_notes'] ?? null,
                'color' => $validated['color'] ?? '#3B82F6',
                'is_system' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => '試験タイプを作成しました',
                'exam_type' => $examType,
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
                'message' => '試験タイプの作成中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 試験タイプを更新
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'exam_date' => 'nullable|date',
                'exam_notes' => 'nullable|string|max:2000',
                'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            ]);

            $userId = auth()->id();

            $examType = ExamType::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $examType) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された試験タイプが見つかりません',
                ], 404);
            }

            // システム標準データは編集不可
            if ($examType->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'システム標準の試験タイプは編集できません',
                ], 403);
            }

            // 名前の重複チェック（自分以外）
            $exists = ExamType::where('name', $validated['name'])
                ->where('id', '!=', $id)
                ->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->orWhere('is_system', true);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'この名前の試験タイプは既に存在します',
                ], 422);
            }

            $examType->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $examType->description,
                'exam_date' => $validated['exam_date'],
                'exam_notes' => $validated['exam_notes'],
                'color' => $validated['color'] ?? $examType->color,
            ]);

            return response()->json([
                'success' => true,
                'message' => '試験タイプを更新しました',
                'exam_type' => $examType,
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
                'message' => '試験タイプの更新中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 試験タイプを削除
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $userId = auth()->id();

            $examType = ExamType::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $examType) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された試験タイプが見つかりません',
                ], 404);
            }

            // システム標準データは削除不可
            if ($examType->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'システム標準の試験タイプは削除できません',
                ], 403);
            }

            // 関連する学習履歴がある場合は警告
            $hasStudySessions = $examType->subjectAreas()
                ->whereHas('studySessions')
                ->exists();

            if ($hasStudySessions) {
                return response()->json([
                    'success' => false,
                    'message' => 'この試験タイプには学習履歴が存在します。削除すると関連データも削除されます。',
                ], 409);
            }

            $examTypeName = $examType->name;
            $examType->delete();

            return response()->json([
                'success' => true,
                'message' => "試験タイプ「{$examTypeName}」を削除しました",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '試験タイプの削除中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 試験コードを自動生成
     */
    private function generateExamCode(string $name, int $userId): string
    {
        // 名前から英数字のみを抽出してコードベースを作成
        $baseCode = preg_replace('/[^a-zA-Z0-9]/', '', $name);
        if (empty($baseCode)) {
            $baseCode = 'exam';
        }

        // 最大10文字まで
        $baseCode = strtolower(substr($baseCode, 0, 10));

        // ユーザーIDと現在時刻を使ってユニークにする
        $uniqueCode = $baseCode.'_'.$userId.'_'.time();

        // 重複チェック（念のため）
        $counter = 1;
        $finalCode = $uniqueCode;
        while (ExamType::where('code', $finalCode)->exists()) {
            $finalCode = $uniqueCode.'_'.$counter;
            $counter++;
        }

        return $finalCode;
    }
}
