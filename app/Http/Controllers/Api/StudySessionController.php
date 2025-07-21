<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudySession;
use App\Models\SubjectArea;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StudySessionController extends Controller
{
    /**
     * 学習セッション開始
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subject_area_id' => 'required|exists:subject_areas,id',
                'study_comment' => 'required|string|max:1000'
            ]);

            $userId = auth()->id();

            // 既に進行中のセッションがないかチェック
            $currentSession = StudySession::getCurrentSession($userId);
            if ($currentSession) {
                return response()->json([
                    'success' => false,
                    'message' => '既に進行中の学習セッションがあります。先に終了してください。',
                    'current_session' => [
                        'id' => $currentSession->id,
                        'subject_area' => $currentSession->subjectArea->name,
                        'started_at' => $currentSession->started_at->format('Y-m-d H:i:s'),
                        'elapsed_minutes' => $currentSession->started_at->diffInMinutes(now())
                    ]
                ], 400);
            }

            // 新しいセッションを開始
            $session = StudySession::create([
                'user_id' => $userId,
                'subject_area_id' => $validated['subject_area_id'],
                'started_at' => now(),
                'study_comment' => $validated['study_comment']
            ]);

            // レスポンス用に関連データを読み込み
            $session->load('subjectArea.examType');

            return response()->json([
                'success' => true,
                'message' => '学習セッションを開始しました',
                'session' => [
                    'id' => $session->id,
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'study_comment' => $session->study_comment
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'セッション開始中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習セッション終了
     */
    public function end(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'session_id' => 'sometimes|exists:study_sessions,id',
                'study_comment' => 'sometimes|string|max:1000'
            ]);

            $userId = auth()->id();

            // セッションIDが指定されている場合はそれを使用、なければ現在のセッションを取得
            if (isset($validated['session_id'])) {
                $session = StudySession::where('id', $validated['session_id'])
                    ->where('user_id', $userId)
                    ->first();
            } else {
                $session = StudySession::getCurrentSession($userId);
            }

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '終了可能な学習セッションが見つかりません'
                ], 404);
            }

            if (!$session->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'このセッションは既に終了しています'
                ], 400);
            }

            // セッション終了
            $comment = $validated['study_comment'] ?? null;
            $session->endSession($comment);
            $session->load('subjectArea.examType');

            return response()->json([
                'success' => true,
                'message' => '学習セッションを終了しました',
                'session' => [
                    'id' => $session->id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'ended_at' => $session->ended_at->format('Y-m-d H:i:s'),
                    'duration_minutes' => $session->duration_minutes,
                    'study_comment' => $session->study_comment
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'セッション終了中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 現在進行中のセッション取得
     */
    public function current(): JsonResponse
    {
        try {
            $userId = auth()->id();

            $session = StudySession::getCurrentSession($userId);

            if (!$session) {
                return response()->json([
                    'success' => true,
                    'message' => '現在進行中のセッションはありません',
                    'session' => null
                ]);
            }

            $session->load('subjectArea.examType');
            $elapsedMinutes = $session->started_at->diffInMinutes(now());

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'elapsed_minutes' => $elapsedMinutes,
                    'study_comment' => $session->study_comment
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '現在のセッション取得中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習履歴取得
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            $userId = auth()->id();

            $limit = $validated['limit'] ?? 20;
            $page = $validated['page'] ?? 1;

            $sessions = StudySession::completed()
                ->forUser($userId)
                ->with('subjectArea.examType')
                ->orderBy('started_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $history = $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'ended_at' => $session->ended_at->format('Y-m-d H:i:s'),
                    'duration_minutes' => $session->duration_minutes,
                    'study_comment' => $session->study_comment,
                    'date' => $session->started_at->format('Y-m-d')
                ];
            });

            return response()->json([
                'success' => true,
                'history' => $history,
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total()
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '学習履歴取得中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習セッション一覧（管理用）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $sessions = StudySession::forUser($userId)
                ->with('subjectArea.examType')
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get();

            $data = $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'ended_at' => $session->ended_at?->format('Y-m-d H:i:s'),
                    'duration_minutes' => $session->duration_minutes,
                    'is_active' => $session->isActive(),
                    'study_comment' => $session->study_comment
                ];
            });

            return response()->json([
                'success' => true,
                'sessions' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'セッション一覧取得中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習セッション詳細取得
     */
    public function show(int $id): JsonResponse
    {
        try {
            $userId = auth()->id();

            $session = StudySession::where('id', $id)
                ->where('user_id', $userId)
                ->with('subjectArea.examType')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された学習セッションが見つかりません'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'ended_at' => $session->ended_at?->format('Y-m-d H:i:s'),
                    'duration_minutes' => $session->duration_minutes,
                    'study_comment' => $session->study_comment,
                    'is_active' => $session->isActive(),
                    'created_at' => $session->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'セッション取得中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習セッション編集
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subject_area_id' => 'sometimes|exists:subject_areas,id',
                'study_comment' => 'sometimes|string|max:1000',
                'started_at' => 'sometimes|date',
                'ended_at' => 'sometimes|date|after:started_at',
                'duration_minutes' => 'sometimes|integer|min:1|max:1440' // 最大24時間
            ]);

            $userId = auth()->id();

            $session = StudySession::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された学習セッションが見つかりません'
                ], 404);
            }

            // アクティブなセッションは編集制限
            if ($session->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => '進行中のセッションは編集できません。先に終了してください。'
                ], 400);
            }

            // 日時が更新された場合、duration_minutesを再計算
            if (isset($validated['started_at']) || isset($validated['ended_at'])) {
                $startedAt = isset($validated['started_at']) ? 
                    \Carbon\Carbon::parse($validated['started_at']) : 
                    $session->started_at;
                
                $endedAt = isset($validated['ended_at']) ? 
                    \Carbon\Carbon::parse($validated['ended_at']) : 
                    $session->ended_at;

                if ($endedAt) {
                    $validated['duration_minutes'] = $startedAt->diffInMinutes($endedAt);
                }
            }

            // セッション更新
            $session->update($validated);
            $session->load('subjectArea.examType');

            return response()->json([
                'success' => true,
                'message' => '学習セッションを更新しました',
                'session' => [
                    'id' => $session->id,
                    'subject_area_id' => $session->subject_area_id,
                    'subject_area_name' => $session->subjectArea->name,
                    'exam_type_name' => $session->subjectArea->examType->name,
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'ended_at' => $session->ended_at?->format('Y-m-d H:i:s'),
                    'duration_minutes' => $session->duration_minutes,
                    'study_comment' => $session->study_comment,
                    'is_active' => $session->isActive()
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'セッション更新中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 学習セッション削除
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $userId = auth()->id();

            $session = StudySession::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された学習セッションが見つかりません'
                ], 404);
            }

            // アクティブなセッションは削除不可
            if ($session->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => '進行中のセッションは削除できません。先に終了してください。'
                ], 400);
            }

            // 削除実行
            $sessionData = [
                'id' => $session->id,
                'subject_area_name' => $session->subjectArea->name,
                'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                'duration_minutes' => $session->duration_minutes
            ];

            $session->delete();

            return response()->json([
                'success' => true,
                'message' => '学習セッションを削除しました',
                'deleted_session' => $sessionData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'セッション削除中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
