#!/bin/bash
set -e

# Laravelアプリケーションの初期化
echo "Initializing Laravel application..."

# データベースファイルの作成と権限設定
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
fi

echo "Setting database permissions..."
chown -R www-data:www-data /var/www/html/database/
chmod 775 /var/www/html/database/
chmod 664 /var/www/html/database/database.sqlite

# キャッシュクリア
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

# データベースマイグレーション
echo "Running database migrations..."
php artisan migrate --force

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