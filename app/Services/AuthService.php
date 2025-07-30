<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Create a new user.
     */
    public function createUser(array $userData): User
    {
        return User::create([
            'nickname' => $userData['nickname'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Authenticate user with email and password.
     */
    public function authenticateUser(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        // Google認証のみのユーザーの場合はパスワードログイン拒否
        if ($user->isGoogleUser() && ! $user->password) {
            return null;
        }

        return $user;
    }

    /**
     * Generate authentication token for user.
     */
    public function generateAuthToken(User $user): string
    {
        // 既存のトークンを削除
        $user->tokens()->delete();

        // 新しいトークンを生成
        return $user->createToken('auth-token')->plainTextToken;
    }

    /**
     * Create success response with user data and token.
     */
    public function createAuthResponse(User $user, string $token, string $message, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => new UserResource($user),
            'token' => $token,
        ], $statusCode);
    }

    /**
     * Create error response.
     */
    public function createErrorResponse(string $message, int $statusCode = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create validation error response (compatible with existing tests).
     */
    public function createValidationErrorResponse(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'バリデーションエラー',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Validate password for account operations.
     */
    public function validatePassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}
