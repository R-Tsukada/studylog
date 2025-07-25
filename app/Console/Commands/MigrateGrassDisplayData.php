<?php

namespace App\Console\Commands;

use App\Models\DailyStudySummary;
use App\Models\PomodoroSession;
use App\Models\StudySession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateGrassDisplayData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grass:migrate-data {--user_id=} {--start_date=} {--end_date=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '既存の学習データを草表示用に移行する';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user_id');
        $startDate = $this->option('start_date') ?? '2024-01-01';
        $endDate = $this->option('end_date') ?? now()->toDateString();
        $dryRun = $this->option('dry-run');

        $this->info('草表示データ移行を開始します...');

        if ($dryRun) {
            $this->warn('*** DRY RUN MODE - 実際の更新は行いません ***');
        }

        $query = DailyStudySummary::whereBetween('study_date', [$startDate, $endDate]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $summaries = $query->get();
        $this->info('対象レコード数: '.$summaries->count());

        if ($summaries->count() === 0) {
            $this->warn('移行対象のレコードが見つかりませんでした。');

            return 0;
        }

        $progressBar = $this->output->createProgressBar($summaries->count());
        $updatedCount = 0;

        DB::beginTransaction();

        try {
            // バッチ処理でN+1問題を回避 - 小さなチャンクに分けて処理
            $summaries->chunk(50)->each(function ($chunk) use (&$updatedCount, $progressBar, $dryRun) {
                foreach ($chunk as $summary) {
                    $updated = $this->migrateDailySummary($summary, $dryRun);
                    if ($updated) {
                        $updatedCount++;
                    }
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine();

            if (! $dryRun) {
                DB::commit();
                $this->info("移行完了: {$updatedCount}件のレコードを更新しました");
            } else {
                DB::rollBack();
                $this->info("DRY RUN完了: {$updatedCount}件のレコードが更新対象です");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('移行中にエラーが発生しました: '.$e->getMessage());

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
            $summary->study_session_minutes !== $studySessionMinutes ||
            $summary->pomodoro_minutes !== $pomodoroMinutes ||
            $summary->total_focus_sessions !== $focusSessions ||
            $summary->grass_level !== $grassLevel ||
            $summary->total_minutes !== $newTotalMinutes
        );

        if ($needsUpdate && ! $dryRun) {
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
        if ($totalMinutes === 0) {
            return 0;
        }
        if ($totalMinutes <= 60) {
            return 1;
        }
        if ($totalMinutes <= 120) {
            return 2;
        }

        return 3;
    }
}
