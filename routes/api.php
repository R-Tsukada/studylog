<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PomodoroController;
use App\Http\Controllers\Api\StudyAnalyticsController;
use App\Http\Controllers\Api\StudyGoalController;
use App\Http\Controllers\Api\StudySessionController;
use App\Http\Controllers\Api\UserFutureVisionController;
use Illuminate\Support\Facades\Route;

// 認証が不要なエンドポイント
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// 認証が必要なエンドポイント
Route::middleware('auth:sanctum')->group(function () {
    // 認証管理
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);
    Route::post('/auth/google/link', [GoogleAuthController::class, 'linkGoogleAccount']);
    Route::delete('/auth/google/unlink', [GoogleAuthController::class, 'unlinkGoogleAccount']);

    // ユーザー設定管理
    Route::apiResource('user/exam-types', App\Http\Controllers\Api\UserExamTypeController::class);
    Route::apiResource('user/subject-areas', App\Http\Controllers\Api\UserSubjectAreaController::class);

    // User Future Vision API（将来のビジョン機能）
    Route::prefix('user')->middleware(['throttle:10,1'])->group(function () {
        Route::get('/future-vision', [UserFutureVisionController::class, 'show']);
        Route::post('/future-vision', [UserFutureVisionController::class, 'store']);
        Route::put('/future-vision', [UserFutureVisionController::class, 'update']);
        Route::delete('/future-vision', [UserFutureVisionController::class, 'destroy']);
    });

    // Dashboard API（認証済みユーザーのデータのみ）
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);

    // Study Session API（認証済みユーザーのセッションのみ）
    Route::prefix('study-sessions')->group(function () {
        Route::get('/', [StudySessionController::class, 'index']);
        Route::post('/start', [StudySessionController::class, 'start']);
        Route::post('/end', [StudySessionController::class, 'end']);
        Route::get('/current', [StudySessionController::class, 'current']);
        Route::get('/history', [StudySessionController::class, 'history']);
        Route::get('/{id}', [StudySessionController::class, 'show']);
        Route::put('/{id}', [StudySessionController::class, 'update']);
        Route::delete('/{id}', [StudySessionController::class, 'destroy']);
    });

    // Pomodoro Session API（認証済みユーザーのセッションのみ）
    Route::prefix('pomodoro')->group(function () {
        Route::get('/', [PomodoroController::class, 'index']);
        Route::post('/', [PomodoroController::class, 'store']);
        Route::get('/current', [PomodoroController::class, 'current']);
        Route::get('/stats', [PomodoroController::class, 'stats']);
        Route::get('/{pomodoroSession}', [PomodoroController::class, 'show']);
        Route::put('/{pomodoroSession}', [PomodoroController::class, 'update']);
        Route::post('/{pomodoroSession}/complete', [PomodoroController::class, 'complete']);
        Route::delete('/{pomodoroSession}', [PomodoroController::class, 'destroy']);
    });

    // Study Analytics API（統合分析）
    Route::prefix('analytics')->group(function () {
        Route::get('/history', [StudyAnalyticsController::class, 'history']);
        Route::get('/stats', [StudyAnalyticsController::class, 'stats']);
        Route::get('/insights', [StudyAnalyticsController::class, 'insights']);
        Route::get('/suggest', [StudyAnalyticsController::class, 'suggest']);
        Route::get('/comparison', [StudyAnalyticsController::class, 'comparison']);

        // 草表示機能
        Route::get('/grass-data', [StudyAnalyticsController::class, 'grassData']);
        Route::get('/monthly-stats', [StudyAnalyticsController::class, 'monthlyStats']);
        Route::get('/day-detail', [StudyAnalyticsController::class, 'dayDetail']);
        Route::post('/clear-grass-cache', [StudyAnalyticsController::class, 'clearGrassCache']);
    });

    // Study Goal API（学習目標設定）
    Route::prefix('study-goals')->group(function () {
        Route::get('/', [StudyGoalController::class, 'index']);
        Route::post('/', [StudyGoalController::class, 'store']);
        Route::get('/active', [StudyGoalController::class, 'active']);
        Route::get('/{goal}', [StudyGoalController::class, 'show']);
        Route::put('/{goal}', [StudyGoalController::class, 'update']);
        Route::delete('/{goal}', [StudyGoalController::class, 'destroy']);
    });

    // Onboarding API（オンボーディング機能）
    Route::prefix('onboarding')->group(function () {
        Route::get('/status', [OnboardingController::class, 'status']);
        Route::post('/progress', [OnboardingController::class, 'updateProgress']);
        Route::post('/complete', [OnboardingController::class, 'complete']);
        Route::post('/skip', [OnboardingController::class, 'skip']);

        // 管理者用統計API（必要に応じて権限チェック追加）
        Route::get('/analytics', [OnboardingController::class, 'analytics']);
    });
});

// 認証不要なエンドポイント（マスターデータなど）
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!', 'timestamp' => now()]);
});

// Master Data API（認証不要）
Route::get('/exam-types', [MasterDataController::class, 'examTypes']);
Route::get('/subject-areas', [MasterDataController::class, 'subjectAreas']);
