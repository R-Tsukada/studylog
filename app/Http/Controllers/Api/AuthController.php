<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ユーザー登録
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nickname' => 'required|string|min:2|max:50|regex:/^[a-zA-Z0-9ぁ-んァ-ンー一-龠]+$/u',
                'email' => 'required|string|email:rfc|max:255|unique:users|ends_with:.com,.net,.org,.jp,.edu,.gov',
                'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            ], [
                'nickname.required' => 'ニックネムは必須です',
                'nickname.min' => 'ニックネムは2文字以上で入力してください',
                'nickname.max' => 'ニックネムは50文字以内で入力してください',
                'nickname.regex' => 'ニックネムは英数字、ひらがな、カタカナ、漢字のみ使用できます',
                'email.required' => 'メールアドレスは必須です',
                'email.email' => '正しいメールアドレス形式で入力してください',
                'email.unique' => 'このメールアドレスは既に登録されています',
                'email.ends_with' => '有効なドメインのメールアドレスを入力してください（.com, .net, .org, .jp, .edu, .gov）',
                'password.required' => 'パスワードは必須です',
                'password.confirmed' => 'パスワード確認が一致しません',
            ]);

            $user = User::create([
                'nickname' => $validated['nickname'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(), // 今回は自動で認証済みとする
            ]);

            // APIトークンを生成
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'ユーザー登録が完了しました',
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ],
                'token' => $token,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー登録中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
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

            $user = User::where('email', $validated['email'])->first();

            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'メールアドレスまたはパスワードが間違っています',
                ], 401);
            }

            // Google認証のみのユーザーの場合はパスワードログイン拒否
            if ($user->isGoogleUser() && ! $user->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'このアカウントはGoogleアカウントでログインしてください',
                ], 401);
            }

            // 既存のトークンを削除
            $user->tokens()->delete();

            // 新しいトークンを生成
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'ログインしました',
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                ],
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ログイン中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
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
            return response()->json([
                'success' => false,
                'message' => 'ログアウト中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
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
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                    'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー情報取得中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * プロフィール更新
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Google認証ユーザーのパスワード変更をブロック
            if ($user->isGoogleUser() && $request->has('password')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google認証ユーザーはパスワードを変更できません',
                    'errors' => [
                        'password' => ['Google認証ユーザーはパスワードを設定できません'],
                    ],
                ], 422);
            }

            $validated = $request->validate([
                'nickname' => 'sometimes|string|min:2|max:50|regex:/^[a-zA-Z0-9ぁ-んァ-ンー一-龠]+$/u',
                'email' => 'sometimes|string|email:rfc|max:255|unique:users,email,'.$user->id.'|ends_with:.com,.net,.org,.jp,.edu,.gov',
                'password' => ['sometimes', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            ], [
                'nickname.min' => 'ニックネムは2文字以上で入力してください',
                'nickname.max' => 'ニックネムは50文字以内で入力してください',
                'nickname.regex' => 'ニックネムは英数字、ひらがな、カタカナ、漢字のみ使用できます',
                'email.email' => '正しいメールアドレス形式で入力してください',
                'email.unique' => 'このメールアドレスは既に登録されています',
                'email.ends_with' => '有効なドメインのメールアドレスを入力してください（.com, .net, .org, .jp, .edu, .gov）',
                'password.confirmed' => 'パスワード確認が一致しません',
            ]);

            // パスワードが含まれている場合はハッシュ化
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'プロフィールを更新しました',
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'プロフィール更新中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
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

                if (! Hash::check($validated['password'], $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'パスワードが間違っています',
                    ], 401);
                }
            }

            // 確認メッセージの検証
            $validated = $request->validate([
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'アカウント削除中にエラーが発生しました',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
