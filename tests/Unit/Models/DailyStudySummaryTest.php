<?php

namespace Tests\Unit\Models;

use App\Models\DailyStudySummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyStudySummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    /** @test */
    #[Test]
    public function daily_study_summary_has_correct_fillable_attributes()
    {
        $summary = new DailyStudySummary;
        $expected = [
            'user_id',
            'study_date',
            'total_minutes',
            'session_count',
            'subject_breakdown',
            'study_session_minutes',
            'pomodoro_minutes',
            'total_focus_sessions',
            'grass_level',
            'streak_days',
        ];

        $this->assertEquals($expected, $summary->getFillable());
    }

    /** @test */
    #[Test]
    public function daily_study_summary_casts_attributes_correctly()
    {
        $summary = DailyStudySummary::factory()->create([
            'study_date' => '2024-01-01',
            'total_minutes' => '120',
            'session_count' => '3',
            'subject_breakdown' => ['テスト基礎' => 60, 'テスト技法' => 60],
        ]);

        $this->assertInstanceOf(Carbon::class, $summary->study_date);
        $this->assertIsInt($summary->total_minutes);
        $this->assertIsInt($summary->session_count);
        $this->assertIsArray($summary->subject_breakdown);
        $this->assertEquals(120, $summary->total_minutes);
        $this->assertEquals(3, $summary->session_count);
        $this->assertEquals(['テスト基礎' => 60, 'テスト技法' => 60], $summary->subject_breakdown);
    }

    /** @test */
    #[Test]
    public function daily_study_summary_belongs_to_user()
    {
        $user = User::factory()->create();
        $summary = DailyStudySummary::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $summary->user);
        $this->assertEquals($user->id, $summary->user->id);
        $this->assertEquals($user->nickname, $summary->user->nickname);
    }

    /** @test */
    #[Test]
    public function date_range_scope_filters_by_date_range()
    {
        $user = User::factory()->create();
        $startDate = Carbon::now()->subDays(5);
        $endDate = Carbon::now()->subDays(1);

        // 範囲内のサマリー
        DailyStudySummary::factory()->create([
            'user_id' => $user->id,
            'study_date' => $startDate->copy()->addDays(1),
        ]);

        // 範囲外のサマリー
        DailyStudySummary::factory()->create([
            'user_id' => $user->id,
            'study_date' => $startDate->copy()->subDays(1),
        ]);

        $summariesInRange = DailyStudySummary::dateRange($startDate, $endDate)->get();

        $this->assertEquals(1, $summariesInRange->count());
        foreach ($summariesInRange as $summary) {
            $this->assertTrue(
                $summary->study_date->between($startDate, $endDate)
            );
        }
    }

    /** @test */
    #[Test]
    public function by_user_scope_filters_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        DailyStudySummary::factory()->create(['user_id' => $user1->id]);
        DailyStudySummary::factory()->create(['user_id' => $user2->id]);

        $user1Summaries = DailyStudySummary::byUser($user1->id)->get();

        $this->assertGreaterThan(0, $user1Summaries->count());
        foreach ($user1Summaries as $summary) {
            $this->assertEquals($user1->id, $summary->user_id);
        }
    }

    /** @test */
    #[Test]
    public function recent_scope_returns_summaries_in_desc_order()
    {
        $user = User::factory()->create();

        // 複数のサマリーを作成（異なる日付）
        for ($i = 0; $i < 10; $i++) {
            DailyStudySummary::factory()->create([
                'user_id' => $user->id,
                'study_date' => Carbon::now()->subDays($i),
            ]);
        }

        $recentSummaries = DailyStudySummary::recent(5)->get();

        $this->assertCount(5, $recentSummaries);

        // 新しい順にソートされていることを確認
        for ($i = 1; $i < $recentSummaries->count(); $i++) {
            $this->assertTrue(
                $recentSummaries[$i - 1]->study_date >= $recentSummaries[$i]->study_date
            );
        }
    }

    /** @test */
    #[Test]
    public function can_create_daily_study_summary_with_valid_data()
    {
        $user = User::factory()->create();
        $summaryData = [
            'user_id' => $user->id,
            'study_date' => Carbon::today(),
            'total_minutes' => 90,
            'session_count' => 2,
            'subject_breakdown' => [
                'テストの基礎' => 30,
                'テスト技法' => 60,
            ],
        ];

        $summary = DailyStudySummary::create($summaryData);

        $this->assertDatabaseHas('daily_study_summaries', [
            'user_id' => $summaryData['user_id'],
            'study_date' => $summaryData['study_date']->format('Y-m-d 00:00:00'),
            'total_minutes' => $summaryData['total_minutes'],
            'session_count' => $summaryData['session_count'],
        ]);

        $this->assertEquals($summaryData['total_minutes'], $summary->total_minutes);
        $this->assertEquals($summaryData['subject_breakdown'], $summary->subject_breakdown);
    }

    /** @test */
    #[Test]
    public function user_id_and_study_date_combination_must_be_unique()
    {
        $user = User::factory()->create();
        $studyDate = Carbon::today();

        DailyStudySummary::factory()->create([
            'user_id' => $user->id,
            'study_date' => $studyDate,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DailyStudySummary::factory()->create([
            'user_id' => $user->id,
            'study_date' => $studyDate,
        ]);
    }

    /** @test */
    #[Test]
    public function can_handle_null_subject_breakdown()
    {
        $user = User::factory()->create();
        $summary = DailyStudySummary::factory()->create([
            'user_id' => $user->id,
            'subject_breakdown' => null,
        ]);

        $this->assertNull($summary->subject_breakdown);
    }

    /** @test */
    #[Test]
    public function can_handle_empty_subject_breakdown()
    {
        $user = User::factory()->create();
        $summary = DailyStudySummary::factory()->create([
            'user_id' => $user->id,
            'subject_breakdown' => [],
        ]);

        $this->assertIsArray($summary->subject_breakdown);
        $this->assertEmpty($summary->subject_breakdown);
    }
}
