<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StudySessionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PomodoroController;
use App\Http\Controllers\Api\StudyAnalyticsController;

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
    Route::post('/auth/google/link', [GoogleAuthController::class, 'linkGoogleAccount']);
    Route::delete('/auth/google/unlink', [GoogleAuthController::class, 'unlinkGoogleAccount']);
    
    // ユーザー設定管理
    Route::apiResource('user/exam-types', App\Http\Controllers\Api\UserExamTypeController::class);
    Route::apiResource('user/subject-areas', App\Http\Controllers\Api\UserSubjectAreaController::class);
    
    // Dashboard API（認証済みユーザーのデータのみ）
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/statistics', [DashboardController::class, 'statistics']);
    Route::get('/dashboard/study-calendar', [DashboardController::class, 'getStudyCalendar']);
    
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
    });
});

// 認証不要なエンドポイント（マスターデータなど）
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!', 'timestamp' => now()]);
});

// Master Data API（認証不要）
Route::get('/exam-types', [MasterDataController::class, 'examTypes']);
Route::get('/subject-areas', [MasterDataController::class, 'subjectAreas']);
