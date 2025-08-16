/**
 * ポモドーロタイマー関連定数 (v2.0)
 * ストレージ頻度とパフォーマンス最適化
 * Issue #62 対応 + 自動開始機能強化
 */
export const POMODORO_CONSTANTS = {
  // デフォルト時間設定（分）
  DEFAULT_FOCUS_DURATION: 25,
  DEFAULT_SHORT_BREAK_DURATION: 5,
  DEFAULT_LONG_BREAK_DURATION: 20,
  
  // タイマー設定
  TICK_INTERVAL_MS: 100,           // ティック間隔（100ms = 正確性確保）
  STORAGE_DEBOUNCE_MS: 3000,       // ストレージ保存間隔（3秒 = パフォーマンス重視）
  STORAGE_RETRY_MS: 1000,          // ストレージエラー時のリトライ間隔
  
  // 通知設定
  NOTIFICATION_DELAY_MS: 0,        // 完了通知遅延時間
  AUTO_START_DELAY_MS: 3000,       // 自動開始遅延時間（設計書に合わせて3秒に更新）
  
  // 自動開始関連（新規）
  AUTO_START_COUNTDOWN_INTERVAL: 100,        // カウントダウン更新間隔（100ms）
  NOTIFICATION_PERMISSION_REQUEST_DELAY: 1000, // 通知権限要求の遅延
  
  // ポモドーロサイクル管理（拡張）
  POMODORO_CYCLE_LENGTH: 4,        // 4回のfocus後にlong_break（既存のFOCUS_SESSIONS_PER_CYCLEと統合）
  FOCUS_SESSIONS_PER_CYCLE: 4,     // 既存互換性のため保持
  
  // セッション検証（新規）
  MAX_SESSION_DURATION_MINUTES: 240,        // 最大セッション時間（4時間）
  MIN_SESSION_DURATION_MINUTES: 1,          // 最小セッション時間（1分）
  
  // API通信設定（新規）
  API_TIMEOUT_MS: 10000,           // API通信タイムアウト（10秒）
  MAX_RETRY_ATTEMPTS: 3,           // 最大リトライ回数
  
  // セッションタイプ検証（新規）
  ALLOWED_SESSION_TYPES: ['focus', 'short_break', 'long_break'],
  
  // セッションタイプ
  SESSION_TYPES: {
    FOCUS: 'focus',
    SHORT_BREAK: 'short_break',
    LONG_BREAK: 'long_break'
  },
  
  // 状態定数
  TIMER_STATES: {
    IDLE: 'idle',
    RUNNING: 'running',
    PAUSED: 'paused',
    COMPLETED: 'completed'
  },
  
  // ローカルストレージキー
  STORAGE_KEYS: {
    TIMER_STATE: 'pomodoroTimer',
    CYCLE_STATE: 'pomodoroSessionManager',
    OFFLINE_QUEUE: 'pomodoroOfflineQueue'
  },
  
  // エラー・バリデーション
  MAX_DURATION_HOURS: 4,           // 最大セッション時間（異常値検出）
  MIN_DURATION_SECONDS: 10,        // 最小セッション時間
  SERIALIZATION_VERSION: '2.0'     // シリアライゼーションバージョン
}