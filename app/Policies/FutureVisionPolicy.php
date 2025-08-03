<?php

namespace App\Policies;

use App\Models\FutureVision;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FutureVisionPolicy
{
    use HandlesAuthorization;

    /**
     * ユーザーが将来像一覧を表示できるかを判定
     */
    public function viewAny(User $user): bool
    {
        return true; // 自分のデータのみ表示
    }

    /**
     * ユーザーが特定の将来像を表示できるかを判定
     */
    public function view(User $user, FutureVision $futureVision): bool
    {
        // 公開データまたは所有者
        return $futureVision->is_public || $user->id === $futureVision->user_id;
    }

    /**
     * ユーザーが将来像を作成できるかを判定
     */
    public function create(User $user): bool
    {
        // 作成数制限（DoS攻撃防止）
        $count = $user->futureVisions()->count();
        return $count < 50; // 1ユーザー最大50個
    }

    /**
     * ユーザーが将来像を更新できるかを判定
     */
    public function update(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    /**
     * ユーザーが将来像を削除できるかを判定
     */
    public function delete(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    /**
     * ユーザーが将来像を管理できるかを判定（汎用メソッド）
     */
    public function manage(User $user, FutureVision $futureVision): bool
    {
        return $this->update($user, $futureVision);
    }

    /**
     * ユーザーが将来像の公開設定を変更できるかを判定
     */
    public function togglePublic(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    /**
     * ユーザーが将来像の優先度を変更できるかを判定
     */
    public function updatePriority(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    /**
     * ユーザーが将来像を復元できるかを判定
     */
    public function restore(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    /**
     * ユーザーが将来像を完全削除できるかを判定
     */
    public function forceDelete(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }
}
