<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserFutureVisionRequest;
use App\Models\UserFutureVision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserFutureVisionController extends Controller
{
    /**
     * 将来のビジョンを取得
     */
    public function show(Request $request): JsonResponse|Response
    {
        $vision = $request->user()->userFutureVision;

        if (! $vision->exists) {
            return response()->noContent();
        }

        return response()->json([
            'success' => true,
            'data' => $vision,
        ], 200);
    }

    /**
     * 将来のビジョンを作成
     */
    public function store(UserFutureVisionRequest $request): JsonResponse
    {
        $user = $request->user();

        // 既存のビジョンがある場合は409 Conflictを返す
        if ($user->userFutureVision->exists) {
            return response()->json([
                'success' => false,
                'message' => '将来のビジョンは既に登録されています。更新する場合はPUTメソッドを使用してください。',
            ], 409);
        }

        try {
            $vision = UserFutureVision::create([
                'user_id' => $user->id,
                'vision_text' => $request->validated()['vision_text'],
            ]);

            return response()->json([
                'success' => true,
                'message' => '将来のビジョンを保存しました',
                'data' => $vision,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            // ユニーク制約違反の場合（race condition対応）
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                return response()->json([
                    'success' => false,
                    'message' => '将来のビジョンは既に登録されています。更新する場合はPUTメソッドを使用してください。',
                ], 409);
            }

            throw $e;
        }
    }

    /**
     * 将来のビジョンを更新
     */
    public function update(UserFutureVisionRequest $request): JsonResponse
    {
        $user = $request->user();
        $vision = $user->userFutureVision;

        if (! $vision->exists) {
            return response()->json([
                'success' => false,
                'message' => '更新対象の将来のビジョンが見つかりません。',
            ], 404);
        }

        $vision->update([
            'vision_text' => $request->validated()['vision_text'],
        ]);

        return response()->json([
            'success' => true,
            'message' => '将来のビジョンを更新しました',
            'data' => $vision->fresh(),
        ]);
    }

    /**
     * 将来のビジョンを削除
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $vision = $user->userFutureVision;

        if (! $vision->exists) {
            return response()->json([
                'success' => false,
                'message' => '削除対象の将来のビジョンが見つかりません。',
            ], 404);
        }

        $vision->delete();

        return response()->json([
            'success' => true,
            'message' => '将来のビジョンを削除しました',
        ]);
    }
}
