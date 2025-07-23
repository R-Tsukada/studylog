#!/bin/bash
set -e

# Laravelアプリケーションの初期化
echo "Initializing Laravel application..."

# データベースの初期化（SQLiteの場合のみ）
if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Initializing SQLite database..."
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "Creating SQLite database..."
        touch /var/www/html/database/database.sqlite
    fi
    echo "Setting database permissions..."
    chown -R www-data:www-data /var/www/html/database/
    chmod 775 /var/www/html/database/
    chmod 664 /var/www/html/database/database.sqlite
else
    echo "Using PostgreSQL database connection"
    echo "Waiting for PostgreSQL to be ready..."
    
    # PostgreSQLデータベースの接続テスト（最大60秒待機）
    for i in {1..60}; do
        if php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database connected!'; } catch(Exception \$e) { echo 'Database not ready: ' . \$e->getMessage(); exit(1); }" 2>/dev/null | grep -q "Database connected"; then
            echo "PostgreSQL database is ready!"
            break
        else
            echo "Waiting for database connection... ($i/60)"
            sleep 1
        fi
        
        if [ $i -eq 60 ]; then
            echo "Error: Could not connect to PostgreSQL database after 60 seconds"
            echo "Database connection details:"
            echo "DB_HOST: $DB_HOST"
            echo "DB_PORT: $DB_PORT" 
            echo "DB_DATABASE: $DB_DATABASE"
            echo "DB_USERNAME: $DB_USERNAME"
            exit 1
        fi
    done
fi

# データベースマイグレーション（最優先で実行）
echo "Running database migrations..."
php artisan migrate --force

# キャッシュクリア（マイグレーション後に実行）
echo "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 本番用設定キャッシュ
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# データベースシード（本番環境でも基本データは必要）
if [ -f /var/www/html/database/seeders/DatabaseSeeder.php ]; then
    echo "Running database seeders..."
    php artisan db:seed --class=ExamTypeSeeder --force
    php artisan db:seed --class=SubjectAreaSeeder --force
fi

# ストレージリンク
php artisan storage:link

echo "Laravel application initialized successfully."

# Supervisorを起動
exec "$@"