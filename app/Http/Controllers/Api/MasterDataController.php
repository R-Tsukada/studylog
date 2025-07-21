<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use App\Models\SubjectArea;
use Illuminate\Http\JsonResponse;

class MasterDataController extends Controller
{
    /**
     * 試験タイプ一覧を取得
     */
    public function examTypes(): JsonResponse
    {
        try {
            $examTypes = ExamType::with(['subjectAreas' => function($query) {
                    $query->where('is_active', true)->orderBy('sort_order');
                }])
                ->where('is_active', true)
                ->orderBy('created_at')
                ->get(['id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at']);

            return response()->json([
                'status' => 'success',
                'data' => $examTypes,
                'message' => '試験タイプ一覧を取得しました'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '試験タイプの取得に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 学習分野一覧を取得（試験タイプ別）
     */
    public function subjectAreas(): JsonResponse
    {
        try {
            $examTypeId = request()->get('exam_type_id');

            $query = SubjectArea::with('examType:id,name,code')
                ->where('is_active', true);

            if ($examTypeId) {
                $query->where('exam_type_id', $examTypeId);
            }

            $subjectAreas = $query
                ->orderBy('exam_type_id')
                ->orderBy('sort_order')
                ->get(['id', 'exam_type_id', 'code', 'name', 'description', 'sort_order', 'is_active', 'created_at', 'updated_at']);

            return response()->json([
                'status' => 'success',
                'data' => $subjectAreas,
                'message' => '学習分野一覧を取得しました'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => '学習分野の取得に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
