# GitHub草表示機能 実装詳細設計書

## 概要

学習記録アプリに、GitHubのコントリビューション表示のような学習時間の視覚化機能を追加する実装詳細を定義する。

## 目次

1. [データベース実装詳細](#1-データベース実装詳細)
2. [既存データ移行戦略](#2-既存データ移行戦略)
3. [バックエンド実装詳細](#3-バックエンド実装詳細)
4. [フロントエンド実装詳細](#4-フロントエンド実装詳細)
5. [エラーハンドリング戦略](#5-エラーハンドリング戦略)
6. [パフォーマンス最適化](#6-パフォーマンス最適化)
7. [テスト戦略](#7-テスト戦略)
8. [実装手順](#8-実装手順)

---

## 1. データベース実装詳細

### 1.1 マイグレーションファイル作成

**実行コマンド:**
```bash
php artisan make:migration add_grass_display_columns_to_daily_study_summaries_table --table=daily_study_summaries
```

**マイグレーションファイル:**
```php
<?php
// database/migrations/2025_07_24_000000_add_grass_display_columns_to_daily_study_summaries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_study_summaries', function (Blueprint $table) {
            // 学習開始/終了で計測した時間（分）
            $table->integer('study_session_minutes')->default(0)->after('total_minutes')
                ->comment('学習開始/終了で計測した合計時間（分）');
            
            // ポモドーロで計測した時間（分）
            $table->integer('pomodoro_minutes')->default(0)->after('study_session_minutes')
                ->comment('ポモドーロで計測した合計時間（分）');
            
            // 完了したフォーカスセッション数
            $table->integer('total_focus_sessions')->default(0)->after('pomodoro_minutes')
                ->comment('完了したフォーカスセッション数');
            
            // 草表示用レベル（0-3）
            $table->tinyInteger('grass_level')->default(0)->after('total_focus_sessions')
                ->comment('草表示レベル（0:なし、1:薄い、2:中間、3:濃い）');
            
            // 学習ストリーク用（後の拡張用）
            $table->integer('streak_days')->default(0)->after('grass_level')
                ->comment('連続学習日数（将来の拡張用）');
        });

        // インデックス追加
        Schema::table('daily_study_summaries', function (Blueprint $table) {
            $table->index(['user_id', 'study_date', 'grass_level'], 'idx_user_date_grass');
            $table->index(['grass_level', 'study_date'], 'idx_grass_level_date');
        });
    }

    public function down(): void
    {
        Schema::table('daily_study_summaries', function (Blueprint $table) {
            $table->dropIndex('idx_user_date_grass');
            $table->dropIndex('idx_grass_level_date');
            
            $table->dropColumn([
                'study_session_minutes',
                'pomodoro_minutes', 
                'total_focus_sessions',
                'grass_level',
                'streak_days'
            ]);
        });
    }
};
```

### 1.2 DailyStudySummaryモデル拡張

```php
<?php
// app/Models/DailyStudySummary.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyStudySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_date',
        'total_minutes',
        'session_count',
        'subject_breakdown',
        'study_session_minutes',    // 新規追加
        'pomodoro_minutes',         // 新規追加
        'total_focus_sessions',     // 新規追加
        'grass_level',              // 新規追加
        'streak_days',              // 新規追加
    ];

    protected $casts = [
        'study_date' => 'date',
        'total_minutes' => 'integer',
        'session_count' => 'integer',
        'subject_breakdown' => 'array',
        'study_session_minutes' => 'integer',
        'pomodoro_minutes' => 'integer',
        'total_focus_sessions' => 'integer',
        'grass_level' => 'integer',
        'streak_days' => 'integer',
    ];

    // リレーション
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 既存スコープ
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('study_date', [$startDate, $endDate]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $limit = null)
    {
        $query = $query->orderBy('study_date', 'desc');
        
        if ($limit !== null) {
            $query = $query->limit($limit);
        }
        
        return $query;
    }

    // 新規スコープ
    public function scopeWithGrassLevel($query, $level)
    {
        return $query->where('grass_level', $level);
    }

    public function scopeActiveStudyDays($query)
    {
        return $query->where('total_minutes', '>', 0);
    }

    // 新規メソッド
    public function updateFromStudySession(StudySession $studySession): void
    {
        $this->study_session_minutes += $studySession->duration_minutes;
        $this->recalculateTotal();
        $this->updateSubjectBreakdown($studySession);
    }

    public function updateFromPomodoroSession(PomodoroSession $pomodoroSession): void
    {
        if ($pomodoroSession->session_type === 'focus' && $pomodoroSession->is_completed) {
            $this->pomodoro_minutes += $pomodoroSession->actual_duration;
            $this->total_focus_sessions += 1;
            $this->recalculateTotal();
            $this->updateSubjectBreakdownFromPomodoro($pomodoroSession);
        }
    }

    public function updateSubjectBreakdown(StudySession $studySession): void
    {
        $breakdown = $this->subject_breakdown ?? [];
        $subjectName = $studySession->subjectArea->name ?? 'その他';
        
        if (!isset($breakdown[$subjectName])) {
            $breakdown[$subjectName] = 0;
        }
        
        $breakdown[$subjectName] += $studySession->duration_minutes;
        $this->subject_breakdown = $breakdown;
    }

    public function updateSubjectBreakdownFromPomodoro(PomodoroSession $pomodoroSession): void
    {
        $breakdown = $this->subject_breakdown ?? [];
        $subjectName = $pomodoroSession->subjectArea->name ?? 'その他';
        
        if (!isset($breakdown[$subjectName])) {
            $breakdown[$subjectName] = 0;
        }
        
        $breakdown[$subjectName] += $pomodoroSession->actual_duration;
        $this->subject_breakdown = $breakdown;
    }

    private function recalculateTotal(): void
    {
        $this->total_minutes = $this->study_session_minutes + $this->pomodoro_minutes;
        $this->grass_level = $this->calculateGrassLevel($this->total_minutes);
    }

    public function calculateGrassLevel(int $totalMinutes): int
    {
        if ($totalMinutes == 0) return 0;
        if ($totalMinutes <= 60) return 1;
        if ($totalMinutes <= 120) return 2;
        return 3;
    }

    // 草表示用のデータ配列を取得
    public function toGrassData(): array
    {
        return [
            'date' => $this->study_date->format('Y-m-d'),
            'total_minutes' => $this->total_minutes,
            'level' => $this->grass_level,
            'study_session_minutes' => $this->study_session_minutes,
            'pomodoro_minutes' => $this->pomodoro_minutes,
            'session_count' => $this->session_count,
            'focus_sessions' => $this->total_focus_sessions,
        ];
    }
}
```

---

## 2. 既存データ移行戦略

### 2.1 データ移行用Artisanコマンド作成

**実行コマンド:**
```bash
php artisan make:command MigrateGrassDisplayData
```

**コマンド実装:**
```php
<?php
// app/Console/Commands/MigrateGrassDisplayData.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DailyStudySummary;
use App\Models\StudySession;
use App\Models\PomodoroSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MigrateGrassDisplayData extends Command
{
    protected $signature = 'grass:migrate-data {--user_id=} {--start_date=} {--end_date=} {--dry-run}';
    protected $description = '既存の学習データを草表示用に移行する';

    public function handle()
    {
        $userId = $this->option('user_id');
        $startDate = $this->option('start_date') ?? '2024-01-01';
        $endDate = $this->option('end_date') ?? now()->toDateString();
        $dryRun = $this->option('dry-run');

        $this->info("草表示データ移行を開始します...");
        
        if ($dryRun) {
            $this->warn("*** DRY RUN MODE - 実際の更新は行いません ***");
        }

        $query = DailyStudySummary::whereBetween('study_date', [$startDate, $endDate]);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $summaries = $query->get();
        $this->info("対象レコード数: " . $summaries->count());

        $progressBar = $this->output->createProgressBar($summaries->count());
        $updatedCount = 0;

        DB::beginTransaction();
        
        try {
            foreach ($summaries as $summary) {
                $updated = $this->migrateDailySummary($summary, $dryRun);
                if ($updated) {
                    $updatedCount++;
                }
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
            
            if (!$dryRun) {
                DB::commit();
                $this->info("移行完了: {$updatedCount}件のレコードを更新しました");
            } else {
                DB::rollBack();
                $this->info("DRY RUN完了: {$updatedCount}件のレコードが更新対象です");
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("移行中にエラーが発生しました: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function migrateDailySummary(DailyStudySummary $summary, bool $dryRun): bool
    {
        // StudySessionの時間を集計
        $studySessionMinutes = StudySession::where('user_id', $summary->user_id)
            ->whereDate('started_at', $summary->study_date)
            ->whereNotNull('ended_at')
            ->sum('duration_minutes');

        // PomodoroSessionの時間を集計（完了したフォーカスセッションのみ）
        $pomodoroData = PomodoroSession::where('user_id', $summary->user_id)
            ->whereDate('started_at', $summary->study_date)
            ->where('session_type', 'focus')
            ->where('is_completed', true)
            ->selectRaw('
                COALESCE(SUM(actual_duration), 0) as total_minutes,
                COUNT(*) as session_count
            ')
            ->first();

        $pomodoroMinutes = $pomodoroData->total_minutes ?? 0;
        $focusSessions = $pomodoroData->session_count ?? 0;

        // 新しい合計時間を計算
        $newTotalMinutes = $studySessionMinutes + $pomodoroMinutes;
        
        // 草レベルを計算
        $grassLevel = $this->calculateGrassLevel($newTotalMinutes);

        // 更新が必要かチェック
        $needsUpdate = (
            $summary->study_session_minutes != $studySessionMinutes ||
            $summary->pomodoro_minutes != $pomodoroMinutes ||
            $summary->total_focus_sessions != $focusSessions ||
            $summary->grass_level != $grassLevel ||
            $summary->total_minutes != $newTotalMinutes
        );

        if ($needsUpdate && !$dryRun) {
            $summary->update([
                'study_session_minutes' => $studySessionMinutes,
                'pomodoro_minutes' => $pomodoroMinutes,
                'total_focus_sessions' => $focusSessions,
                'grass_level' => $grassLevel,
                'total_minutes' => $newTotalMinutes,
            ]);
        }

        return $needsUpdate;
    }

    private function calculateGrassLevel(int $totalMinutes): int
    {
        if ($totalMinutes == 0) return 0;
        if ($totalMinutes <= 60) return 1;
        if ($totalMinutes <= 120) return 2;
        return 3;
    }
}
```

### 2.2 移行実行手順

```bash
# 1. ドライランで確認
php artisan grass:migrate-data --dry-run

# 2. 特定ユーザーでテスト
php artisan grass:migrate-data --user_id=1 --start_date=2025-01-01

# 3. 全データ移行
php artisan grass:migrate-data
```

---

## 3. バックエンド実装詳細

### 3.1 StudyActivityService実装

```php
<?php
// app/Services/StudyActivityService.php

namespace App\Services;

use App\Models\DailyStudySummary;
use App\Models\StudySession;
use App\Models\PomodoroSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StudyActivityService
{
    public function updateDailySummaryFromStudySession(StudySession $studySession): DailyStudySummary
    {
        $summary = DailyStudySummary::firstOrCreate([
            'user_id' => $studySession->user_id,
            'study_date' => $studySession->started_at->toDateString()
        ]);

        $summary->updateFromStudySession($studySession);
        $summary->save();

        // キャッシュをクリア
        $this->clearUserGrassCache($studySession->user_id);

        return $summary;
    }

    public function updateDailySummaryFromPomodoro(PomodoroSession $pomodoroSession): ?DailyStudySummary
    {
        if ($pomodoroSession->session_type !== 'focus' || !$pomodoroSession->is_completed) {
            return null;
        }

        $summary = DailyStudySummary::firstOrCreate([
            'user_id' => $pomodoroSession->user_id,
            'study_date' => $pomodoroSession->started_at->toDateString()
        ]);

        $summary->updateFromPomodoroSession($pomodoroSession);
        $summary->save();

        // キャッシュをクリア
        $this->clearUserGrassCache($pomodoroSession->user_id);

        return $summary;
    }

    public function getGrassData(int $userId, int $year): array
    {
        $cacheKey = "grass_data_{$userId}_{$year}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $year) {
            return $this->buildGrassData($userId, $year);
        });
    }

    private function buildGrassData(int $userId, int $year): array
    {
        $startDate = Carbon::create($year, 1, 1);
        $endDate = Carbon::create($year, 12, 31);

        // データベースから日別サマリーを取得
        $summaries = DailyStudySummary::byUser($userId)
            ->dateRange($startDate, $endDate)
            ->get()
            ->keyBy(function ($item) {
                return $item->study_date->format('Y-m-d');
            });

        // 365日分のデータ配列を構築
        $days = [];
        $totals = [
            'total_study_minutes' => 0,
            'active_days' => 0,
            'longest_streak' => 0,
            'current_streak' => 0,
        ];

        $currentStreak = 0;
        $longestStreak = 0;
        $isCurrentStreakActive = true;

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $summary = $summaries->get($dateStr);

            if ($summary) {
                $dayData = $summary->toGrassData();
                $totals['total_study_minutes'] += $dayData['total_minutes'];
                
                if ($dayData['total_minutes'] > 0) {
                    $totals['active_days']++;
                    $currentStreak++;
                    $longestStreak = max($longestStreak, $currentStreak);
                } else {
                    if ($isCurrentStreakActive && $date <= now()) {
                        $totals['current_streak'] = $currentStreak;
                        $isCurrentStreakActive = false;
                    }
                    $currentStreak = 0;
                }
            } else {
                // データが存在しない日
                $dayData = [
                    'date' => $dateStr,
                    'total_minutes' => 0,
                    'level' => 0,
                    'study_session_minutes' => 0,
                    'pomodoro_minutes' => 0,
                    'session_count' => 0,
                    'focus_sessions' => 0,
                ];
                
                if ($isCurrentStreakActive && $date <= now()) {
                    $totals['current_streak'] = $currentStreak;
                    $isCurrentStreakActive = false;
                }
                $currentStreak = 0;
            }

            $days[] = $dayData;
        }

        // 現在もストリークが継続している場合
        if ($isCurrentStreakActive) {
            $totals['current_streak'] = $currentStreak;
        }

        $totals['longest_streak'] = $longestStreak;

        return [
            'year' => $year,
            'days' => $days,
            'totals' => $totals,
        ];
    }

    public function getMonthlyStats(int $userId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        $summaries = DailyStudySummary::byUser($userId)
            ->dateRange($startDate, $endDate)
            ->get();

        $totalMinutes = $summaries->sum('total_minutes');
        $activeDays = $summaries->where('total_minutes', '>', 0)->count();
        $averagePerDay = $activeDays > 0 ? round($totalMinutes / $activeDays, 1) : 0;

        $bestDay = $summaries->sortByDesc('total_minutes')->first();
        $subjectBreakdown = [];

        foreach ($summaries as $summary) {
            foreach ($summary->subject_breakdown ?? [] as $subject => $minutes) {
                if (!isset($subjectBreakdown[$subject])) {
                    $subjectBreakdown[$subject] = 0;
                }
                $subjectBreakdown[$subject] += $minutes;
            }
        }

        return [
            'year' => $year,
            'month' => $month,
            'total_minutes' => $totalMinutes,
            'active_days' => $activeDays,
            'average_per_day' => $averagePerDay,
            'best_day' => $bestDay ? [
                'date' => $bestDay->study_date->format('Y-m-d'),
                'minutes' => $bestDay->total_minutes,
            ] : null,
            'subject_breakdown' => $subjectBreakdown,
        ];
    }

    public function getDayDetail(int $userId, string $date): array
    {
        $summary = DailyStudySummary::byUser($userId)
            ->where('study_date', $date)
            ->first();

        if (!$summary) {
            return [
                'date' => $date,
                'total_minutes' => 0,
                'sessions' => [],
            ];
        }

        // その日の学習セッションを取得
        $studySessions = StudySession::where('user_id', $userId)
            ->whereDate('started_at', $date)
            ->whereNotNull('ended_at')
            ->with('subjectArea')
            ->get()
            ->map(function ($session) {
                return [
                    'type' => 'study_session',
                    'duration_minutes' => $session->duration_minutes,
                    'subject' => $session->subjectArea->name ?? 'その他',
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'ended_at' => $session->ended_at->format('Y-m-d H:i:s'),
                    'comment' => $session->study_comment,
                ];
            });

        // その日のポモドーロセッションを取得
        $pomodoroSessions = PomodoroSession::where('user_id', $userId)
            ->whereDate('started_at', $date)
            ->where('session_type', 'focus')
            ->where('is_completed', true)
            ->with('subjectArea')
            ->get()
            ->map(function ($session) {
                return [
                    'type' => 'pomodoro',
                    'duration_minutes' => $session->actual_duration,
                    'subject' => $session->subjectArea->name ?? 'その他',
                    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
                    'completed_at' => $session->completed_at->format('Y-m-d H:i:s'),
                    'was_interrupted' => $session->was_interrupted,
                    'notes' => $session->notes,
                ];
            });

        // セッションを時系列順にソート
        $allSessions = $studySessions->concat($pomodoroSessions)
            ->sortBy('started_at')
            ->values()
            ->toArray();

        return [
            'date' => $date,
            'total_minutes' => $summary->total_minutes,
            'study_session_minutes' => $summary->study_session_minutes,
            'pomodoro_minutes' => $summary->pomodoro_minutes,
            'focus_sessions' => $summary->total_focus_sessions,
            'sessions' => $allSessions,
        ];
    }

    private function clearUserGrassCache(int $userId): void
    {
        $currentYear = now()->year;
        for ($year = $currentYear - 1; $year <= $currentYear + 1; $year++) {
            Cache::forget("grass_data_{$userId}_{$year}");
        }
    }
}
```

### 3.2 StudyActivityController実装

```php
<?php
// app/Http/Controllers/Api/StudyActivityController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudyActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class StudyActivityController extends Controller
{
    private StudyActivityService $studyActivityService;

    public function __construct(StudyActivityService $studyActivityService)
    {
        $this->studyActivityService = $studyActivityService;
    }

    public function getGrassData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'sometimes|integer|between:2020,2030',
            ]);

            $year = $validated['year'] ?? now()->year;
            $userId = auth()->id();

            $grassData = $this->studyActivityService->getGrassData($userId, $year);

            return response()->json([
                'success' => true,
                'data' => $grassData,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力値が不正です',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('草表示データ取得エラー', [
                'user_id' => auth()->id(),
                'year' => $year ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'データの取得に失敗しました',
            ], 500);
        }
    }

    public function getMonthlyStats(Request $request, int $year, int $month): JsonResponse
    {
        try {
            // バリデーション
            if ($year < 2020 || $year > 2030) {
                return response()->json([
                    'success' => false,
                    'message' => '年は2020年から2030年の間で指定してください',
                ], 422);
            }

            if ($month < 1 || $month > 12) {
                return response()->json([
                    'success' => false,
                    'message' => '月は1から12の間で指定してください',
                ], 422);
            }

            $userId = auth()->id();
            $stats = $this->studyActivityService->getMonthlyStats($userId, $year, $month);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            \Log::error('月別統計取得エラー', [
                'user_id' => auth()->id(),
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '統計データの取得に失敗しました',
            ], 500);
        }
    }

    public function getDayDetail(Request $request, string $date): JsonResponse
    {
        try {
            // 日付形式をバリデーション
            if (!Carbon::hasFormat($date, 'Y-m-d')) {
                return response()->json([
                    'success' => false,
                    'message' => '日付はYYYY-MM-DD形式で指定してください',
                ], 422);
            }

            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
            
            // 未来の日付や古すぎる日付をチェック
            if ($carbonDate->isFuture() || $carbonDate->isBefore('2020-01-01')) {
                return response()->json([
                    'success' => false,
                    'message' => '指定された日付は無効です',
                ], 422);
            }

            $userId = auth()->id();
            $detail = $this->studyActivityService->getDayDetail($userId, $date);

            return response()->json([
                'success' => true,
                'data' => $detail,
            ]);

        } catch (\Exception $e) {
            \Log::error('日別詳細取得エラー', [
                'user_id' => auth()->id(),
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '詳細データの取得に失敗しました',
            ], 500);
        }
    }

    public function getStreakInfo(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();
            $currentYear = now()->year;
            
            // 現在年のデータを取得してストリーク情報を抽出
            $grassData = $this->studyActivityService->getGrassData($userId, $currentYear);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_streak' => $grassData['totals']['current_streak'],
                    'longest_streak' => $grassData['totals']['longest_streak'],
                    'total_active_days' => $grassData['totals']['active_days'],
                    'year' => $currentYear,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('ストリーク情報取得エラー', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'ストリーク情報の取得に失敗しました',
            ], 500);
        }
    }
}
```

### 3.3 ルート定義

```php
<?php
// routes/api.php

// 既存のルートに追加
Route::prefix('study-activity')->middleware('auth:sanctum')->group(function () {
    Route::get('/grass-data', [StudyActivityController::class, 'getGrassData']);
    Route::get('/monthly-stats/{year}/{month}', [StudyActivityController::class, 'getMonthlyStats']);
    Route::get('/day-detail/{date}', [StudyActivityController::class, 'getDayDetail']);
    Route::get('/streak-info', [StudyActivityController::class, 'getStreakInfo']);
});
```

---

## 4. フロントエンド実装詳細

### 4.1 useStudyActivity.js（Composable）

```javascript
// resources/js/composables/useStudyActivity.js
import { ref, reactive, computed } from 'vue'
import axios from 'axios'
import ErrorHandler from '@/utils/errorHandler'
import { ApiRetry } from '@/utils/apiRetry'

export function useStudyActivity() {
  const grassData = ref([])
  const yearlyStats = reactive({
    total_study_minutes: 0,
    active_days: 0,
    longest_streak: 0,
    current_streak: 0
  })
  const loading = ref(false)
  const error = ref(null)

  const apiRetry = new ApiRetry(3, 1000)

  const fetchGrassData = async (year) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await apiRetry.execute(
        () => axios.get(`/api/study-activity/grass-data?year=${year}`),
        'fetchGrassData'
      )
      
      if (response.data.success) {
        grassData.value = response.data.data.days
        Object.assign(yearlyStats, response.data.data.totals)
      } else {
        throw new Error(response.data.message || '草データの取得に失敗しました')
      }
    } catch (err) {
      const errorInfo = ErrorHandler.handle(err, 'fetchGrassData')
      error.value = errorInfo.message
      
      // エラー時は空のデータを設定
      grassData.value = []
      Object.assign(yearlyStats, {
        total_study_minutes: 0,
        active_days: 0,
        longest_streak: 0,
        current_streak: 0
      })
    } finally {
      loading.value = false
    }
  }

  const fetchMonthlyStats = async (year, month) => {
    try {
      const response = await apiRetry.execute(
        () => axios.get(`/api/study-activity/monthly-stats/${year}/${month}`),
        'fetchMonthlyStats'
      )
      
      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || '月別統計の取得に失敗しました')
      }
    } catch (err) {
      ErrorHandler.handle(err, 'fetchMonthlyStats')
      throw err
    }
  }

  const fetchDayDetail = async (date) => {
    try {
      const response = await apiRetry.execute(
        () => axios.get(`/api/study-activity/day-detail/${date}`),
        'fetchDayDetail'
      )
      
      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || '日別詳細の取得に失敗しました')
      }
    } catch (err) {
      ErrorHandler.handle(err, 'fetchDayDetail')
      throw err
    }
  }

  const fetchStreakInfo = async () => {
    try {
      const response = await apiRetry.execute(
        () => axios.get('/api/study-activity/streak-info'),
        'fetchStreakInfo'
      )
      
      if (response.data.success) {
        return response.data.data
      } else {
        throw new Error(response.data.message || 'ストリーク情報の取得に失敗しました')
      }
    } catch (err) {
      ErrorHandler.handle(err, 'fetchStreakInfo')
      throw err
    }
  }

  // 計算されたプロパティ
  const totalHours = computed(() => Math.floor(yearlyStats.total_study_minutes / 60))
  const averageMinutesPerActiveDay = computed(() => {
    return yearlyStats.active_days > 0 
      ? Math.round(yearlyStats.total_study_minutes / yearlyStats.active_days)
      : 0
  })

  return {
    // データ
    grassData,
    yearlyStats,
    loading,
    error,
    
    // 計算されたプロパティ
    totalHours,
    averageMinutesPerActiveDay,
    
    // メソッド
    fetchGrassData,
    fetchMonthlyStats,
    fetchDayDetail,
    fetchStreakInfo
  }
}
```

### 4.2 GrassCalendarUtils.js（カレンダー構築ロジック）

```javascript
// resources/js/utils/GrassCalendarUtils.js
import { startOfYear, endOfYear, startOfWeek, endOfWeek, eachDayOfInterval, format, isWithinInterval } from 'date-fns'
import { ja } from 'date-fns/locale'

export function buildCalendarWeeks(grassData, year) {
  const yearStart = startOfYear(new Date(year, 0, 1))
  const yearEnd = endOfYear(new Date(year, 0, 1))
  
  // 年の開始週の日曜日から年の終了週の土曜日まで
  const calendarStart = startOfWeek(yearStart, { weekStartsOn: 0 }) // 日曜日開始
  const calendarEnd = endOfWeek(yearEnd, { weekStartsOn: 0 })
  
  // 全ての日付を生成
  const allDays = eachDayOfInterval({ start: calendarStart, end: calendarEnd })
  
  // 草データをマップに変換（高速検索用）
  const grassDataMap = new Map()
  grassData.forEach(day => {
    grassDataMap.set(day.date, day)
  })
  
  // 週ごとにグループ化
  const weeks = []
  let currentWeek = []
  let weekNumber = 0
  
  allDays.forEach((date, index) => {
    const dateStr = format(date, 'yyyy-MM-dd')
    const isInYear = isWithinInterval(date, { start: yearStart, end: yearEnd })
    
    // 草データを取得、なければデフォルト値
    const dayData = grassDataMap.get(dateStr) || {
      date: dateStr,
      total_minutes: 0,
      level: 0,
      study_session_minutes: 0,
      pomodoro_minutes: 0,
      session_count: 0,
      focus_sessions: 0
    }
    
    // 年外の日付は表示を薄くする
    const dayItem = {
      ...dayData,
      isInYear,
      dayOfWeek: date.getDay(),
      isToday: dateStr === format(new Date(), 'yyyy-MM-dd')
    }
    
    currentWeek.push(dayItem)
    
    // 土曜日または最後の日の場合、週を確定
    if (date.getDay() === 6 || index === allDays.length - 1) {
      weeks.push({
        weekNumber: weekNumber++,
        days: [...currentWeek],
        monthLabel: getMonthLabelForWeek(currentWeek, year)
      })
      currentWeek = []
    }
  })
  
  return weeks
}

function getMonthLabelForWeek(weekDays, year) {
  // 週の最初の年内の日付の月を取得
  const firstDayInYear = weekDays.find(day => day.isInYear)
  if (!firstDayInYear) return null
  
  const date = new Date(firstDayInYear.date)
  const monthStart = new Date(year, date.getMonth(), 1)
  
  // 月の最初の週の場合のみラベルを表示
  if (date.getDate() <= 7) {
    return format(monthStart, 'MMM', { locale: ja })
  }
  
  return null
}

export function getGrassLevelClass(level, isInYear = true) {
  const baseClasses = 'w-3 h-3 rounded-sm'
  
  if (!isInYear) {
    return `${baseClasses} bg-gray-100 opacity-30`
  }
  
  switch (level) {
    case 0: return `${baseClasses} bg-gray-200 hover:bg-gray-300`
    case 1: return `${baseClasses} bg-green-200 hover:bg-green-300`
    case 2: return `${baseClasses} bg-green-400 hover:bg-green-500`
    case 3: return `${baseClasses} bg-green-600 hover:bg-green-700`
    default: return `${baseClasses} bg-gray-200`
  }
}

export function formatTooltipContent(dayData) {
  const date = new Date(dayData.date)
  const formattedDate = format(date, 'yyyy年M月d日(E)', { locale: ja })
  
  if (dayData.total_minutes === 0) {
    return `${formattedDate}\n学習記録なし`
  }
  
  const hours = Math.floor(dayData.total_minutes / 60)
  const minutes = dayData.total_minutes % 60
  const timeStr = hours > 0 ? `${hours}時間${minutes}分` : `${minutes}分`
  
  let content = `${formattedDate}\n学習時間: ${timeStr}`
  
  if (dayData.study_session_minutes > 0 || dayData.pomodoro_minutes > 0) {
    content += '\n\n内訳:'
    if (dayData.study_session_minutes > 0) {
      content += `\n• 学習セッション: ${dayData.study_session_minutes}分`
    }
    if (dayData.pomodoro_minutes > 0) {
      content += `\n• ポモドーロ: ${dayData.pomodoro_minutes}分 (${dayData.focus_sessions}セッション)`
    }
  }
  
  return content
}
```

### 4.3 StudyGrassChart.vue（メインコンポーネント）

```vue
<!-- resources/js/components/StudyGrassChart.vue -->
<template>
  <div class="study-grass-chart bg-white rounded-lg shadow-sm p-6" data-cy="grass-chart">
    <!-- エラー表示 -->
    <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md" data-cy="error-message">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">エラーが発生しました</h3>
          <div class="mt-2 text-sm text-red-700">{{ error }}</div>
          <div class="mt-4">
            <button @click="retryFetch" class="bg-red-100 hover:bg-red-200 text-red-800 px-3 py-2 rounded text-sm" data-cy="retry-button">
              再試行
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ヘッダー -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6 gap-4">
      <h2 class="text-xl font-bold text-gray-800">学習アクティビティ</h2>
      <YearNavigator 
        :current-year="selectedYear" 
        :loading="loading"
        @year-changed="handleYearChange"
        data-cy="year-selector"
      />
    </div>

    <!-- 統計サマリー -->
    <GrassStats :stats="yearlyStats" :loading="loading" class="mb-6" />

    <!-- 草カレンダー -->
    <div class="relative">
      <GrassCalendar 
        :grass-data="grassData"
        :year="selectedYear"
        :loading="loading"
        @day-click="handleDayClick"
        @day-hover="handleDayHover"
        @day-leave="handleDayLeave"
      />

      <!-- ローディングオーバーレイ -->
      <div v-if="loading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center" data-cy="loading-indicator">
        <div class="flex items-center space-x-2 text-gray-500">
          <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>読み込み中...</span>
        </div>
      </div>
    </div>

    <!-- ツールチップ -->
    <GrassTooltip 
      v-if="hoveredDay && tooltipVisible"
      :day-data="hoveredDay"
      :position="tooltipPosition"
      data-cy="grass-tooltip"
    />

    <!-- 凡例 -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-6 pt-4 border-t border-gray-200">
      <div class="flex items-center text-sm text-gray-600 mb-2 sm:mb-0">
        <span class="mr-2">少ない</span>
        <div class="flex gap-1 mr-2">
          <div class="w-3 h-3 bg-gray-200 rounded-sm"></div>
          <div class="w-3 h-3 bg-green-200 rounded-sm"></div>
          <div class="w-3 h-3 bg-green-400 rounded-sm"></div>
          <div class="w-3 h-3 bg-green-600 rounded-sm"></div>
        </div>
        <span>多い</span>
      </div>
      
      <div class="text-xs text-gray-500">
        クリックで詳細表示 • ホバーで概要表示
      </div>
    </div>

    <!-- 年表示（テスト用） -->
    <div class="hidden" data-cy="year-display">{{ selectedYear }}</div>
  </div>
</template>

<script>
import { ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useStudyActivity } from '@/composables/useStudyActivity'
import GrassCalendar from './GrassCalendar.vue'
import GrassTooltip from './GrassTooltip.vue'
import GrassStats from './GrassStats.vue'
import YearNavigator from './YearNavigator.vue'

export default {
  name: 'StudyGrassChart',
  components: {
    GrassCalendar,
    GrassTooltip,
    GrassStats,
    YearNavigator
  },
  setup() {
    const router = useRouter()
    const selectedYear = ref(new Date().getFullYear())
    const hoveredDay = ref(null)
    const tooltipPosition = ref({ x: 0, y: 0 })
    const tooltipVisible = ref(false)
    
    const { 
      grassData, 
      yearlyStats, 
      loading, 
      error,
      fetchGrassData 
    } = useStudyActivity()
    
    const handleYearChange = async (year) => {
      selectedYear.value = year
      await fetchGrassData(year)
    }
    
    const handleDayClick = (dayData) => {
      if (dayData.total_minutes > 0) {
        // 詳細画面へ遷移
        router.push({
          name: 'study-detail',
          params: { date: dayData.date }
        })
      }
    }
    
    const handleDayHover = async (dayData, event) => {
      hoveredDay.value = dayData
      tooltipPosition.value = {
        x: event.clientX + 10,
        y: event.clientY - 10
      }
      
      // 少し遅延してツールチップを表示（誤操作防止）
      await nextTick()
      setTimeout(() => {
        if (hoveredDay.value === dayData) {
          tooltipVisible.value = true
        }
      }, 300)
    }
    
    const handleDayLeave = () => {
      hoveredDay.value = null
      tooltipVisible.value = false
    }

    const retryFetch = async () => {
      await fetchGrassData(selectedYear.value)
    }
    
    onMounted(async () => {
      await fetchGrassData(selectedYear.value)
    })
    
    return {
      selectedYear,
      grassData,
      yearlyStats,
      loading,
      error,
      hoveredDay,
      tooltipPosition,
      tooltipVisible,
      handleYearChange,
      handleDayClick,
      handleDayHover,
      handleDayLeave,
      retryFetch
    }
  }
}
</script>
```

### 4.4 GrassCalendar.vue（カレンダーグリッド）

```vue
<!-- resources/js/components/GrassCalendar.vue -->
<template>
  <div class="grass-calendar">
    <!-- 月ラベル -->
    <div class="month-labels flex mb-2 text-xs text-gray-500 select-none">
      <div 
        v-for="(week, index) in calendarWeeks" 
        :key="`month-${index}`"
        class="flex-shrink-0 w-3 mr-1 text-center"
      >
        {{ week.monthLabel }}
      </div>
    </div>

    <div class="calendar-container flex">
      <!-- 曜日ラベル -->
      <div class="weekday-labels flex flex-col mr-2 text-xs text-gray-500 select-none">
        <div v-for="(day, index) in weekdays" :key="day" class="h-3 mb-1 flex items-center justify-end pr-1">
          <span v-if="index % 2 === 1">{{ day }}</span>
        </div>
      </div>

      <!-- カレンダーグリッド -->
      <div class="calendar-grid flex gap-1">
        <div v-for="week in calendarWeeks" :key="week.weekNumber" class="week-column flex flex-col gap-1">
          <div
            v-for="day in week.days"
            :key="day.date"
            :class="getDayClass(day)"
            class="day-cell cursor-pointer transition-all duration-200 hover:ring-2 hover:ring-blue-300 hover:ring-opacity-50"
            :title="getTooltipTitle(day)"
            :data-cy="`grass-day`"
            :data-minutes="day.total_minutes"
            @click="handleDayClick(day)"
            @mouseenter="handleDayHover($event, day)"
            @mouseleave="$emit('day-leave')"
          >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'
import { buildCalendarWeeks, getGrassLevelClass, formatTooltipContent } from '@/utils/GrassCalendarUtils'

export default {
  name: 'GrassCalendar',
  props: {
    grassData: {
      type: Array,
      default: () => []
    },
    year: {
      type: Number,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  emits: ['day-click', 'day-hover', 'day-leave'],
  setup(props, { emit }) {
    const weekdays = ['日', '月', '火', '水', '木', '金', '土']
    
    const calendarWeeks = computed(() => {
      if (props.loading) {
        return generateLoadingSkeleton()
      }
      return buildCalendarWeeks(props.grassData, props.year)
    })
    
    const getDayClass = (day) => {
      if (props.loading) {
        return 'w-3 h-3 rounded-sm bg-gray-100 animate-pulse'
      }
      
      let baseClass = getGrassLevelClass(day.level, day.isInYear)
      
      // 今日の場合は境界線を追加
      if (day.isToday) {
        baseClass += ' ring-2 ring-blue-500 ring-opacity-50'
      }
      
      // クリック可能な場合はカーソルを変更
      if (day.total_minutes > 0) {
        baseClass += ' hover:scale-110'
      }
      
      return baseClass
    }
    
    const getTooltipTitle = (day) => {
      if (props.loading) return '読み込み中...'
      return formatTooltipContent(day)
    }
    
    const handleDayClick = (day) => {
      if (!props.loading && day.isInYear) {
        emit('day-click', day)
      }
    }
    
    const handleDayHover = (event, day) => {
      if (!props.loading && day.isInYear) {
        emit('day-hover', day, event)
      }
    }
    
    const generateLoadingSkeleton = () => {
      // 53週分のスケルトンを生成
      const skeleton = []
      for (let week = 0; week < 53; week++) {
        const days = []
        for (let day = 0; day < 7; day++) {
          days.push({
            date: '',
            total_minutes: 0,
            level: 0,
            isInYear: true,
            isToday: false
          })
        }
        skeleton.push({
          weekNumber: week,
          days,
          monthLabel: week === 0 ? '読み込み中...' : null
        })
      }
      return skeleton
    }
    
    return {
      weekdays,
      calendarWeeks,
      getDayClass,
      getTooltipTitle,
      handleDayClick,
      handleDayHover
    }
  }
}
</script>

<style scoped>
.grass-calendar {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
}

.day-cell {
  min-height: 12px;
  min-width: 12px;
}

.calendar-container {
  overflow-x: auto;
  scrollbar-width: thin;
  scrollbar-color: #d1d5db transparent;
}

.calendar-container::-webkit-scrollbar {
  height: 6px;
}

.calendar-container::-webkit-scrollbar-track {
  background: transparent;
}

.calendar-container::-webkit-scrollbar-thumb {
  background-color: #d1d5db;
  border-radius: 3px;
}

.calendar-container::-webkit-scrollbar-thumb:hover {
  background-color: #9ca3af;
}
</style>
```

---

## 5. エラーハンドリング戦略

### 5.1 ErrorHandler実装

```javascript
// resources/js/utils/errorHandler.js
class ErrorHandler {
  static handle(error, context = '') {
    const errorInfo = {
      message: this.getErrorMessage(error),
      type: this.getErrorType(error),
      context,
      timestamp: new Date().toISOString(),
      userAgent: navigator.userAgent,
    }
    
    // ログ出力
    console.error(`[${context}] エラーが発生:`, errorInfo)
    
    // エラートラッキングサービスに送信（Sentryなど）
    if (window.Sentry) {
      window.Sentry.captureException(error, { extra: errorInfo })
    }
    
    return errorInfo
  }
  
  static getErrorMessage(error) {
    if (error.response?.data?.message) {
      return error.response.data.message
    }
    
    if (error.message) {
      return error.message
    }
    
    if (error.response?.status) {
      return this.getStatusMessage(error.response.status)
    }
    
    return '予期しないエラーが発生しました'
  }
  
  static getErrorType(error) {
    if (!error.response) {
      return 'network'
    }
    
    const status = error.response.status
    if (status >= 400 && status < 500) {
      return 'client'
    }
    
    if (status >= 500) {
      return 'server'
    }
    
    return 'unknown'
  }
  
  static getStatusMessage(status) {
    const messages = {
      400: 'リクエストが無効です',
      401: 'ログインが必要です',
      403: 'アクセス権限がありません', 
      404: 'データが見つかりません',
      422: '入力内容に誤りがあります',
      429: 'リクエストが多すぎます。少し待ってから再試行してください',
      500: 'サーバーエラーが発生しました',
      502: 'サーバーに接続できません',
      503: 'サービスが一時的に利用できません',
    }
    
    return messages[status] || `エラーが発生しました (${status})`
  }
  
  static isRetryable(error) {
    if (!error.response) {
      return true // ネットワークエラーは再試行可能
    }
    
    const status = error.response.status
    return status === 429 || status >= 500
  }
}

export default ErrorHandler
```

### 5.2 APIリトライ機能

```javascript
// resources/js/utils/apiRetry.js
export class ApiRetry {
  constructor(maxRetries = 3, baseDelay = 1000) {
    this.maxRetries = maxRetries
    this.baseDelay = baseDelay
  }
  
  async execute(apiCall, context = '') {
    let lastError
    
    for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
      try {
        return await apiCall()
      } catch (error) {
        lastError = error
        
        if (attempt === this.maxRetries || !this.shouldRetry(error)) {
          throw error
        }
        
        const delay = this.calculateDelay(attempt)
        console.warn(`[${context}] 再試行 ${attempt + 1}/${this.maxRetries} (${delay}ms後)`)
        
        await this.sleep(delay)
      }
    }
    
    throw lastError
  }
  
  shouldRetry(error) {
    // ネットワークエラー
    if (!error.response) {
      return true
    }
    
    const status = error.response.status
    
    // 5xx系エラーまたは429（レート制限）
    return status >= 500 || status === 429
  }
  
  calculateDelay(attempt) {
    // エクスポネンシャルバックオフ + ジッター
    const exponentialDelay = this.baseDelay * Math.pow(2, attempt)
    const jitter = Math.random() * 1000
    return exponentialDelay + jitter
  }
  
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms))
  }
}
```

---

## 6. パフォーマンス最適化

### 6.1 データベース最適化

```sql
-- インデックス最適化
CREATE INDEX CONCURRENTLY idx_daily_study_summaries_user_date_grass 
ON daily_study_summaries(user_id, study_date DESC, grass_level);

CREATE INDEX CONCURRENTLY idx_study_sessions_user_date_duration 
ON study_sessions(user_id, started_at::date, duration_minutes) 
WHERE ended_at IS NOT NULL;

CREATE INDEX CONCURRENTLY idx_pomodoro_sessions_user_date_focus 
ON pomodoro_sessions(user_id, started_at::date, actual_duration) 
WHERE session_type = 'focus' AND is_completed = true;
```

### 6.2 キャッシュサービス

```php
<?php
// app/Services/GrassCacheService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class GrassCacheService
{
    private const CACHE_TTL = 3600; // 1時間
    private const CACHE_PREFIX = 'grass:';
    
    public function getGrassData(int $userId, int $year): ?array
    {
        $key = $this->getCacheKey('data', $userId, $year);
        return Cache::get($key);
    }
    
    public function setGrassData(int $userId, int $year, array $data): void
    {
        $key = $this->getCacheKey('data', $userId, $year);
        Cache::put($key, $data, self::CACHE_TTL);
        
        // タグ付きキャッシュで部分削除を可能に
        Cache::tags(["user:{$userId}", "grass:data"])->put($key, $data, self::CACHE_TTL);
    }
    
    public function invalidateUserCache(int $userId): void
    {
        // ユーザーの全キャッシュを削除
        Cache::tags(["user:{$userId}"])->flush();
        
        // 個別キーも削除（フォールバック）
        $currentYear = now()->year;
        for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
            $key = $this->getCacheKey('data', $userId, $year);
            Cache::forget($key);
        }
    }
    
    public function warmUpCache(int $userId, int $year): void
    {
        // バックグラウンドでキャッシュを事前に生成
        dispatch(function () use ($userId, $year) {
            app(StudyActivityService::class)->getGrassData($userId, $year);
        })->onQueue('cache-warmup');
    }
    
    private function getCacheKey(string $type, int $userId, int $year): string
    {
        return self::CACHE_PREFIX . "{$type}:{$userId}:{$year}";
    }
}
```

---

## 7. テスト戦略

### 7.1 単体テスト例

```php
<?php
// tests/Unit/Services/StudyActivityServiceTest.php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\StudyActivityService;
use App\Models\StudySession;
use App\Models\PomodoroSession;
use App\Models\DailyStudySummary;
use App\Models\User;
use App\Models\SubjectArea;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudyActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private StudyActivityService $service;
    private User $user;
    private SubjectArea $subjectArea;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new StudyActivityService();
        $this->user = User::factory()->create();
        $this->subjectArea = SubjectArea::factory()->create();
    }

    /** @test */
    public function 学習セッション完了時に日別サマリーが正しく更新される()
    {
        // Arrange
        $studySession = StudySession::factory()->create([
            'user_id' => $this->user->id,
            'subject_area_id' => $this->subjectArea->id,
            'started_at' => '2025-07-24 10:00:00',
            'ended_at' => '2025-07-24 11:30:00',
            'duration_minutes' => 90,
        ]);

        // Act
        $summary = $this->service->updateDailySummaryFromStudySession($studySession);

        // Assert
        $this->assertInstanceOf(DailyStudySummary::class, $summary);
        $this->assertEquals($this->user->id, $summary->user_id);
        $this->assertEquals('2025-07-24', $summary->study_date->format('Y-m-d'));
        $this->assertEquals(90, $summary->study_session_minutes);
        $this->assertEquals(0, $summary->pomodoro_minutes);
        $this->assertEquals(90, $summary->total_minutes);
        $this->assertEquals(2, $summary->grass_level); // 61-120分 = レベル2
    }
}
```

### 7.2 統合テスト例

```php
<?php
// tests/Feature/Api/StudyActivityControllerTest.php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\DailyStudySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StudyActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function 草表示データを正常に取得できる()
    {
        // Arrange
        DailyStudySummary::factory()->create([
            'user_id' => $this->user->id,
            'study_date' => '2025-07-24',
            'total_minutes' => 90,
            'grass_level' => 2,
        ]);

        // Act
        $response = $this->getJson('/api/study-activity/grass-data?year=2025');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'year',
                    'days' => [
                        '*' => [
                            'date',
                            'total_minutes',
                            'level',
                            'study_session_minutes',
                            'pomodoro_minutes',
                            'session_count',
                            'focus_sessions',
                        ]
                    ],
                    'totals' => [
                        'total_study_minutes',
                        'active_days',
                        'longest_streak',
                        'current_streak',
                    ]
                ]
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(2025, $response->json('data.year'));
    }
}
```

### 7.3 E2Eテスト例

```javascript
// cypress/e2e/grass-display.cy.js
describe('草表示機能', () => {
  beforeEach(() => {
    // ログイン処理
    cy.login('test@example.com', 'password')
    
    // テストデータ作成
    cy.exec('php artisan db:seed --class=GrassDisplayTestSeeder')
  })

  it('草表示が正常に動作する', () => {
    cy.visit('/dashboard')
    
    // 草表示コンポーネントが表示される
    cy.get('[data-cy="grass-chart"]').should('be.visible')
    
    // 学習データがある日付をクリック
    cy.get('[data-cy="grass-day"][data-minutes="90"]').first().click()
    
    // 詳細画面に遷移
    cy.url().should('include', '/study-detail/')
    cy.get('[data-cy="day-detail"]').should('be.visible')
  })

  it('ツールチップが正常に表示される', () => {
    cy.visit('/dashboard')
    
    // 学習データがある日付にホバー
    cy.get('[data-cy="grass-day"][data-minutes="90"]').first().trigger('mouseenter')
    
    // ツールチップが表示される
    cy.get('[data-cy="grass-tooltip"]').should('be.visible')
    cy.get('[data-cy="grass-tooltip"]').should('contain', '90分')
  })
})
```

---

## 8. 実装手順

### Phase 1: データベース拡張（1-2日）

1. **マイグレーション作成・実行**
   ```bash
   php artisan make:migration add_grass_display_columns_to_daily_study_summaries_table --table=daily_study_summaries
   php artisan migrate
   ```

2. **データ移行コマンド作成・実行**
   ```bash
   php artisan make:command MigrateGrassDisplayData
   php artisan grass:migrate-data --dry-run
   php artisan grass:migrate-data
   ```

3. **DailyStudySummaryモデル拡張**
   - fillable属性追加
   - 新メソッド実装
   - テスト作成・実行

### Phase 2: バックエンドAPI実装（2-3日）

1. **StudyActivityService実装**
   - 草データ構築ロジック
   - キャッシュ機能
   - ストリーク計算

2. **StudyActivityController実装**
   - 各エンドポイント実装
   - バリデーション
   - エラーハンドリング

3. **ルート定義**
   - API routes追加
   - ミドルウェア設定

4. **テスト実装・実行**
   ```bash
   php artisan test tests/Unit/Services/StudyActivityServiceTest.php
   php artisan test tests/Feature/Api/StudyActivityControllerTest.php
   ```

### Phase 3: フロントエンド実装（3-4日）

1. **ユーティリティ実装**
   - ErrorHandler
   - ApiRetry
   - GrassCalendarUtils

2. **Composable実装**
   - useStudyActivity.js

3. **コンポーネント実装**
   - StudyGrassChart.vue
   - GrassCalendar.vue
   - GrassTooltip.vue
   - GrassStats.vue
   - YearNavigator.vue

4. **テスト実装・実行**
   ```bash
   npm run test:unit
   npm run test:e2e
   ```

### Phase 4: 統合・最適化（1-2日）

1. **パフォーマンス最適化**
   - インデックス追加
   - キャッシュ実装
   - クエリ最適化

2. **エラーハンドリング改善**
   - ログ設定
   - 監視設定

3. **本番デプロイ準備**
   - 環境設定
   - セキュリティチェック

### Phase 5: テスト・リリース（1日）

1. **統合テスト実行**
2. **パフォーマンステスト実行**  
3. **ユーザー受け入れテスト**
4. **本番リリース**

**総期間：7-12日**

---

## 9. 注意事項・制約

### 9.1 技術的制約

- Laravel 12 + Vue.js 3 + TailwindCSS 4.0
- SQLiteデータベース（開発環境）
- 既存のDailyStudySummaryテーブル構造を活用
- パフォーマンスを重視した設計

### 9.2 データ制約

- 過去データの整合性保持
- 学習時間の合算ルール厳守
- ユーザー個別のデータ分離

### 9.3 UI/UX制約

- GitHubライクなデザイン維持
- レスポンシブ対応
- アクセシビリティ配慮

---

## 10. 今後の拡張予定

- 科目別草表示
- 週/月単位表示切り替え
- 学習目標との連携
- データエクスポート機能
- リアルタイム更新
- モバイルアプリ対応

---

*この実装詳細設計書は、GitHub草表示機能の完全な実装ガイドラインです。各フェーズで適切にテストを実行し、段階的に機能を追加してください。*