<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * GoogleへのOAuth認証リダイレクト
     * 現在は一時的に無効化されています
     */
    public function redirectToGoogle(): JsonResponse
    {
        // Google認証機能は現在準備中のため無効化
        return response()->json([
            'success' => false,
            'message' => 'Google認証機能は現在準備中です。通常のメール・パスワードでのログインをご利用ください。',
            'available_from' => '近日公開予定'
        ], 503);

        // 以下のコードは将来の実装時に使用予定
        /*
        try {
            // Google OAuth設定チェック
            if (!config('services.google.client_id') || !config('services.google.client_secret')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google認証が設定されていません。管理者にお問い合わせください。'
                ], 503);
            }

            $redirectUrl = Socialite::driver('google')->redirect()->getTargetUrl();
            
            return response()->json([
                'success' => true,
                'redirect_url' => $redirectUrl
            ]);

        } catch (\Exception $e) {
            \Log::error('Google認証初期化エラー:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Google認証の初期化に失敗しました。設定を確認してください。',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
        */
    }

    /**
     * Googleからのコールバック処理
     */
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        try {
            // Google認証からユーザー情報を取得
            $googleUser = Socialite::driver('google')->user();

            // 既存ユーザーをGoogle IDまたはEmailで検索
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                // 既存ユーザーの場合：Google IDが未設定なら設定
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                    ]);
                }
            } else {
                // 新規ユーザーの場合：作成
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => null, // Google認証のみのユーザー
                ]);
            }

            // 既存のトークンを削除
            $user->tokens()->delete();

            // 新しいトークンを生成
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Google認証でログインしました',
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ],
                'token' => $token,
                'is_new_user' => $user->wasRecentlyCreated
            ]);

        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google認証のセッションが無効です。再度お試しください。'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google認証処理中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Google認証でのアカウント連携
     */
    public function linkGoogleAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // 既にGoogle認証が連携済みの場合
            if ($user->isGoogleUser()) {
                return response()->json([
                    'success' => false,
                    'message' => '既にGoogleアカウントと連携済みです'
                ], 400);
            }

            // Google認証からユーザー情報を取得
            $googleUser = Socialite::driver('google')->user();

            // 同じGoogle IDが他のアカウントで使用されていないかチェック
            $existingGoogleUser = User::where('google_id', $googleUser->getId())->first();
            if ($existingGoogleUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'このGoogleアカウントは既に他のアカウントと連携されています'
                ], 400);
            }

            // 現在のユーザーにGoogle情報を連携
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Googleアカウントと連携しました',
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google連携中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Google認証の連携解除
     */
    public function unlinkGoogleAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Google認証が連携されていない場合
            if (!$user->isGoogleUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Googleアカウントと連携されていません'
                ], 400);
            }

            // パスワードが設定されていない場合は連携解除を拒否
            if (!$user->password) {
                return response()->json([
                    'success' => false,
                    'message' => 'パスワードを設定してからGoogle連携を解除してください'
                ], 400);
            }

            // Google情報を削除
            $user->update([
                'google_id' => null,
                'avatar' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Googleアカウントとの連携を解除しました',
                'user' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_google_user' => $user->isGoogleUser(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google連携解除中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
