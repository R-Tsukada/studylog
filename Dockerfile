# メモリ効率を重視したDockerfile
FROM php:8.2-fpm

# 必要最小限のシステム依存関係のみインストール
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libpq-dev \
    nginx \
    supervisor \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Node.js 20をインストール（最小構成）
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリを設定
WORKDIR /var/www/html

# 依存関係ファイルのみ先にコピー（レイヤーキャッシュのため）
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Node.js依存関係をインストール
COPY package*.json ./
RUN npm ci --omit=dev

# アプリケーションファイルをコピー
COPY . .

# フロントエンドをビルド（本番環境用）
RUN npm run build && npm cache clean --force

# ストレージディレクトリの権限設定
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# PHP-FPM設定（メモリ最適化）
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

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