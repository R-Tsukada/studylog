# 📘 Supabase移行ガイド

## 🎯 概要

SQLiteからSupabaseに移行することで、以下のメリットがあります：

- ✅ **クラウドホスティング**: サーバーレスで自動スケーリング
- ✅ **リアルタイム機能**: WebSocketでのリアルタイム更新
- ✅ **高可用性**: 99.9%のアップタイム保証
- ✅ **バックアップ**: 自動データバックアップ
- ✅ **API自動生成**: RESTとGraphQL API

## 🚀 移行手順

### 1. Supabaseプロジェクト作成

1. **Supabaseにアクセス**: https://supabase.com/dashboard
2. **新しいプロジェクト作成**:
   - Organization: 任意
   - Name: `study-app`
   - Database Password: 強力なパスワードを設定
   - Region: `Northeast Asia (Tokyo)` (日本の場合)

### 2. 接続情報の取得

プロジェクト作成後、以下の情報を取得：

1. **Settings** → **Database** で取得:
   ```
   Host: db.xxxxxxxxxxxxx.supabase.co
   Database name: postgres
   Port: 5432
   User: postgres
   Password: [作成時に設定したパスワード]
   ```

2. **Settings** → **API** で取得:
   ```
   Project URL: https://xxxxxxxxxxxxx.supabase.co
   Project API keys:
   - anon public: eyJ... (フロントエンド用)
   - service_role: eyJ... (サーバーサイド用)
   ```

### 3. Laravel設定の更新

`.env`ファイルを以下のように更新：

```bash
# データベース設定
DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxxxxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=[your-database-password]

# Supabase設定
SUPABASE_URL=https://xxxxxxxxxxxxx.supabase.co
SUPABASE_ANON_KEY=[your-anon-key]
SUPABASE_SERVICE_ROLE_KEY=[your-service-role-key]
```

### 4. マイグレーション実行

```bash
# 既存データベースのクリア（必要に応じて）
php artisan migrate:fresh

# マイグレーション実行
php artisan migrate

# テストデータの投入
php artisan db:seed
```

## 🔧 現在の移行状況

✅ **設定ファイル更新済み**
- .envにSupabase接続情報を追加
- PostgreSQL接続設定に変更

⏳ **実行待ち**
- Supabaseプロジェクトの実際の作成
- 接続情報の実際の値への更新
- マイグレーション実行

## 📝 次のステップ

1. **Supabaseプロジェクト作成**
2. **実際の接続情報に更新**
3. **マイグレーション実行**
4. **接続テスト**

## 🆘 トラブルシューティング

### よくある問題

**1. 接続エラー**
```bash
# 接続テスト
php artisan tinker
DB::connection()->getPdo();
```

**2. SSL接続エラー**
```bash
# SSL設定が必要な場合
DB_SSLMODE=require
```

**3. タイムゾーン設定**
```bash
# config/database.php で設定
'timezone' => '+09:00'
```

## 💡 Supabaseの追加機能

移行後に利用できる機能：

- **Row Level Security**: 行レベルでのセキュリティ
- **Realtime**: リアルタイムデータ更新
- **Storage**: ファイルストレージ
- **Edge Functions**: サーバーレス関数
- **Auth**: 組み込み認証システム