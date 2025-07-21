# 資格学習アプリ 開発環境構築ガイド

## 📝 概要

本ガイドでは、Laravel + Vue.js で構築する資格学習アプリケーションの開発環境構築手順を記録しています。

### 技術スタック
- **バックエンド**: Laravel 12.20.0
- **フロントエンド**: Vue.js 3
- **ビルドツール**: Vite
- **データベース**: MySQL 9.3.0 / SQLite (開発時)
- **スタイリング**: TailwindCSS
- **パッケージ管理**: Composer (PHP), npm (Node.js)

### 対象環境
- **OS**: macOS (darwin 24.5.0)
- **シェル**: zsh
- **パッケージマネージャー**: Homebrew

## 🛠️ 環境構築手順

### 1. PHP のインストール

Laravel に必要な PHP 8.2 以上をインストールします。

```bash
# PHP 8.4.10 をインストール
brew install php

# バージョン確認
php --version
```

**出力例:**
```
PHP 8.4.10 (cli) (built: Jul  2 2025 02:22:42) (NTS)
Copyright (c) The PHP Group
Built by Homebrew
Zend Engine v4.4.10, Copyright (c) Zend Technologies
    with Zend OPcache v8.4.10, Copyright (c), by Zend Technologies
```

### 2. Composer のインストール

PHP のパッケージ管理ツールをインストールします。

```bash
# Composer をインストール
brew install composer
```

### 3. Node.js のインストール

Vue.js とビルドツールに必要な Node.js をインストールします。

```bash
# Node.js 24.4.1 をインストール
brew install node

# バージョン確認
node --version
npm --version
```

### 4. MySQL のインストールと起動

データベースサーバーをセットアップします。

```bash
# MySQL をインストール
brew install mysql

# MySQL サービスを開始
brew services start mysql
```

**注意**: 本プロジェクトでは開発時は SQLite を使用するため、MySQL は本番環境用の設定です。

### 5. Laravel プロジェクトの作成

資格学習アプリのプロジェクトを作成します。

```bash
# Laravel プロジェクト "study-app" を作成
composer create-project laravel/laravel study-app

# プロジェクトディレクトリに移動
cd study-app
```

### 6. Vue.js の設定

#### 6.1 Vue.js パッケージのインストール

```bash
# Vue.js 3 と Vite プラグインをインストール
npm install vue @vitejs/plugin-vue
```

#### 6.2 Vite 設定ファイルの更新

`vite.config.js` を編集して Vue.js プラグインを追加：

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

#### 6.3 JavaScript エントリーポイントの設定

`resources/js/app.js` を更新：

```javascript
import './bootstrap';

import { createApp } from 'vue';
import App from './App.vue';

const app = createApp(App);

app.mount('#app');
```

#### 6.4 メイン Vue コンポーネントの作成

`resources/js/App.vue` を作成：

```vue
<template>
  <div id="app" class="min-h-screen bg-gray-100">
    <!-- ヘッダー -->
    <header class="bg-blue-600 text-white px-4 py-3">
      <div class="max-w-4xl mx-auto flex justify-between items-center">
        <h1 class="text-xl font-bold">📚 資格学習アプリ</h1>
        <div class="text-sm">
          👤 {{ username }}
        </div>
      </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="max-w-4xl mx-auto p-4">
      <!-- 今日の学習状況 -->
      <section class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">📊 今日の学習状況</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="text-center p-4 bg-green-50 rounded-lg">
            <div class="text-2xl font-bold text-green-600">{{ continuousDays }}</div>
            <div class="text-sm text-gray-600">🔥 連続学習日数</div>
          </div>
          <div class="text-center p-4 bg-blue-50 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">{{ todayStudyTime }}</div>
            <div class="text-sm text-gray-600">⏰ 今日の学習時間</div>
          </div>
          <div class="text-center p-4 bg-purple-50 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">{{ todaySessionCount }}</div>
            <div class="text-sm text-gray-600">📚 今日の学習回数</div>
          </div>
          <div class="text-center p-4 bg-orange-50 rounded-lg">
            <div class="text-2xl font-bold text-orange-600">{{ achievementRate }}%</div>
            <div class="text-sm text-gray-600">🎯 目標達成率</div>
          </div>
        </div>
      </section>

      <!-- クイックスタート -->
      <section class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">🚀 学習を開始</h2>
        <div class="text-center">
          <button 
            @click="startStudy" 
            class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-8 rounded-lg text-lg mb-3 transition-colors duration-200"
          >
            ▶️ 学習開始
          </button>
          <p class="text-sm text-gray-600">
            📝 前回の分野: {{ lastSubject }}
          </p>
        </div>
      </section>

      <!-- 最近の学習 -->
      <section class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-800">📖 最近の学習</h2>
        <div class="space-y-3">
          <div 
            v-for="session in recentSessions" 
            :key="session.id"
            class="flex justify-between items-center p-3 bg-gray-50 rounded-lg"
          >
            <div class="flex items-center">
              <span class="mr-3">{{ session.icon }}</span>
              <div>
                <div class="font-medium">{{ session.subject }}</div>
                <div class="text-sm text-gray-600">{{ session.time }}</div>
              </div>
            </div>
            <div class="text-blue-600 font-semibold">{{ session.duration }}</div>
          </div>
          <div class="text-center">
            <button class="text-blue-600 hover:text-blue-800 text-sm">
              📋 もっと見る →
            </button>
          </div>
        </div>
      </section>
    </main>

    <!-- ボトムナビゲーション -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200">
      <div class="max-w-4xl mx-auto px-4 py-2">
        <div class="flex justify-around">
          <button class="flex flex-col items-center py-2 px-3 text-blue-600">
            <span class="text-xl">🏠</span>
            <span class="text-xs font-medium">ダッシュボード</span>
          </button>
          <button class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-gray-600">
            <span class="text-xl">📊</span>
            <span class="text-xs">統計</span>
          </button>
          <button class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-gray-600">
            <span class="text-xl">📝</span>
            <span class="text-xs">ログ</span>
          </button>
          <button class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-gray-600">
            <span class="text-xl">⚙️</span>
            <span class="text-xs">設定</span>
          </button>
        </div>
      </div>
    </nav>

    <!-- スペーサー（ボトムナビのため） -->
    <div class="h-20"></div>
  </div>
</template>

<script>
export default {
  name: 'App',
  data() {
    return {
      username: 'ユーザー',
      continuousDays: 7,
      todayStudyTime: '45分',
      todaySessionCount: 3,
      achievementRate: 75,
      lastSubject: 'テスト技法',
      recentSessions: [
        {
          id: 1,
          icon: '📖',
          subject: 'テスト技法',
          time: '今日 14:30',
          duration: '30分'
        },
        {
          id: 2,
          icon: '🧪',
          subject: 'テスト管理',
          time: '今日 10:15',
          duration: '15分'
        },
        {
          id: 3,
          icon: '📋',
          subject: 'テストの基礎',
          time: '昨日 20:00',
          duration: '45分'
        }
      ]
    }
  },
  methods: {
    startStudy() {
      alert('学習機能は実装中です！\n\n今は基本的なUI表示のみ動作しています。');
    }
  }
}
</script>

#### 6.5 Laravel ビューファイルの更新

`resources/views/welcome.blade.php` をシンプルなVue.jsマウントポイントに更新：

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title>資格学習アプリ</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    
    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <!-- Vue.js App Mount Point -->
    <div id="app"></div>
</body>
</html>
```

### 7. 開発サーバーの起動

#### 7.1 フロントエンド開発サーバー（Vite）

```bash
# アセットのビルドと監視を開始（バックグラウンド実行）
npm run dev
```

**出力例:**
```
> dev
> vite
  VITE v6.3.5  ready in 679 ms
  ➜  Local:   http://localhost:5173/
  ➜  Network: use --host to expose
  ➜  press h + enter to show help
  LARAVEL v12.20.0  plugin v1.3.0
  ➜  APP_URL: http://localhost
```

#### 7.2 Laravel 開発サーバー

```bash
# Laravel アプリケーションサーバーを起動（バックグラウンド実行）
php artisan serve
```

**出力例:**
```
   INFO  Server running on [http://127.0.0.1:8000].  
  Press Ctrl+C to stop the server
  2025-07-20 17:37:44 / ............................................................ ~ 0.10ms
```

## 🌐 アクセス方法

### 開発環境でのアクセス

ブラウザで以下のURLにアクセス：

```
http://localhost:8000
```

または

```
http://127.0.0.1:8000
```

## ✅ 動作確認

正常に環境構築が完了すると、以下の画面が表示されます：

1. **📱 ヘッダー**: 「📚 資格学習アプリ」のタイトル
2. **📊 今日の学習状況**: 4つの統計カード（連続学習日数、学習時間、回数、達成率）
3. **🚀 クイックスタート**: 大きな「学習開始」ボタン
4. **📖 最近の学習**: 学習履歴のリスト
5. **📍 ボトムナビゲーション**: 4つのナビゲーションボタン

### 現在の機能

- ✅ Vue.js による SPA の基本表示
- ✅ レスポンシブデザイン（モバイルファースト）
- ✅ TailwindCSS によるモダンなUI
- ✅ 基本的なナビゲーション構造
- ⚠️ 学習開始ボタン（現在はデモアラート表示）

## 🔧 トラブルシューティング

### よくある問題と解決方法

#### 1. npm run dev が起動しない

```bash
# node_modules を削除して再インストール
rm -rf node_modules
npm install
npm run dev
```

#### 2. Laravel サーバーが起動しない

```bash
# Composer の依存関係を再インストール
composer install

# アプリケーションキーを生成
php artisan key:generate

# サーバーを再起動
php artisan serve
```

#### 3. Vue コンポーネントが表示されない

- ブラウザのデベロッパーツールでコンソールエラーを確認
- Vite サーバーが起動しているか確認
- `vite.config.js` の設定を確認

#### 4. データベース接続エラー

開発環境では SQLite を使用しているため、通常はエラーは発生しません。
もしエラーが発生した場合は、`.env` ファイルの `DB_CONNECTION=sqlite` を確認してください。

## 📁 プロジェクト構造

```
study-app/
├── app/                    # Laravel アプリケーションファイル
├── config/                 # 設定ファイル
├── database/              # マイグレーション、シーダー
├── public/                # 公開ディレクトリ
├── resources/             # フロントエンドリソース
│   ├── css/
│   │   └── app.css       # メインスタイル
│   ├── js/
│   │   ├── app.js        # JavaScript エントリーポイント
│   │   └── App.vue       # メイン Vue コンポーネント
│   └── views/
│       └── welcome.blade.php  # メインHTML
├── routes/                # ルート定義
├── storage/               # ストレージ
├── tests/                 # テスト
├── .env                   # 環境設定（自動生成）
├── composer.json          # PHP 依存関係
├── package.json           # Node.js 依存関係
└── vite.config.js         # Vite 設定
```

## 🚀 次のステップ

1. **認証機能の実装**: Laravel Sanctum を使用したユーザー認証
2. **データベース設計**: マイグレーションファイルの作成
3. **API エンドポイントの実装**: 学習セッション管理 API
4. **実機能の実装**: 実際の学習時間記録機能
5. **GitHub風ヒートマップの実装**: 学習継続の可視化

## 📚 参考資料

- [Laravel 公式ドキュメント](https://laravel.com/docs)
- [Vue.js 公式ドキュメント](https://vuejs.org/)
- [TailwindCSS 公式ドキュメント](https://tailwindcss.com/)
- [Vite 公式ドキュメント](https://vitejs.dev/)

---

**作成日**: 2025年7月20日  
**環境**: macOS (darwin 24.5.0), PHP 8.4.10, Laravel 12.20.0, Vue.js 3, Node.js 24.4.1 