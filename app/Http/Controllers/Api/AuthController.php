<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
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
                'nickname' => 'required|string|max:50|min:2',  // ニックネームに変更、長さ制限追加
                'email' => 'required|string|email|max:255|unique:users',
                'password' => ['required', 'confirmed', Password::min(8)],
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
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ],
                'token' => $token
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー登録中にエラーが発生しました',
                'error' => $e->getMessage()
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
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'メールアドレスまたはパスワードが間違っています'
                ], 401);
            }

            // Google認証のみのユーザーの場合はパスワードログイン拒否
            if ($user->isGoogleUser() && !$user->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'このアカウントはGoogleアカウントでログインしてください'
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
                'token' => $token
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ログイン中にエラーが発生しました',
                'error' => $e->getMessage()
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
                'message' => 'ログアウトしました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ログアウト中にエラーが発生しました',
                'error' => $e->getMessage()
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
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー情報取得中にエラーが発生しました',
                'error' => $e->getMessage()
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
            
            $validated = $request->validate([
                'nickname' => 'sometimes|string|max:50|min:2',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'password' => ['sometimes', 'confirmed', Password::min(8)],
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
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'プロフィール更新中にエラーが発生しました',
                'error' => $e->getMessage()
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
            
            // バリデーション：パスワード確認と削除確認テキスト
            $validated = $request->validate([
                'password' => 'required|string',
                'confirmation_text' => 'required|string|in:削除します',
            ], [
                'password.required' => 'パスワードを入力してください',
                'confirmation_text.required' => '削除確認テキストを入力してください',
                'confirmation_text.in' => '削除確認テキストには「削除します」と入力してください'
            ]);

            // パスワード確認
            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'パスワードが間違っています'
                ], 401);
            }

            // Google認証のみのユーザーの場合は削除を拒否
            if ($user->isGoogleUser() && !$user->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Googleアカウント認証のみのユーザーはアカウント削除できません'
                ], 403);
            }

            // トランザクション内で関連データを削除
            \DB::transaction(function () use ($user) {
                // 関連データの削除（外部キー制約に従って順序を考慮）
                
                // 1. ポモドーロセッション削除
                $user->pomodoroSessions()->delete();
                
                // 2. 学習セッション削除  
                $user->studySessions()->delete();
                
                // 3. 学習目標削除
                $user->studyGoals()->delete();
                
                // 4. 日次学習サマリー削除
                $user->dailyStudySummaries()->delete();
                
                // 5. APIトークンを全て削除
                $user->tokens()->delete();
                
                // 6. ユーザー本体を削除
                $user->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'アカウントを削除しました。ご利用ありがとうございました。'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('アカウント削除エラー', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'アカウント削除中にエラーが発生しました。しばらく時間をおいて再度お試しください。',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
