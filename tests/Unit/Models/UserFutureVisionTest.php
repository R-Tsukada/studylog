<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserFutureVision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFutureVisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_future_vision_can_be_created()
    {
        $user = User::factory()->create();

        $futureVision = UserFutureVision::create([
            'user_id' => $user->id,
            'vision_text' => '資格を取得して、チームリーダーとして活躍したい',
        ]);

        $this->assertDatabaseHas('user_future_visions', [
            'id' => $futureVision->id,
            'user_id' => $user->id,
            'vision_text' => '資格を取得して、チームリーダーとして活躍したい',
        ]);
    }

    public function test_user_future_vision_belongs_to_user()
    {
        $user = User::factory()->create();
        $futureVision = UserFutureVision::create([
            'user_id' => $user->id,
            'vision_text' => 'AWS認定を取得してクラウドエンジニアになる',
        ]);

        $this->assertInstanceOf(User::class, $futureVision->user);
        $this->assertEquals($user->id, $futureVision->user->id);
    }

    public function test_user_has_one_future_vision()
    {
        $user = User::factory()->create();
        $futureVision = UserFutureVision::create([
            'user_id' => $user->id,
            'vision_text' => 'プロジェクトマネージャーとしてチームを成功に導く',
        ]);

        $this->assertInstanceOf(UserFutureVision::class, $user->userFutureVision);
        $this->assertEquals($futureVision->id, $user->userFutureVision->id);
    }

    public function test_user_future_vision_has_correct_fillable_fields()
    {
        $futureVision = new UserFutureVision;
        $expectedFillable = [
            'user_id',
            'vision_text',
        ];

        $this->assertEquals($expectedFillable, $futureVision->getFillable());
    }

    public function test_user_future_vision_has_correct_table_name()
    {
        $futureVision = new UserFutureVision;
        $this->assertEquals('user_future_visions', $futureVision->getTable());
    }

    public function test_user_future_vision_casts_timestamps()
    {
        $user = User::factory()->create();
        $futureVision = UserFutureVision::create([
            'user_id' => $user->id,
            'vision_text' => 'フリーランスエンジニアとして独立する',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $futureVision->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $futureVision->updated_at);
    }
}
