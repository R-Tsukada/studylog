# 認証システム実装完了ガイド

## 概要

Laravel Sanctum + Vue.js 3 を使用したユーザー認証システムが実装されました。
メールアドレス・パスワード認証とGoogle OAuth認証の両方に対応しています。

## 実装内容

### バックエンド（Laravel）

1. **認証モデル**
   - `User` モデルにGoogle ID、アバター画像URLのフィールドを追加
   - Laravel Sanctum統合でAPIトークン認証

2. **認証コントローラー**
   - `AuthController`: 登録、ログイン、ログアウト、プロフィール更新
   - `GoogleAuthController`: Google OAuth認証、アカウント連携

3. **APIエンドポイント**
   - 認証不要: `/api/auth/register`, `/api/auth/login`, `/api/auth/google/*`
   - 認証必要: `/api/user`, `/api/auth/logout`, 全ての学習関連API

4. **ミドルウェア**
   - 既存のAPI (`/api/study-sessions/*`, `/api/dashboard/*`) にauth:sanctumミドルウェア適用

### フロントエンド（Vue.js）

1. **認証UI**
   - ログイン・新規登録フォーム
   - Googleログインボタン
   - 認証状態に応じた画面切り替え

2. **認証状態管理**
   - ローカルストレージでトークン永続化
   - Axios interceptorで自動認証ヘッダー設定
   - ログアウト時のクリーンアップ

## 使用方法

### 開発環境でのテスト

1. **テストユーザーでログイン**
   ```
   メールアドレス: test@example.com
   パスワード: password
   ```

2. **Google OAuth設定（オプション）**
   - Google Cloud Consoleでプロジェクト作成
   - OAuth 2.0クライアント作成
   - .envファイルに設定を追加:
   ```
   GOOGLE_CLIENT_ID=your_client_id
   GOOGLE_CLIENT_SECRET=your_client_secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
   ```

### 本番環境での設定

1. **環境変数設定**
   ```bash
   php artisan key:generate
   # .envファイルでAPP_URL、DB設定、Google OAuth設定を更新
   ```

2. **データベースマイグレーション**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

## セキュリティ機能

- パスワードは8文字以上必須
- トークンベース認証（Laravel Sanctum）
- Google OAuth 2.0連携
- アカウントの連携・連携解除機能
- 認証エラーの適切なハンドリング

## API仕様

### 認証エンドポイント

- `POST /api/auth/register` - 新規登録
- `POST /api/auth/login` - ログイン
- `POST /api/auth/logout` - ログアウト（要認証）
- `GET /api/user` - ユーザー情報取得（要認証）
- `PUT /api/auth/profile` - プロフィール更新（要認証）

### Google OAuth

- `GET /api/auth/google` - Google認証開始
- `GET /api/auth/google/callback` - Google認証コールバック
- `POST /api/auth/google/link` - Google連携（要認証）
- `DELETE /api/auth/google/unlink` - Google連携解除（要認証）

## 注意事項

- 本番環境では適切なHTTPS設定が必要
- Google OAuth使用時はGoogle Cloud Console設定が必要
- 既存のモックデータは認証済みユーザーのデータに変更されます