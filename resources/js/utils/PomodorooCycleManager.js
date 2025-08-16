import { POMODORO_CONSTANTS } from './constants.js'

/**
 * ポモドーロサイクル管理ユーティリティクラス
 * App.vueで使用するサイクル管理ロジックを提供
 */
export class PomodorooCycleManager {
  constructor() {
    this.pomodoroCounterState = {
      completedFocusSessions: 0,         // 完了した集中セッション数
      currentCycleStartTime: null,       // 現在のサイクル開始時間
      lastSessionCompletedAt: null,      // 最後のセッション完了時間
      cycleHistory: []                   // サイクル履歴（デバッグ用）
    }
  }

  /**
   * サイクル状態をリセット
   */
  resetCycleState() {
    this.pomodoroCounterState = {
      completedFocusSessions: 0,
      currentCycleStartTime: null,
      lastSessionCompletedAt: null,
      cycleHistory: []
    }
  }

  /**
   * 集中セッション完了をカウント
   */
  incrementFocusSession() {
    this.pomodoroCounterState.completedFocusSessions++
    this.pomodoroCounterState.lastSessionCompletedAt = Date.now()
    
    // 初回セッションまたはサイクル開始
    if (this.pomodoroCounterState.completedFocusSessions === 1) {
      this.pomodoroCounterState.currentCycleStartTime = Date.now()
    }

    // サイクル履歴に記録
    this.pomodoroCounterState.cycleHistory.push({
      sessionType: 'focus',
      completedAt: this.pomodoroCounterState.lastSessionCompletedAt,
      sessionCount: this.pomodoroCounterState.completedFocusSessions
    })
  }

  /**
   * 次のセッションタイプを決定
   * @returns {string} 'focus' | 'short_break' | 'long_break'
   */
  getNextSessionType() {
    const focusCount = this.pomodoroCounterState.completedFocusSessions
    
    if (focusCount === 0) {
      return 'focus' // 初回は集中セッション
    }
    
    if (focusCount >= POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH) {
      return 'long_break' // 4回完了後は長い休憩
    }
    
    return 'short_break' // その他は短い休憩
  }

  /**
   * サイクル完了判定
   * @returns {boolean} 長い休憩の時間かどうか
   */
  isLongBreakTime() {
    return this.pomodoroCounterState.completedFocusSessions >= POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH
  }

  /**
   * サイクル完了後のリセット
   * @returns {Object} 完了したサイクルの情報
   */
  completeCycle() {
    const completedCycle = {
      completedFocusSessions: this.pomodoroCounterState.completedFocusSessions,
      cycleStartTime: this.pomodoroCounterState.currentCycleStartTime,
      cycleEndTime: Date.now(),
      history: [...this.pomodoroCounterState.cycleHistory]
    }

    // 次のサイクル用にリセット
    this.pomodoroCounterState.completedFocusSessions = 0
    this.pomodoroCounterState.currentCycleStartTime = null
    this.pomodoroCounterState.cycleHistory = []

    return completedCycle
  }

  /**
   * 休憩セッション完了後の処理
   */
  completeBreakSession() {
    this.pomodoroCounterState.lastSessionCompletedAt = Date.now()
    
    // 履歴に記録
    this.pomodoroCounterState.cycleHistory.push({
      sessionType: 'break',
      completedAt: this.pomodoroCounterState.lastSessionCompletedAt,
      sessionCount: this.pomodoroCounterState.completedFocusSessions
    })
  }

  /**
   * セッション完了の汎用処理（統合テスト用）
   * @param {Object} session - 完了したセッション情報
   */
  markSessionCompleted(session) {
    if (session && session.session_type === 'focus') {
      this.incrementFocusSession()
    } else if (session && (session.session_type === 'short_break' || session.session_type === 'long_break')) {
      this.completeBreakSession()
    }
  }

  /**
   * 統計情報取得
   * @returns {Object} 現在のサイクル状態統計
   */
  getCycleStats() {
    return {
      completedFocusSessions: this.pomodoroCounterState.completedFocusSessions,
      currentCycleStartTime: this.pomodoroCounterState.currentCycleStartTime,
      lastSessionCompletedAt: this.pomodoroCounterState.lastSessionCompletedAt,
      cycleHistoryLength: this.pomodoroCounterState.cycleHistory.length,
      nextSessionType: this.getNextSessionType(),
      isLongBreakTime: this.isLongBreakTime()
    }
  }

  /**
   * ローカルストレージから状態を復元
   * @param {Object} savedState 保存された状態
   */
  restoreFromStorage(savedState) {
    if (savedState && typeof savedState === 'object') {
      this.pomodoroCounterState = {
        completedFocusSessions: savedState.completedFocusSessions || 0,
        currentCycleStartTime: savedState.currentCycleStartTime || null,
        lastSessionCompletedAt: savedState.lastSessionCompletedAt || null,
        cycleHistory: Array.isArray(savedState.cycleHistory) ? savedState.cycleHistory : []
      }
    }
  }

  /**
   * ローカルストレージ用のシリアライズ
   * @returns {Object} シリアライズされた状態
   */
  serialize() {
    return {
      ...this.pomodoroCounterState,
      version: POMODORO_CONSTANTS.SERIALIZATION_VERSION
    }
  }
}