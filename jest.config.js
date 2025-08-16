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
  
  // テスト環境
  testEnvironment: 'node',
  
  // モジュール解決
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/resources/js/$1'
  },
  
  // セットアップファイル
  setupFilesAfterEnv: [],
  
  // 変換設定 - Babel使用
  transform: {
    '^.+\\.js$': ['babel-jest', { 
      presets: [['@babel/preset-env', { targets: { node: 'current' } }]]
    }]
  },
  
  // 詳細出力
  verbose: true
}