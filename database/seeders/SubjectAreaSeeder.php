<?php

namespace Database\Seeders;

use App\Models\ExamType;
use App\Models\SubjectArea;
use Illuminate\Database\Seeder;

class SubjectAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // JSTQB FL の試験タイプを取得
        $jstqbFL = ExamType::where('code', 'jstqb_fl')->first();

        if ($jstqbFL) {
            $jstqbSubjects = [
                [
                    'exam_type_id' => $jstqbFL->id,
                    'code' => 'testing_fundamentals',
                    'name' => 'テストの基礎',
                    'description' => 'ソフトウェアテストの基本概念と原則',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $jstqbFL->id,
                    'code' => 'test_lifecycle',
                    'name' => 'テストライフサイクル',
                    'description' => 'テストプロセスとライフサイクル',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $jstqbFL->id,
                    'code' => 'static_testing',
                    'name' => '静的テスト',
                    'description' => 'レビューと静的解析技法',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $jstqbFL->id,
                    'code' => 'test_techniques',
                    'name' => 'テスト技法',
                    'description' => 'ブラックボックス・ホワイトボックステスト技法',
                    'sort_order' => 4,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $jstqbFL->id,
                    'code' => 'test_management',
                    'name' => 'テストマネジメント',
                    'description' => 'テスト計画、監視、制御',
                    'sort_order' => 5,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $jstqbFL->id,
                    'code' => 'test_tools',
                    'name' => 'テストツール',
                    'description' => 'テスト支援ツールの分類と使用',
                    'sort_order' => 6,
                    'is_active' => true,
                ],
            ];

            foreach ($jstqbSubjects as $subject) {
                SubjectArea::firstOrCreate(
                    [
                        'exam_type_id' => $subject['exam_type_id'],
                        'code' => $subject['code'],
                    ],
                    $subject
                );
            }

            $this->command->info('JSTQB FL SubjectAreas seeded successfully!');
        }

        // 基本情報技術者試験の分野
        $fe = ExamType::where('code', 'fe')->first();

        if ($fe) {
            $feSubjects = [
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'basic_theory',
                    'name' => '基礎理論',
                    'description' => '離散数学、応用数学、情報理論',
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'computer_system',
                    'name' => 'コンピュータシステム',
                    'description' => 'プロセッサ、メモリ、システム構成',
                    'sort_order' => 2,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'technology_elements',
                    'name' => '技術要素',
                    'description' => 'ヒューマンインターフェース、マルチメディア、データベース、ネットワーク、セキュリティ',
                    'sort_order' => 3,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'development_technology',
                    'name' => '開発技術',
                    'description' => 'システム開発技術、ソフトウェア開発管理技術',
                    'sort_order' => 4,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'project_management',
                    'name' => 'プロジェクトマネジメント',
                    'description' => 'プロジェクトマネジメント',
                    'sort_order' => 5,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'service_management',
                    'name' => 'サービスマネジメント',
                    'description' => 'サービスマネジメント、システム監査',
                    'sort_order' => 6,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'system_strategy',
                    'name' => 'システム戦略',
                    'description' => 'システム戦略、システム企画',
                    'sort_order' => 7,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'management_strategy',
                    'name' => '経営戦略',
                    'description' => '経営戦略マネジメント、技術戦略マネジメント、ビジネスインダストリ',
                    'sort_order' => 8,
                    'is_active' => true,
                ],
                [
                    'exam_type_id' => $fe->id,
                    'code' => 'corporate_legal',
                    'name' => '企業と法務',
                    'description' => '企業活動、法務',
                    'sort_order' => 9,
                    'is_active' => true,
                ],
            ];

            foreach ($feSubjects as $subject) {
                SubjectArea::firstOrCreate(
                    [
                        'exam_type_id' => $subject['exam_type_id'],
                        'code' => $subject['code'],
                    ],
                    $subject
                );
            }

            $this->command->info('基本情報技術者試験 SubjectAreas seeded successfully!');
        }
    }
}
