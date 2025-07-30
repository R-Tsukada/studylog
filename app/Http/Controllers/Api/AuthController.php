<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserProfileUpdateRequest;
use App\Http\Requests\UserRegistrationRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    /**
     * ユーザー登録
     */
    public function register(UserRegistrationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $user = $this->authService->createUser($validated);
            $token = $this->authService->generateAuthToken($user);

            return $this->authService->createAuthResponse(
                $user,
                $token,
                'ユーザー登録が完了しました',
                201
            );

        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->authService->createErrorResponse(
                'ユーザー登録中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                500
            );
        }
    }

    /**
     * ユーザーログイン
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email:rfc|max:255',
                'password' => 'required|string|min:8|max:255',
            ], [
                'email.required' => 'メールアドレスは必須です',
                'email.email' => '正しいメールアドレス形式で入力してください',
                'password.required' => 'パスワードは必須です',
                'password.min' => 'パスワードは8文字以上で入力してください',
            ]);

            $user = $this->authService->authenticateUser(
                $validated['email'],
                $validated['password']
            );

            if (! $user) {
                return $this->authService->createErrorResponse(
                    'メールアドレスまたはパスワードが間違っています',
                    401
                );
            }

            $token = $this->authService->generateAuthToken($user);

            return $this->authService->createAuthResponse(
                $user,
                $token,
                'ログインしました'
            );

        } catch (ValidationException $e) {
            return $this->authService->createValidationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('User login failed', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->authService->createErrorResponse(
                'ログイン中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                500
            );
        }
    }

    /**
     * ログアウト
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'ログアウトしました',
            ]);

        } catch (\Exception $e) {
            Log::error('User logout failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->authService->createErrorResponse(
                'ログアウト中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                500
            );
        }
    }

    /**
     * ユーザー情報取得
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'user' => new UserResource($user),
            ]);

        } catch (\Exception $e) {
            Log::error('User info retrieval failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->authService->createErrorResponse(
                'ユーザー情報取得中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                500
            );
        }
    }

    /**
     * プロフィール更新
     */
    public function updateProfile(UserProfileUpdateRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Google認証ユーザーのパスワード変更をブロック
            if ($user->isGoogleUser() && $request->has('password')) {
                return $this->authService->createErrorResponse(
                    'Google認証ユーザーはパスワードを変更できません',
                    422,
                    ['password' => ['Google認証ユーザーはパスワードを設定できません']]
                );
            }

            $validated = $request->validated();

            // パスワードが含まれている場合はハッシュ化
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'プロフィールを更新しました',
                'user' => new UserResource($user),
            ]);

        } catch (ValidationException $e) {
            return $this->authService->createValidationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->authService->createErrorResponse(
                'プロフィール更新中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                500
            );
        }
    }

    /**
     * アカウント削除
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // パスワード確認（Google認証のみのユーザーは除く）
            if ($user->password) {
                $validated = $request->validate([
                    'password' => 'required|string',
                ]);

                if (! $this->authService->validatePassword($user, $validated['password'])) {
                    return $this->authService->createErrorResponse(
                        'パスワードが間違っています',
                        401
                    );
                }
            }

            // 確認メッセージの検証
            $request->validate([
                'confirmation' => 'required|string|in:削除します',
            ]);

            // 関連データの削除（カスケード削除）
            // トークンを削除
            $user->tokens()->delete();

            // ユーザーを削除（他の関連データは外部キー制約で自動削除される）
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'アカウントを削除しました',
            ]);

        } catch (ValidationException $e) {
            return $this->authService->createValidationErrorResponse($e->errors());
        } catch (\Exception $e) {
            Log::error('Account deletion failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->authService->createErrorResponse(
                'アカウント削除中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                500
            );
        }
    }
}
