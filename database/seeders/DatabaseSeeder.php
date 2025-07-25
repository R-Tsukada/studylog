<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        // マスターデータのシーディング（優先順位重要）
        $this->call([
            ExamTypeSeeder::class,      // 試験タイプを先に作成
            SubjectAreaSeeder::class,   // その後に分野を作成（外部キー参照のため）
            StudyCalendarSeeder::class, // 学習カレンダー用のテストデータ
        ]);

        // テストユーザーの作成
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'nickname' => 'Test User',
                'email' => 'test@example.com',
            ]);
            $this->command->info('👤 Test user created: test@example.com');
        } else {
            $this->command->info('👤 Test user already exists: test@example.com');
        }

        // 開発環境では追加のテストユーザーも作成
        if (app()->environment('local')) {
            if (User::count() < 5) {
                User::factory(3)->create();
                $this->command->info('👥 Additional test users created for local development');
            }
        }

        $this->command->info('🎉 Database seeding completed successfully!');
    }
}
