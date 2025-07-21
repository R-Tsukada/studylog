  サーバー起動コマンド

  推奨：すべてのサービスを一括起動

  composer dev

  このコマンドで以下が同時に起動します：
  - 🌐 Laravel API サーバー: http://127.0.0.1:8001
  - ⚡ Vite フロントエンド: http://localhost:5173
  - 🔄 Queue ワーカー: バックグラウンドジョブ処理
  - 📝 ログ監視: リアルタイムログ表示

  個別起動（デバッグ時）

  # Laravel APIサーバーのみ
  php artisan serve --host=127.0.0.1 --port=8001

  # Viteフロントエンドのみ
  npm run dev

  # Queue ワーカーのみ
  php artisan queue:work

  # ログ監視のみ
  php artisan log:monitor

  停止方法

  - Ctrl + C でサーバーを停止

  アクセス先

  - メインアプリ: http://127.0.0.1:8001
  - テストユーザー: test@example.com / password

  これで認証機能付きの資格学習アプリが完全に動作しますわ！ 🚀
