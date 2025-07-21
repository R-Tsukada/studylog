# PHP環境用のDockerfile
FROM php:8.2-fpm

# システムの依存関係をインストール
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    postgresql-client \
    nginx \
    supervisor \
    ca-certificates \
    gnupg \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Node.js 20をインストール（最新LTSバージョン）
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリを設定
WORKDIR /var/www/html

# パッケージファイルをコピーして依存関係をインストール
COPY package*.json ./
RUN npm install

# Laravel artisanファイルを含むすべてのファイルを先にコピー
COPY . .

# Composerで依存関係をインストール
RUN composer install --no-dev --optimize-autoloader --no-interaction

# フロントエンドをビルド
RUN npm run build

# ストレージディレクトリの権限設定
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Nginx設定
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Supervisor設定
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# エントリーポイントスクリプト
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ポートを公開
EXPOSE 80

# エントリーポイントとコマンドを設定
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]