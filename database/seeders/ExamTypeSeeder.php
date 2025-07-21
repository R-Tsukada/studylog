<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExamType;

class ExamTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $examTypes = [
            [
                'code' => 'jstqb_fl',
                'name' => 'JSTQB Foundation Level',
                'description' => 'ソフトウェアテスト技術者資格 Foundation Level',
                'is_active' => true,
            ],
            [
                'code' => 'fe',
                'name' => '基本情報技術者試験',
                'description' => '情報処理技術者試験 基本情報技術者',
                'is_active' => true,
            ],
        ];

        foreach ($examTypes as $examType) {
            ExamType::firstOrCreate(
                ['code' => $examType['code']],
                $examType
            );
        }

        $this->command->info('ExamTypes seeded successfully!');
    }
}
