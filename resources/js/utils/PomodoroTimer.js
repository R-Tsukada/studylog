/**
 * デッドラインベースポモドーロタイマー (v2.0)
 * Issue #62 レースコンディション問題の根本解決
 * + startTime追跡、serialization修正、レースコンディション対策強化
 */
import { POMODORO_CONSTANTS } from './constants.js'

class PomodoroTimer {
  constructor() {
    // 時刻管理
    this.deadline = null          // セッション終了予定時刻
    this.startTime = null         // セッション開始時刻（actualDuration計算用）
    this.pausedAt = null          // 一時停止した時刻
    
    // タイマー制御
    this.tickInterval = null      // setInterval ID
    this.isCompleting = false     // 完了処理中フラグ（レースコンディション防止）
    
    // コールバック関数群
    this.callbacks = {
      onTick: null,               // 毎秒呼び出し
      onComplete: null,           // 完了時呼び出し
      onError: null               // エラー時呼び出し
    }
    
    // 状態管理
    this.state = POMODORO_CONSTANTS.TIMER_STATES.IDLE
    this.lastKnownRemaining = 0   // 前回の残り時間（UI更新最適化用）
    this.pausedRemaining = 0      // 一時停止時の残り時間
    
    // セッション情報（外部から設定）
    this.sessionData = null       // currentSession相当
  }

  /**
   * タイマー開始
   * @param {number} durationSeconds - 継続時間（秒）
   * @param {Object} callbacks - コールバック関数群
   * @param {Object} sessionData - セッション情報
   */
  start(durationSeconds, callbacks = {}, sessionData = null) {
    // 既存タイマーの強制停止（重複防止）
    this.forceStop()
    
    // 状態初期化
    this.callbacks = { ...this.callbacks, ...callbacks }
    this.sessionData = sessionData
    this.startTime = Date.now()
    this.deadline = this.startTime + (durationSeconds * 1000)
    this.state = POMODORO_CONSTANTS.TIMER_STATES.RUNNING
    this.isCompleting = false
    this.pausedAt = null
    
    // タイマー開始
    this.tickInterval = setInterval(() => {
      this.tick()
    }, POMODORO_CONSTANTS.TICK_INTERVAL_MS)
    
    
    // 初回実行
    this.tick()
  }

  /**
   * メインのティック処理
   * レースコンディション完全回避設計
   */
  tick() {
    // 完了処理中またはrunning状態でない場合はスキップ
    if (this.isCompleting || this.state !== POMODORO_CONSTANTS.TIMER_STATES.RUNNING) {
      return
    }

    try {
      const now = Date.now()
      const remainingMs = this.deadline - now
      const remainingSeconds = Math.max(0, Math.ceil(remainingMs / 1000))
      
      // 残り時間の変化をチェック（不要なUI更新を避ける）
      if (remainingSeconds !== this.lastKnownRemaining) {
        this.lastKnownRemaining = remainingSeconds
        
        if (this.callbacks.onTick) {
          this.callbacks.onTick(remainingSeconds)
        }
      }
      
      // 完了判定（レースコンディション回避）
      if (remainingSeconds <= 0) {
        this.complete()
      }
    } catch (error) {
      this.handleError(error)
    }
  }

  /**
   * セッション完了処理
   * レースコンディション完全回避
   */
  complete() {
    // 既に完了処理中の場合は重複実行を防ぐ
    if (this.isCompleting) {
      return
    }
    
    this.isCompleting = true
    this.state = POMODORO_CONSTANTS.TIMER_STATES.COMPLETED
    this.cleanup()
    
    
    if (this.callbacks.onComplete) {
      // 非同期でコールバック実行（UIブロック回避）
      setTimeout(() => {
        this.callbacks.onComplete()
      }, 0)
    }
  }

  /**
   * 一時停止
   */
  pause() {
    if (this.state === POMODORO_CONSTANTS.TIMER_STATES.RUNNING && !this.isCompleting) {
      this.state = POMODORO_CONSTANTS.TIMER_STATES.PAUSED
      this.pausedAt = Date.now()
      const remainingMs = this.deadline - this.pausedAt
      this.pausedRemaining = Math.max(0, Math.ceil(remainingMs / 1000))
      this.cleanup()
      
    }
  }

  /**
   * 再開
   */
  resume() {
    if (this.state === POMODORO_CONSTANTS.TIMER_STATES.PAUSED && this.pausedRemaining > 0) {
      // 新しい deadline を設定（pause時間を考慮）
      const now = Date.now()
      this.deadline = now + (this.pausedRemaining * 1000)
      this.state = POMODORO_CONSTANTS.TIMER_STATES.RUNNING
      this.isCompleting = false
      this.pausedAt = null
      
      // タイマー再開
      this.tickInterval = setInterval(() => {
        this.tick()
      }, POMODORO_CONSTANTS.TICK_INTERVAL_MS)
      
      
      this.tick() // 即座に更新
    }
  }

  /**
   * 停止
   */
  stop() {
    this.state = POMODORO_CONSTANTS.TIMER_STATES.IDLE
    this.isCompleting = false
    this.cleanup()
    this.resetState()
  }

  /**
   * 強制停止（重複実行防止用）
   */
  forceStop() {
    this.cleanup()
    this.isCompleting = false
  }

  /**
   * リソースクリーンアップ
   */
  cleanup() {
    if (this.tickInterval) {
      clearInterval(this.tickInterval)
      this.tickInterval = null
    }
  }

  /**
   * 状態リセット
   */
  resetState() {
    this.deadline = null
    this.startTime = null
    this.pausedAt = null
    this.pausedRemaining = 0
    this.lastKnownRemaining = 0
    this.sessionData = null
  }

  /**
   * 実際の経過時間を分で取得
   */
  getActualDurationMinutes() {
    if (!this.startTime) return 0
    const now = Date.now()
    return Math.ceil((now - this.startTime) / 1000 / 60)
  }

  /**
   * エラーハンドリング
   */
  handleError(error) {
    console.error('ポモドーロタイマーエラー:', error)
    this.state = POMODORO_CONSTANTS.TIMER_STATES.IDLE
    this.isCompleting = false
    this.cleanup()
    this.resetState()
    
    if (this.callbacks.onError) {
      this.callbacks.onError(error)
    }
  }

  /**
   * 状態保存用のシリアライズ（修正版）
   * startTime を含める
   */
  serialize() {
    return {
      deadline: this.deadline,
      startTime: this.startTime,           // 追加: actualDuration計算用
      pausedAt: this.pausedAt,             // 追加: pause状態復元用
      state: this.state,
      pausedRemaining: this.pausedRemaining,
      lastKnownRemaining: this.lastKnownRemaining,
      sessionData: this.sessionData,       // 追加: セッション情報
      serializedAt: Date.now()             // 追加: 保存時刻
    }
  }

  /**
   * 状態復元用のデシリアライズ（修正版）
   * 厳密な状態検証と復元
   */
  deserialize(data, callbacks = {}) {
    try {
      // データ検証
      if (!data || typeof data !== 'object') {
        throw new Error('無効な復元データ')
      }

      this.callbacks = { ...this.callbacks, ...callbacks }
      this.deadline = data.deadline
      this.startTime = data.startTime
      this.pausedAt = data.pausedAt
      this.state = data.state
      this.pausedRemaining = data.pausedRemaining || 0
      this.lastKnownRemaining = data.lastKnownRemaining || 0
      this.sessionData = data.sessionData
      this.isCompleting = false

      const now = Date.now()
      
      if (this.state === POMODORO_CONSTANTS.TIMER_STATES.RUNNING) {
        // 実行中の復元
        if (!this.deadline) {
          throw new Error('deadline が設定されていません')
        }
        
        const remainingMs = this.deadline - now
        const remainingSeconds = Math.max(0, Math.ceil(remainingMs / 1000))
        
        if (remainingSeconds > 0) {
          // タイマー復元
          this.lastKnownRemaining = remainingSeconds
          this.tickInterval = setInterval(() => {
            this.tick()
          }, POMODORO_CONSTANTS.TICK_INTERVAL_MS)
          
          
          this.tick() // 即座に更新
        } else {
          // 期限切れのため自動完了
          this.complete()
        }
      } else if (this.state === POMODORO_CONSTANTS.TIMER_STATES.PAUSED) {
        // 一時停止状態の復元
        if (this.pausedRemaining > 0) {
        } else {
          // 不正な一時停止状態
          this.state = POMODORO_CONSTANTS.TIMER_STATES.IDLE
          this.resetState()
        }
      }
      
      return true
    } catch (error) {
      console.error('タイマー復元エラー:', error)
      this.handleError(error)
      return false
    }
  }
}

export default PomodoroTimer