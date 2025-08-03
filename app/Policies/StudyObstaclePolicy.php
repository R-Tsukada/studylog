<?php

namespace App\Policies;

use App\Models\StudyObstacle;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudyObstaclePolicy
{
    use HandlesAuthorization;

    /**
     * ユーザーが学習障害一覧を表示できるかを判定
     */
    public function viewAny(User $user): bool
    {
        return true; // 自分のデータのみ表示
    }

    /**
     * ユーザーが特定の学習障害を表示できるかを判定
     */
    public function view(User $user, StudyObstacle $studyObstacle): bool
    {
        // 所有者のみ
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害を作成できるかを判定
     */
    public function create(User $user): bool
    {
        // 作成数制限（DoS攻撃防止）
        $count = $user->studyObstacles()->count();
        return $count < 100; // 1ユーザー最大100個
    }

    /**
     * ユーザーが学習障害を更新できるかを判定
     */
    public function update(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害を削除できるかを判定
     */
    public function delete(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害を管理できるかを判定（汎用メソッド）
     */
    public function manage(User $user, StudyObstacle $studyObstacle): bool
    {
        return $this->update($user, $studyObstacle);
    }

    /**
     * ユーザーが学習障害を解決済みにできるかを判定
     */
    public function markResolved(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害の効果度を評価できるかを判定
     */
    public function rateEffectiveness(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害の発生を記録できるかを判定
     */
    public function recordOccurrence(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害を復元できるかを判定
     */
    public function restore(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }

    /**
     * ユーザーが学習障害を完全削除できるかを判定
     */
    public function forceDelete(User $user, StudyObstacle $studyObstacle): bool
    {
        return $user->id === $studyObstacle->user_id;
    }
}
