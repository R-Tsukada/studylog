<?php

namespace Database\Seeders;

use App\Models\ExamType;
use App\Models\StudySession;
use App\Models\SubjectArea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StudyCalendarSeeder extends Seeder
{
    /**
     * カレンダー表示用の多様な学習データを作成
     */
    public function run(): void
    {
        // テストユーザーを作成（または既存のものを使用）
        $user = User::firstOrCreate([
            'email' => 'demo@example.com',
        ], [
            'nickname' => 'デモユーザー',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // 試験タイプを作成
        $examTypes = [
            ['name' => 'JSTQB Foundation Level', 'code' => 'jstqb_fl', 'color' => '#3B82F6'],
            ['name' => 'AWS Solutions Architect', 'code' => 'aws_saa', 'color' => '#F59E0B'],
            ['name' => 'Java SE 11 認定', 'code' => 'java_se11', 'color' => '#EF4444'],
        ];

        foreach ($examTypes as $examData) {
            $examType = ExamType::firstOrCreate(
                ['code' => $examData['code']],
                [
                    'name' => $examData['name'],
                    'description' => $examData['name'].'の学習',
                    'is_active' => true,
                    'is_system' => false,
                    'user_id' => $user->id,
                    'color' => $examData['color'],
                    'exam_date' => now()->addMonths(3),
                ]
            );

            // 各試験タイプに学習分野を作成
            $subjects = $this->getSubjectsForExam($examData['code']);
            foreach ($subjects as $subjectName) {
                SubjectArea::firstOrCreate([
                    'exam_type_id' => $examType->id,
                    'name' => $subjectName,
                ], [
                    'code' => strtolower(str_replace(' ', '_', $subjectName)),
                    'description' => $subjectName.'の学習分野',
                    'sort_order' => 1,
                    'is_active' => true,
                    'is_system' => false,
                    'user_id' => $user->id,
                ]);
            }
        }

        // 過去6ヶ月間の学習データを作成（色の濃淡を確認できるパターン）
        $this->createStudyPattern($user);

        $this->command->info('🌱 学習カレンダー用のテストデータを作成しました！');
        $this->command->info('📧 デモユーザー: demo@example.com (パスワード: password)');
    }

    /**
     * リアルな学習パターンを作成
     */
    private function createStudyPattern(User $user): void
    {
        $subjects = SubjectArea::where('user_id', $user->id)->get();
        $endDate = now();
        $startDate = $endDate->copy()->subMonths(6);

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // 学習パターンを決定（現実的な学習習慣を模倣）
            $studyProbability = $this->getStudyProbability($currentDate);

            if (rand(1, 100) <= $studyProbability) {
                $sessionsToday = $this->determineSessionsForDay($currentDate);

                foreach ($sessionsToday as $sessionData) {
                    $subject = $subjects->random();

                    $startTime = $currentDate->copy()->setTime(
                        $sessionData['hour'],
                        rand(0, 59)
                    );

                    StudySession::create([
                        'user_id' => $user->id,
                        'subject_area_id' => $subject->id,
                        'started_at' => $startTime,
                        'ended_at' => $startTime->copy()->addMinutes($sessionData['duration']),
                        'duration_minutes' => $sessionData['duration'],
                        'study_comment' => $this->getRandomStudyComment($subject->name),
                    ]);
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * 日付に応じた学習確率を計算（現実的なパターン）
     */
    private function getStudyProbability(Carbon $date): int
    {
        // 基本確率
        $baseProbability = 60;

        // 曜日による調整
        if ($date->isWeekend()) {
            $baseProbability += 20; // 週末は学習しやすい
        }

        // 月末・月初は忙しいので学習確率下がる
        if ($date->day <= 3 || $date->day >= 28) {
            $baseProbability -= 15;
        }

        // 試験前（仮に毎月15日が模擬試験とする）
        $daysUntilExam = abs($date->day - 15);
        if ($daysUntilExam <= 3) {
            $baseProbability += 30; // 試験前は頑張る
        }

        // 最近の日付ほど学習確率を上げる（継続的な学習）
        $daysFromToday = $date->diffInDays(now());
        if ($daysFromToday <= 30) {
            $baseProbability += 20;
        }

        return min(95, max(10, $baseProbability));
    }

    /**
     * その日の学習セッション数と時間を決定
     */
    private function determineSessionsForDay(Carbon $date): array
    {
        $sessions = [];
        $sessionCount = $this->getRandomSessionCount($date);

        for ($i = 0; $i < $sessionCount; $i++) {
            $sessions[] = [
                'hour' => $this->getRandomStudyHour($i),
                'duration' => $this->getRandomDuration($date),
            ];
        }

        return $sessions;
    }

    /**
     * その日のセッション数をランダムに決定
     */
    private function getRandomSessionCount(Carbon $date): int
    {
        if ($date->isWeekend()) {
            // 週末は長時間学習の可能性
            return rand(1, 3);
        } else {
            // 平日は1-2セッション
            return rand(1, 2);
        }
    }

    /**
     * 学習時間帯を決定
     */
    private function getRandomStudyHour(int $sessionIndex): int
    {
        $timeSlots = [
            [7, 9],   // 朝の学習
            [12, 14], // 昼休み
            [19, 22], // 夜の学習
        ];

        $slot = $timeSlots[$sessionIndex % count($timeSlots)];

        return rand($slot[0], $slot[1]);
    }

    /**
     * 学習時間をランダムに決定（現実的な時間）
     */
    private function getRandomDuration(Carbon $date): int
    {
        if ($date->isWeekend()) {
            // 週末は長めの学習
            return rand(45, 240); // 45分〜4時間
        } else {
            // 平日は短めの学習
            return rand(30, 120); // 30分〜2時間
        }
    }

    /**
     * 学習コメントをランダムに生成
     */
    private function getRandomStudyComment(string $subjectName): string
    {
        $comments = [
            "{$subjectName}の基本概念を復習",
            "{$subjectName}の問題演習を実施",
            "{$subjectName}のドキュメントを読み込み",
            "{$subjectName}について動画学習",
            "{$subjectName}の過去問を解いた",
            "{$subjectName}のまとめノートを作成",
            "{$subjectName}の実習・ハンズオン",
            "{$subjectName}の重要ポイント暗記",
            "{$subjectName}について調べ物",
            "{$subjectName}の模擬試験",
        ];

        return $comments[array_rand($comments)];
    }

    /**
     * 試験タイプごとの学習分野を取得
     */
    private function getSubjectsForExam(string $examCode): array
    {
        $subjects = [
            'jstqb_fl' => [
                'テスト基礎',
                'テスト技法',
                'テスト管理',
                'テスト分析・設計',
                'テスト実装・実行',
                'テスト完了基準',
                'テストツール',
            ],
            'aws_saa' => [
                'EC2・コンピューティング',
                'S3・ストレージ',
                'VPC・ネットワーク',
                'RDS・データベース',
                'IAM・セキュリティ',
                'CloudFormation',
                'モニタリング・ログ',
            ],
            'java_se11' => [
                'Java基本文法',
                'オブジェクト指向',
                'コレクション',
                'ストリーム API',
                '例外処理',
                'モジュールシステム',
                'ラムダ式',
            ],
        ];

        return $subjects[$examCode] ?? ['基本学習'];
    }
}
