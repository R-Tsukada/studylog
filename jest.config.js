export default {
  // ESモジュール設定（Node.js native ESM support）
  preset: null,
  
  // テストファイルのパターン
  testMatch: [
    '**/tests/Unit/Frontend/**/*.js',
    '**/tests/Unit/Frontend/**/*.test.js'
  ],
  
  // カバレッジ設定
  collectCoverage: false,
  collectCoverageFrom: [
    'resources/js/**/*.js',
    '!resources/js/app.js'
  ],
  
  // テスト環境 - Vue.jsコンポーネントテスト用にjsdomを使用
  testEnvironment: 'jsdom',
  
  // モジュール解決
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/resources/js/$1'
  },
  
  // セットアップファイル
  setupFilesAfterEnv: [],
  
  // 変換設定 - Babel使用 + Vue.jsファイル対応
  transform: {
    '^.+\\.js$': ['babel-jest', { 
      presets: [['@babel/preset-env', { targets: { node: 'current' } }]]
    }],
    '^.+\\.vue$': '@vue/vue3-jest'
  },
  
  // Vue.js 3 用の追加設定
  transformIgnorePatterns: [
    'node_modules/(?!(vue|@vue)/)'
  ],
  
  // 詳細出力
  verbose: true
}