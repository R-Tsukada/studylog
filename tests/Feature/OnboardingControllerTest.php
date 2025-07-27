<?php

namespace Tests\Feature;

use App\Models\OnboardingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_status_returns_correct_data_for_new_user(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'should_show' => true,
                    'completed_at' => null,
                    'skipped' => false,
                ],
            ]);
    }

    public function test_status_returns_false_for_completed_user(): void
    {
        $this->user->update(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'should_show' => false,
                ],
            ]);
    }

    public function test_update_progress_saves_correctly(): void
    {
        $data = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['exam_type' => 'JSTQB'],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $progress = $this->user->onboarding_progress;

        $this->assertEquals(2, $progress['current_step']);
        $this->assertEquals([1], $progress['completed_steps']);
        $this->assertEquals(['exam_type' => 'JSTQB'], $progress['step_data']);
    }

    public function test_complete_marks_user_as_completed(): void
    {
        $data = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
        $this->assertFalse($this->user->onboarding_skipped);

        // ログ記録確認
        $this->assertDatabaseHas('onboarding_logs', [
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_COMPLETED,
        ]);
    }

    public function test_skip_marks_user_as_skipped(): void
    {
        $data = [
            'current_step' => 2,
            'reason' => 'user_choice',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/skip', $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
        $this->assertTrue($this->user->onboarding_skipped);

        // ログ記録確認
        $this->assertDatabaseHas('onboarding_logs', [
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_SKIPPED,
            'step_number' => 2,
        ]);
    }

    public function test_validation_errors_are_handled_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['current_step'],
            ]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/onboarding/status');

        $response->assertStatus(401);
    }
}
