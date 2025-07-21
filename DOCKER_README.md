# Docker環境での資格学習アプリ

このLaravel + Vue.jsアプリケーションは、DockerおよびRender.comでの本番環境デプロイに対応しています。

## 🚀 Docker環境での起動

### 前提条件
- Docker Desktop がインストール済み
- Docker Compose が利用可能

### 起動コマンド

```bash
# Dockerイメージをビルド
docker-compose build

# アプリケーションを起動（バックグラウンド）
docker-compose up -d

# ログを確認
docker-compose logs -f app
```

### アクセス

- **アプリケーション**: http://localhost:8000
- **ダッシュボード**: http://localhost:8000/dashboard
- **登録画面**: http://localhost:8000/register

## 🏗️ Docker構成

### サービス構成
- **app**: Laravel + Vue.js メインアプリケーション
- **mysql** (オプション): MySQL 8.0 データベース

### 使用技術スタック
- **PHP**: 8.2-fpm
- **Node.js**: 20 LTS
- **Laravel**: 12
- **Vue.js**: 3 (Composition API)
- **Nginx**: 1.22.1
- **SQLite**: デフォルトデータベース
- **Supervisor**: プロセス管理

## 📁 Docker関連ファイル

```
docker/
├── nginx/
│   └── default.conf      # Nginx設定
├── supervisor/
│   └── supervisord.conf  # Supervisorプロセス設定
└── entrypoint.sh         # 起動初期化スクリプト

Dockerfile               # メインDockerfile
docker-compose.yml       # Docker Compose設定
.dockerignore           # Dockerビルド除外ファイル
```

## 🌐 Render.comでのデプロイ

### 設定ファイル
- `render.yaml`: Render.com用デプロイ設定

### デプロイ手順
1. GitHub リポジトリをRender.comに接続
2. `render.yaml` の設定が自動で読み込まれる
3. 自動ビルド・デプロイが実行される

### 本番環境での特徴
- SQLiteデータベース使用
- 自動マイグレーション・シード実行
- Nginx + PHP-FPM + Laravel Queue Worker
- 最適化されたLaravel設定（キャッシュ有効）

## 🔧 開発・運用コマンド

### Docker環境での操作

```bash
# コンテナ内でLaravelコマンド実行
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan test

# コンテナ内のシェルに接続
docker-compose exec app bash

# ログ確認
docker-compose logs app

# アプリケーション停止
docker-compose down

# 完全クリーンアップ（ボリューム含む）
docker-compose down -v
docker system prune -f
```

### データベース操作

```bash
# マイグレーション実行
docker-compose exec app php artisan migrate

# シード実行
docker-compose exec app php artisan db:seed

# データベースリセット
docker-compose exec app php artisan migrate:fresh --seed
```

### MySQL環境での起動（オプション）

```bash
# MySQLサービス付きで起動
docker-compose --profile mysql up -d

# MySQL環境用のデータベース設定
# .env ファイルでDB_CONNECTION=mysqlに変更
```

## 🚨 トラブルシューティング

### よくある問題と解決策

#### ビルドエラー
```bash
# キャッシュクリアして再ビルド
docker-compose down
docker system prune -f
docker-compose build --no-cache
```

#### パーミッションエラー
```bash
# ストレージ権限修正
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

#### データベース関連
```bash
# データベースファイル確認
docker-compose exec app ls -la database/
docker-compose exec app php artisan migrate:status
```

## 📊 パフォーマンス最適化

### 本番環境での最適化項目
- ✅ Laravel設定キャッシュ (config, route, view)
- ✅ Composer autoloader最適化
- ✅ フロントエンドアセット最小化
- ✅ Nginx Gzip圧縮
- ✅ セキュリティヘッダー設定
- ✅ Laravel Queue Worker (バックグラウンドジョブ処理)

## 🔒 セキュリティ

### 実装されているセキュリティ対策
- HTTPS対応（Render.com自動）
- Laravel Sanctum による API認証
- CSRF保護
- セキュリティヘッダー設定
- SQLインジェクション対策（Eloquent ORM）

## 📈 監視・ログ

### ログ出力場所
- **Laravel**: `/var/www/html/storage/logs/`
- **Nginx**: stdout/stderr (docker-compose logsで確認)
- **Supervisor**: `/var/log/supervisor/`

### ヘルスチェック
- Dockerコンテナにヘルスチェック機能内蔵
- 30秒間隔でアプリケーション生存確認