/**
 * ポモドーロタイマー関連定数 (v2.0)
 * ストレージ頻度とパフォーマンス最適化
 * Issue #62 対応
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
  AUTO_START_DELAY_MS: 2000,       // 自動開始遅延時間
  
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
  
  // ポモドーロサイクル
  FOCUS_SESSIONS_PER_CYCLE: 4,
  
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