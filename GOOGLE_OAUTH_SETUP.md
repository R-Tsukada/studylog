# Google OAuth 設定手順

## Google Cloud Console設定

### 1. Google Cloud Consoleにアクセス
https://console.cloud.google.com/

### 2. プロジェクトを作成または選択

### 3. OAuth同意画面の設定
1. 「API とサービス」→「OAuth 同意画面」
2. 「外部」を選択して「作成」
3. 必要情報を入力：
   - アプリ名: 「資格学習アプリ」
   - ユーザーサポートメール: あなたのメールアドレス
   - デベロッパーの連絡先情報: あなたのメールアドレス

### 4. 認証情報の作成
1. 「API とサービス」→「認証情報」
2. 「+ 認証情報を作成」→「OAuth 2.0 クライアント ID」
3. アプリケーションの種類: 「ウェブアプリケーション」
4. 名前: 「Study App Web Client」
5. 承認済みのリダイレクト URI:
   ```
   http://127.0.0.1:8001/api/auth/google/callback
   http://localhost:8001/api/auth/google/callback
   ```

### 5. クライアントIDとシークレットをコピー

## Laravel設定

### .envファイルに追加
```bash
# Google OAuth設定
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=http://127.0.0.1:8001/api/auth/google/callback
```

## 一時的な設定（テスト用）

Google OAuth設定が完了するまで、以下のダミー設定で動作確認できます：

```bash
# .envファイルに追加（テスト用）
GOOGLE_CLIENT_ID=dummy_client_id
GOOGLE_CLIENT_SECRET=dummy_client_secret
GOOGLE_REDIRECT_URI=http://127.0.0.1:8001/api/auth/google/callback
```

この設定では実際のGoogle認証は動作しませんが、エラーメッセージが改善されます。