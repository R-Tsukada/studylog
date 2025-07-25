<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PomodoroSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PomodoroController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = PomodoroSession::byUser($user->id)
            ->with('subjectArea.examType')
            ->orderBy('started_at', 'desc');

        if ($request->has('date')) {
            $query->whereDate('started_at', $request->date);
        }

        if ($request->has('session_type')) {
            $query->where('session_type', $request->session_type);
        }

        if ($request->has('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }

        $sessions = $query->paginate($request->get('per_page', 15));

        return response()->json($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_type' => 'required|in:focus,short_break,long_break',
            'planned_duration' => 'required|integer|min:1|max:120',
            'study_session_id' => 'nullable|exists:study_sessions,id',
            'subject_area_id' => 'nullable|exists:subject_areas,id',
            'settings' => 'nullable|array',
            'settings.focus_duration' => 'nullable|integer|min:15|max:60',
            'settings.short_break_duration' => 'nullable|integer|min:5|max:20',
            'settings.long_break_duration' => 'nullable|integer|min:15|max:30',
            'settings.auto_start_break' => 'nullable|boolean',
            'settings.auto_start_focus' => 'nullable|boolean',
            'settings.sound_enabled' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        $activeSession = PomodoroSession::byUser($user->id)
            ->where('is_completed', false)
            ->first();

        if ($activeSession) {
            return response()->json([
                'message' => '既にアクティブなポモドーロセッションがあります。',
                'active_session' => $activeSession,
            ], 409);
        }

        $session = PomodoroSession::create([
            'user_id' => $user->id,
            'study_session_id' => $validated['study_session_id'] ?? null,
            'subject_area_id' => $validated['subject_area_id'] ?? null,
            'session_type' => $validated['session_type'],
            'planned_duration' => $validated['planned_duration'],
            'started_at' => now(),
            'settings' => $validated['settings'] ?? null,
        ]);

        return response()->json($session->load('subjectArea.examType'), 201);
    }

    public function show(PomodoroSession $pomodoroSession): JsonResponse
    {
        $user = Auth::user();

        if ($pomodoroSession->user_id !== $user->id) {
            return response()->json(['message' => 'このリソースにアクセスする権限がありません。'], 403);
        }

        return response()->json($pomodoroSession->load('subjectArea.examType'));
    }

    public function update(Request $request, PomodoroSession $pomodoroSession): JsonResponse
    {
        $user = Auth::user();

        if ($pomodoroSession->user_id !== $user->id) {
            return response()->json(['message' => 'このリソースにアクセスする権限がありません。'], 403);
        }

        $validated = $request->validate([
            'actual_duration' => 'nullable|integer|min:1',
            'completed_at' => 'nullable|date',
            'is_completed' => 'nullable|boolean',
            'was_interrupted' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $pomodoroSession->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'ポモドーロセッションを更新しました',
            'session' => $pomodoroSession->load('subjectArea.examType'),
        ]);
    }

    public function complete(Request $request, PomodoroSession $pomodoroSession): JsonResponse
    {
        $user = Auth::user();

        if ($pomodoroSession->user_id !== $user->id) {
            return response()->json(['message' => 'このリソースにアクセスする権限がありません。'], 403);
        }

        if ($pomodoroSession->is_completed) {
            return response()->json(['message' => 'このセッションは既に完了しています。'], 409);
        }

        $validated = $request->validate([
            'actual_duration' => 'required|integer|min:1',
            'was_interrupted' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $pomodoroSession->update([
            'actual_duration' => $validated['actual_duration'],
            'completed_at' => now(),
            'is_completed' => true,
            'was_interrupted' => $validated['was_interrupted'] ?? false,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($pomodoroSession->load('subjectArea.examType'));
    }

    public function current(): JsonResponse
    {
        $user = Auth::user();

        $currentSession = PomodoroSession::byUser($user->id)
            ->where('is_completed', false)
            ->with('subjectArea.examType')
            ->first();

        if (! $currentSession) {
            return response()->json(['message' => 'アクティブなポモドーロセッションがありません。'], 404);
        }

        return response()->json($currentSession);
    }

    public function stats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // 日付パラメータを適切に処理
            $startDateParam = $request->get('start_date');
            $endDateParam = $request->get('end_date');

            if ($startDateParam) {
                $startDate = \Carbon\Carbon::parse($startDateParam)->startOfDay();
            } else {
                $startDate = now()->startOfMonth();
            }

            if ($endDateParam) {
                $endDate = \Carbon\Carbon::parse($endDateParam)->endOfDay();
            } else {
                $endDate = now()->endOfMonth();
            }

            $sessions = PomodoroSession::byUser($user->id)
                ->dateRange($startDate, $endDate)
                ->completed()
                ->get();

            $stats = [
                'total_sessions' => $sessions->count(),
                'focus_sessions' => $sessions->where('session_type', 'focus')->count(),
                'break_sessions' => $sessions->whereIn('session_type', ['short_break', 'long_break'])->count(),
                'total_focus_time' => $sessions->where('session_type', 'focus')->sum('actual_duration') ?: 0,
                'total_break_time' => $sessions->whereIn('session_type', ['short_break', 'long_break'])->sum('actual_duration') ?: 0,
                'interrupted_sessions' => $sessions->where('was_interrupted', true)->count(),
                'completion_rate' => $sessions->count() > 0 ?
                    round((1 - $sessions->where('was_interrupted', true)->count() / $sessions->count()) * 100, 1) : 0,
                'average_focus_duration' => $sessions->where('session_type', 'focus')->avg('actual_duration') ?: 0,
            ];

            $dailyStats = $sessions->groupBy(function ($session) {
                return $session->started_at->format('Y-m-d');
            })->map(function ($daySessions) {
                return [
                    'date' => $daySessions->first()->started_at->format('Y-m-d'),
                    'total_sessions' => $daySessions->count(),
                    'focus_sessions' => $daySessions->where('session_type', 'focus')->count(),
                    'total_focus_time' => $daySessions->where('session_type', 'focus')->sum('actual_duration') ?: 0,
                    'interrupted_sessions' => $daySessions->where('was_interrupted', true)->count(),
                ];
            })->values();

            return response()->json([
                'stats' => $stats,
                'daily_stats' => $dailyStats,
            ]);
        } catch (\Exception $e) {
            \Log::error('ポモドーロ統計取得エラー:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request_params' => $request->all(),
            ]);

            return response()->json([
                'message' => 'ポモドーロ統計の取得中にエラーが発生しました',
            ], 500);
        }
    }

    public function destroy(PomodoroSession $pomodoroSession): JsonResponse
    {
        $user = Auth::user();

        if ($pomodoroSession->user_id !== $user->id) {
            return response()->json(['message' => 'このリソースにアクセスする権限がありません。'], 403);
        }

        $pomodoroSession->delete();

        return response()->json(['message' => 'ポモドーロセッションが削除されました。']);
    }
}
