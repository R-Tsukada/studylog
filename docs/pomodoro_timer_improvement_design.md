# 🍅 ポモドーロタイマー改善 - 実装詳細設計書 (v2.0)

## 📋 設計概要

### Issue #62 対応
**問題**: ポモドーロのカウントがマイナスになってしまい、休憩タイマーが始まらない

### 根本原因
1. **レースコンディション**: `timeRemaining--` 後に完了判定を行うため、-1になる瞬間が存在
2. **setInterval の精度問題**: ブラウザ負荷やバックグラウンド状態で時刻ドリフトが発生
3. **状態復元時の不整合**: localStorage からの復元時に負の値が計算される
4. **serialization の不整合**: startTime が保存されないため actualDuration 計算が不正確
5. **二重状態管理**: 既存 globalPomodoroTimer と新タイマーの競合リスク

### 解決方針
**デッドラインベースタイマー**への移行により、時刻の絶対値を基準とした正確なタイマーを実装し、**単一責任による状態管理の統一**を実現

---

## 🏗️ アーキテクチャ設計

### フェーズ1: 緊急対応（即座実装）
- **単一タイマーサービス**によるデッドラインベース実装
- **完全なserialization/deserialization**対応
- **レースコンディション完全回避**
- **グローバル状態統一**（二重管理の排除）

### フェーズ2: 安定性向上
- **ドメインモデル分離**（Session、Cycle、Timer）
- **エラーハンドリング・オフライン対応強化**
- **パフォーマンス最適化**（ストレージ頻度調整）

### フェーズ3: 長期改善
- **TypeScript導入**（型安全性確保）
- **Pinia による状態管理分離**
- **包括的テスト戦略**

---

## 🚨 フェーズ1: 緊急対応

### 1.1 PomodoroTimer クラス設計

#### ファイル構成
```
resources/js/utils/
├── PomodoroTimer.js    # メインタイマー実装
├── debounce.js         # デバウンス機能
└── constants.js        # 定数定義
```

#### PomodoroTimer.js - 修正版実装
```javascript
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
    
    console.log('タイマー開始:', {
      duration: durationSeconds,
      startTime: this.startTime,
      deadline: this.deadline
    })
    
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
    
    console.log('タイマー完了:', {
      startTime: this.startTime,
      completedAt: Date.now(),
      actualDuration: this.getActualDurationMinutes()
    })
    
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
      
      console.log('タイマー一時停止:', {
        pausedAt: this.pausedAt,
        remainingSeconds: this.pausedRemaining
      })
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
      
      console.log('タイマー再開:', {
        resumedAt: now,
        newDeadline: this.deadline,
        remainingSeconds: this.pausedRemaining
      })
      
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
          
          console.log('タイマー状態復元（実行中）:', {
            remainingSeconds,
            startTime: this.startTime,
            deadline: this.deadline
          })
          
          this.tick() // 即座に更新
        } else {
          // 期限切れのため自動完了
          console.log('タイマー状態復元（期限切れ）: 自動完了')
          this.complete()
        }
      } else if (this.state === POMODORO_CONSTANTS.TIMER_STATES.PAUSED) {
        // 一時停止状態の復元
        if (this.pausedRemaining > 0) {
          console.log('タイマー状態復元（一時停止）:', {
            pausedRemaining: this.pausedRemaining,
            pausedAt: this.pausedAt
          })
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
```

#### debounce.js
```javascript
/**
 * デバウンス機能
 * localStorage 書き込み頻度を制御
 */
export function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func.apply(this, args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}
```

#### constants.js - 修正版
```javascript
/**
 * ポモドーロタイマー関連定数 (v2.0)
 * ストレージ頻度とパフォーマンス最適化
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
```

### 1.2 App.vue への統合 - 修正版

#### 🔴 重要: 二重管理の完全排除
従来の `globalPomodoroTimer` は廃止し、新しい `PomodoroTimer` に一本化します。UI側は **タイマー単体の状態** のみを参照します。

#### データモデル分離
```javascript
// App.vue - 単一タイマーサービス統合 (v2.0)
import PomodoroTimer from '@/utils/PomodoroTimer.js'
import { debounce } from '@/utils/debounce.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'

export default {
  data() {
    return {
      // 単一のタイマーインスタンス（状態管理統一）
      pomodoroTimer: new PomodoroTimer(),
      
      // UI表示用データ（読み取り専用）
      pomodoroDisplay: reactive({
        isActive: false,          // タイマー動作中かどうか
        timeRemaining: 0,         // 残り時間（秒）
        sessionType: null,        // 'focus', 'short_break', 'long_break'
        progress: 0,              // 進捗パーセンテージ
        actualDuration: 0         // 実際の経過時間（分）
      }),
      
      // セッション管理（API連携用）
      currentSession: null,       // サーバー側セッション情報
    }
  },

  // UI側で参照するcomputed properties
  computed: {
    // ユニバーサルタイマー状態（PomodoroTimer.vue で利用）
    globalPomodoroTimer() {
      return {
        isActive: this.pomodoroDisplay.isActive,
        currentSession: this.currentSession,
        timeRemaining: this.pomodoroDisplay.timeRemaining,
        startTime: this.pomodoroTimer.startTime,
        // 下位互換性のため timer プロパティを残す（使用非推奨）
        timer: null
      }
    },
    
    // フォーマットされた時間表示
    formattedTimeRemaining() {
      const mins = Math.floor(this.pomodoroDisplay.timeRemaining / 60)
      const secs = this.pomodoroDisplay.timeRemaining % 60
      return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }
  },

  methods: {
    /**
     * セッション開始（単一責任設計）
     */
    startGlobalPomodoroTimer(session) {
      console.log('ポモドーロセッション開始:', session)
      
      // セッション情報を保存
      this.currentSession = session
      
      // タイマー開始
      this.pomodoroTimer.start(
        session.planned_duration * 60,
        {
          onTick: (remainingSeconds) => {
            this.updateDisplayState(remainingSeconds)
          },
          onComplete: () => {
            this.handleGlobalTimerComplete()
          },
          onError: (error) => {
            this.handleTimerError(error)
          }
        },
        session // セッションデータをタイマー内で管理
      )
    },

    /**
     * タイマー停止（クリーンアップ強化）
     */
    stopGlobalPomodoroTimer() {
      console.log('ポモドーロセッション停止')
      
      // タイマー停止
      this.pomodoroTimer.stop()
      
      // UI状態クリア
      this.clearDisplayState()
      
      // セッション情報クリア
      this.currentSession = null
      
      // ストレージクリア
      this.clearTimerStorage()
    },

    /**
     * 一時停止・再開（新タイマー委譲）
     */
    pauseSession() {
      this.pomodoroTimer.pause()
      this.updateDisplayFromTimer()
    },

    resumeSession() {
      this.pomodoroTimer.resume()
    },

    /**
     * UI状態更新（単一責任）
     */
    updateDisplayState(remainingSeconds) {
      this.pomodoroDisplay.isActive = this.pomodoroTimer.state === POMODORO_CONSTANTS.TIMER_STATES.RUNNING
      this.pomodoroDisplay.timeRemaining = remainingSeconds
      this.pomodoroDisplay.sessionType = this.currentSession?.session_type
      this.pomodoroDisplay.actualDuration = this.pomodoroTimer.getActualDurationMinutes()
      
      // 進捗計算
      if (this.currentSession) {
        const totalSeconds = this.currentSession.planned_duration * 60
        this.pomodoroDisplay.progress = Math.min(100, 
          ((totalSeconds - remainingSeconds) / totalSeconds) * 100
        )
      }
      
      // デバウンスされた保存
      this.debouncedSaveTimerState()
    },

    /**
     * タイマー状態からUI更新
     */
    updateDisplayFromTimer() {
      this.pomodoroDisplay.isActive = this.pomodoroTimer.state === POMODORO_CONSTANTS.TIMER_STATES.RUNNING
      this.pomodoroDisplay.timeRemaining = this.pomodoroTimer.lastKnownRemaining
      this.pomodoroDisplay.actualDuration = this.pomodoroTimer.getActualDurationMinutes()
    },

    /**
     * UI状態クリア
     */
    clearDisplayState() {
      this.pomodoroDisplay.isActive = false
      this.pomodoroDisplay.timeRemaining = 0
      this.pomodoroDisplay.sessionType = null
      this.pomodoroDisplay.progress = 0
      this.pomodoroDisplay.actualDuration = 0
    },

    /**
     * セッション完了処理（修正版）
     */
    async handleGlobalTimerComplete() {
      if (!this.currentSession) return

      try {
        // 実際の経過時間を取得
        const actualDuration = this.pomodoroTimer.getActualDurationMinutes()
        
        console.log('セッション完了:', {
          sessionId: this.currentSession.id,
          actualDuration,
          sessionType: this.currentSession.session_type
        })

        // API完了通知
        await this.completeCurrentSession(actualDuration)
        
        // 通知表示
        this.showCompletionNotification()
        
        // 統計更新
        await this.loadTodayStats()
        
        // 状態クリア
        this.stopGlobalPomodoroTimer()
        
        // 自動開始判定
        if (this.settings?.auto_start) {
          setTimeout(() => {
            this.suggestNextSession()
          }, POMODORO_CONSTANTS.AUTO_START_DELAY_MS)
        }
        
      } catch (error) {
        console.error('セッション完了処理エラー:', error)
        this.showError('セッション完了処理でエラーが発生しました')
      }
    },

    /**
     * API セッション完了
     */
    async completeCurrentSession(actualDuration) {
      const response = await fetch(`/api/pomodoro/${this.currentSession.id}/complete`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          actual_duration: actualDuration,
          was_interrupted: false,
          notes: this.sessionNotes || '自動完了'
        })
      })

      if (!response.ok) {
        throw new Error(`セッション完了API エラー: ${response.status}`)
      }

      return response.json()
    },

    /**
     * 状態復元（修正版）
     */
    restoreTimerStateFromStorage() {
      try {
        const saved = localStorage.getItem(POMODORO_CONSTANTS.STORAGE_KEYS.TIMER_STATE)
        if (!saved) return

        const state = JSON.parse(saved)
        
        // バージョン検証
        if (state.version !== POMODORO_CONSTANTS.SERIALIZATION_VERSION) {
          console.warn('古いバージョンの保存データです。クリアします。')
          this.clearTimerStorage()
          return
        }

        // セッション情報復元
        this.currentSession = state.sessionData
        
        // タイマー復元
        const restored = this.pomodoroTimer.deserialize(state, {
          onTick: (remainingSeconds) => {
            this.updateDisplayState(remainingSeconds)
          },
          onComplete: () => {
            this.handleGlobalTimerComplete()
          },
          onError: (error) => {
            this.handleTimerError(error)
          }
        })

        if (restored) {
          // UI状態同期
          this.updateDisplayFromTimer()
          console.log('タイマー状態復元成功')
        } else {
          // 復元失敗時のクリーンアップ
          this.clearTimerStorage()
          this.clearDisplayState()
          this.currentSession = null
        }
        
      } catch (error) {
        console.error('タイマー状態復元エラー:', error)
        this.clearTimerStorage()
        this.clearDisplayState()
        this.currentSession = null
      }
    },

    /**
     * 状態保存（修正版・デバウンス）
     */
    debouncedSaveTimerState: debounce(function() {
      if (this.pomodoroDisplay.isActive) {
        try {
          const state = {
            ...this.pomodoroTimer.serialize(),
            version: POMODORO_CONSTANTS.SERIALIZATION_VERSION
          }
          
          localStorage.setItem(POMODORO_CONSTANTS.STORAGE_KEYS.TIMER_STATE, JSON.stringify(state))
        } catch (error) {
          console.error('タイマー状態保存エラー:', error)
        }
      }
    }, POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS),

    /**
     * ストレージクリア
     */
    clearTimerStorage() {
      localStorage.removeItem(POMODORO_CONSTANTS.STORAGE_KEYS.TIMER_STATE)
    },

    /**
     * エラーハンドリング
     */
    handleTimerError(error) {
      console.error('タイマーエラー:', error)
      this.showError('タイマーでエラーが発生しました')
      
      // エラー時のクリーンアップ
      this.clearDisplayState()
      this.currentSession = null
      this.clearTimerStorage()
    }
  }
}
```

#### 重要な設計変更点

1. **二重管理の完全排除**: `globalPomodoroTimer` を computed property 化し、実体は `PomodoroTimer` に統一
2. **startTime 管理の移譲**: タイマー側で `startTime` を完全管理、`getActualDurationMinutes()` で取得
3. **UI状態の分離**: `pomodoroDisplay` で表示専用データを管理
4. **エラー耐性の強化**: バージョン管理、データ検証、フォールバック処理
5. **責任の明確化**: タイマー=時間管理、App.vue=UI同期+API連携

---

## 🟡 フェーズ2: 安定性向上

### 2.1 ポモドーロサイクル管理

#### PomodoroSessionManager.js
```javascript
/**
 * ポモドーロセッションサイクル管理
 */
import { POMODORO_CONSTANTS } from './constants.js'

class PomodoroSessionManager {
  constructor() {
    this.focusCompletedCount = 0
    this.currentCycleStart = Date.now()
    this.sessionHistory = []
  }

  /**
   * 次のセッションタイプを決定
   */
  getNextSessionType(currentSessionType, completedDuration) {
    this.recordSessionCompletion(currentSessionType, completedDuration)

    if (currentSessionType === POMODORO_CONSTANTS.SESSION_TYPES.FOCUS) {
      this.focusCompletedCount++
      
      // 4回目のフォーカス完了後は長い休憩
      if (this.focusCompletedCount % POMODORO_CONSTANTS.FOCUS_SESSIONS_PER_CYCLE === 0) {
        return {
          type: POMODORO_CONSTANTS.SESSION_TYPES.LONG_BREAK,
          duration: POMODORO_CONSTANTS.DEFAULT_LONG_BREAK_DURATION,
          cyclePosition: `フォーカス完了 ${this.focusCompletedCount}/${POMODORO_CONSTANTS.FOCUS_SESSIONS_PER_CYCLE} - 長い休憩`
        }
      } else {
        return {
          type: POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK,
          duration: POMODORO_CONSTANTS.DEFAULT_SHORT_BREAK_DURATION,
          cyclePosition: `フォーカス完了 ${this.focusCompletedCount}/${POMODORO_CONSTANTS.FOCUS_SESSIONS_PER_CYCLE} - 短い休憩`
        }
      }
    } else if (currentSessionType === POMODORO_CONSTANTS.SESSION_TYPES.LONG_BREAK) {
      // 長い休憩後はサイクルリセット
      this.resetCycle()
      return {
        type: POMODORO_CONSTANTS.SESSION_TYPES.FOCUS,
        duration: POMODORO_CONSTANTS.DEFAULT_FOCUS_DURATION,
        cyclePosition: 'フォーカス開始（新しいサイクル）'
      }
    } else {
      // 短い休憩後はフォーカスに戻る
      return {
        type: POMODORO_CONSTANTS.SESSION_TYPES.FOCUS,
        duration: POMODORO_CONSTANTS.DEFAULT_FOCUS_DURATION,
        cyclePosition: `フォーカス ${this.focusCompletedCount + 1}/${POMODORO_CONSTANTS.FOCUS_SESSIONS_PER_CYCLE}`
      }
    }
  }

  /**
   * セッション完了記録
   */
  recordSessionCompletion(sessionType, duration) {
    this.sessionHistory.push({
      type: sessionType,
      duration: duration,
      completedAt: Date.now()
    })

    // 履歴が長くなりすぎないよう制限
    if (this.sessionHistory.length > 20) {
      this.sessionHistory = this.sessionHistory.slice(-20)
    }
  }

  /**
   * サイクルリセット
   */
  resetCycle() {
    this.focusCompletedCount = 0
    this.currentCycleStart = Date.now()
  }

  /**
   * 現在のサイクル状況を取得
   */
  getCycleStatus() {
    return {
      focusCompleted: this.focusCompletedCount,
      nextLongBreakIn: POMODORO_CONSTANTS.FOCUS_SESSIONS_PER_CYCLE - (this.focusCompletedCount % POMODORO_CONSTANTS.FOCUS_SESSIONS_PER_CYCLE),
      cycleStartTime: this.currentCycleStart,
      recentSessions: this.sessionHistory.slice(-5)
    }
  }

  /**
   * 状態の保存・復元
   */
  serialize() {
    return {
      focusCompletedCount: this.focusCompletedCount,
      currentCycleStart: this.currentCycleStart,
      sessionHistory: this.sessionHistory
    }
  }

  deserialize(data) {
    this.focusCompletedCount = data.focusCompletedCount || 0
    this.currentCycleStart = data.currentCycleStart || Date.now()
    this.sessionHistory = data.sessionHistory || []
  }
}

export default PomodoroSessionManager
```

### 2.2 オフライン対応とエラーハンドリング

#### OfflineQueue.js
```javascript
/**
 * オフライン時のAPIリクエスト管理
 */
class OfflineQueue {
  constructor() {
    this.queue = this.loadFromStorage()
    this.isOnline = navigator.onLine
    this.setupEventListeners()
  }

  setupEventListeners() {
    window.addEventListener('online', () => {
      this.isOnline = true
      this.processQueue()
    })

    window.addEventListener('offline', () => {
      this.isOnline = false
    })
  }

  /**
   * オフライン時にAPIリクエストをキューに追加
   */
  addToQueue(requestData) {
    this.queue.push({
      ...requestData,
      timestamp: Date.now(),
      retryCount: 0
    })
    this.saveToStorage()

    // オンラインの場合は即座に処理
    if (this.isOnline) {
      this.processQueue()
    }
  }

  /**
   * キューの処理
   */
  async processQueue() {
    const pendingItems = [...this.queue]
    this.queue = []

    for (const item of pendingItems) {
      try {
        await this.executeRequest(item)
      } catch (error) {
        // リトライ制限に達していない場合は再キュー
        if (item.retryCount < 3) {
          item.retryCount++
          this.queue.push(item)
        } else {
          console.error('リクエスト失敗（リトライ上限）:', item, error)
        }
      }
    }

    this.saveToStorage()
  }

  /**
   * APIリクエスト実行
   */
  async executeRequest(item) {
    const { method, url, data } = item
    
    const response = await fetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(data)
    })

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    return response.json()
  }

  /**
   * ストレージ操作
   */
  loadFromStorage() {
    try {
      const saved = localStorage.getItem('pomodoroOfflineQueue')
      return saved ? JSON.parse(saved) : []
    } catch {
      return []
    }
  }

  saveToStorage() {
    localStorage.setItem('pomodoroOfflineQueue', JSON.stringify(this.queue))
  }
}

export default OfflineQueue
```

---

## 🔵 フェーズ3: 長期改善

### 3.1 Pinia ストア設計

#### stores/pomodoro.js
```javascript
/**
 * Pinia ポモドーロストア
 */
import { defineStore } from 'pinia'
import PomodoroTimer from '@/utils/PomodoroTimer.js'
import PomodoroSessionManager from '@/utils/PomodoroSessionManager.js'
import OfflineQueue from '@/utils/OfflineQueue.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'

export const usePomodoroStore = defineStore('pomodoro', {
  state: () => ({
    // タイマー関連
    timer: new PomodoroTimer(),
    sessionManager: new PomodoroSessionManager(),
    offlineQueue: new OfflineQueue(),
    
    // セッション状態
    isActive: false,
    currentSession: null,
    timeRemaining: 0,
    
    // 設定
    settings: {
      focusDuration: POMODORO_CONSTANTS.DEFAULT_FOCUS_DURATION,
      shortBreakDuration: POMODORO_CONSTANTS.DEFAULT_SHORT_BREAK_DURATION,
      longBreakDuration: POMODORO_CONSTANTS.DEFAULT_LONG_BREAK_DURATION,
      autoStartBreak: true,
      autoStartFocus: false,
      soundEnabled: true,
      cycleGoal: 4
    },
    
    // 統計
    todayStats: null,
    cycleStatus: null
  }),

  getters: {
    /**
     * 現在のセッション情報
     */
    currentSessionInfo: (state) => {
      if (!state.currentSession) return null
      
      return {
        type: state.currentSession.session_type,
        timeRemaining: state.timeRemaining,
        progress: ((state.currentSession.planned_duration * 60 - state.timeRemaining) / 
                  (state.currentSession.planned_duration * 60)) * 100,
        cyclePosition: state.cycleStatus?.focusCompleted || 0
      }
    },

    /**
     * フォーマットされた残り時間
     */
    formattedTimeRemaining: (state) => {
      const mins = Math.floor(state.timeRemaining / 60)
      const secs = state.timeRemaining % 60
      return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }
  },

  actions: {
    /**
     * セッション開始
     */
    async startSession(sessionType, duration, subjectAreaId = null) {
      try {
        // API でセッション作成
        const sessionData = {
          session_type: sessionType,
          planned_duration: duration,
          subject_area_id: subjectAreaId,
          settings: this.settings
        }

        const response = await this.apiCall('POST', '/api/pomodoro', sessionData)
        
        if (response.ok) {
          const data = await response.json()
          this.currentSession = data
          this.isActive = true
          
          // タイマー開始
          this.timer.start(duration * 60, {
            onTick: (remaining) => {
              this.timeRemaining = remaining
              this.persistState()
            },
            onComplete: () => {
              this.handleSessionComplete()
            },
            onError: (error) => {
              this.handleTimerError(error)
            }
          })
        }
      } catch (error) {
        console.error('セッション開始エラー:', error)
        // オフラインキューに追加
        this.offlineQueue.addToQueue({
          method: 'POST',
          url: '/api/pomodoro',
          data: sessionData
        })
      }
    },

    /**
     * セッション完了処理
     */
    async handleSessionComplete() {
      if (!this.currentSession) return

      try {
        // 完了をAPIに送信
        await this.completeSessionAPI()
        
        // セッション履歴を更新
        const actualDuration = this.currentSession.planned_duration
        const nextSession = this.sessionManager.getNextSessionType(
          this.currentSession.session_type,
          actualDuration
        )
        
        // 統計更新
        await this.updateStats()
        
        // 通知表示
        this.showCompletionNotification()
        
        // 自動開始判定
        if (this.shouldAutoStartNext(nextSession.type)) {
          setTimeout(() => {
            this.startSession(nextSession.type, nextSession.duration)
          }, 2000)
        }
        
        // 状態クリア
        this.stopSession()
        
      } catch (error) {
        console.error('セッション完了処理エラー:', error)
      }
    },

    /**
     * API呼び出しヘルパー
     */
    async apiCall(method, url, data = null) {
      try {
        return await fetch(url, {
          method,
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: data ? JSON.stringify(data) : null
        })
      } catch (error) {
        // オフライン時はキューに追加
        if (!navigator.onLine) {
          this.offlineQueue.addToQueue({ method, url, data })
        }
        throw error
      }
    }
  }
})
```

---

## 🧪 テスト戦略 - 修正版

### レビュー指摘事項に基づく検証項目

#### 必須検証項目

1. **レースコンディション検証** - 残り0直前での完了処理が二重に走らないことの検証
2. **復元検証** - 保存→再読み込み→正しい remaining と正しい次セッションの再開
3. **サイクルロジック検証** - 4回フォーカス完了後に long_break が挿入されることと、long_break 後に focus に戻ること
4. **パフォーマンス検証** - 保存頻度を引き下げても UI の表示が滑らかか
5. **破損データ時のフォールバック挙動**
6. **pause/resume 後の正確な remaining の再計算**

### ユニットテスト設計

#### tests/unit/PomodoroTimer.test.js - 修正版
```javascript
import PomodoroTimer from '@/utils/PomodoroTimer.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'

describe('PomodoroTimer v2.0', () => {
  let timer
  
  beforeEach(() => {
    timer = new PomodoroTimer()
    jest.useFakeTimers()
  })

  afterEach(() => {
    timer.cleanup()
    jest.useRealTimers()
  })

  describe('基本機能', () => {
    test('正常なカウントダウン', () => {
      const onTick = jest.fn()
      const onComplete = jest.fn()
      
      timer.start(5, { onTick, onComplete })
      
      // 4秒経過
      jest.advanceTimersByTime(4000)
      expect(onTick).toHaveBeenLastCalledWith(1)
      expect(onComplete).not.toHaveBeenCalled()
      
      // 完了
      jest.advanceTimersByTime(1000)
      expect(onComplete).toHaveBeenCalledTimes(1)
    })

    test('startTime が正しく設定される', () => {
      const startTime = Date.now()
      timer.start(60, {})
      
      expect(timer.startTime).toBeGreaterThanOrEqual(startTime)
      expect(timer.getActualDurationMinutes()).toBe(0)
    })
  })

  describe('Issue #62 レースコンディション修正検証', () => {
    test('負の値にならない', () => {
      const onTick = jest.fn()
      
      timer.start(1, { onTick })
      
      // 2秒経過（予定時間を超過）
      jest.advanceTimersByTime(2000)
      
      // onTickで受け取った値がすべて0以上であることを確認
      onTick.mock.calls.forEach(call => {
        expect(call[0]).toBeGreaterThanOrEqual(0)
      })
    })

    test('完了処理の重複実行防止', () => {
      const onComplete = jest.fn()
      timer.start(1, { onComplete })
      
      // 時間経過で完了
      jest.advanceTimersByTime(1000)
      
      // さらに時間が経過しても完了処理は1回のみ
      jest.advanceTimersByTime(1000)
      expect(onComplete).toHaveBeenCalledTimes(1)
      
      // 手動でcomplete()を呼んでも追加実行されない
      timer.complete()
      expect(onComplete).toHaveBeenCalledTimes(1)
    })

    test('isCompleting フラグによる重複防止', () => {
      timer.start(1, {})
      jest.advanceTimersByTime(1000)
      
      // 完了処理中は tick が実行されない
      expect(timer.isCompleting).toBe(true)
      timer.tick() // 手動実行
      
      // state は completed のまま
      expect(timer.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.COMPLETED)
    })
  })

  describe('一時停止・再開機能', () => {
    test('pause/resume の正確な remaining 計算', () => {
      const onTick = jest.fn()
      timer.start(10, { onTick })
      
      // 3秒経過後一時停止
      jest.advanceTimersByTime(3000)
      timer.pause()
      
      expect(timer.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.PAUSED)
      expect(timer.pausedRemaining).toBe(7) // 10-3=7秒残り
      
      // さらに5秒経過（停止中なので変化なし）
      jest.advanceTimersByTime(5000)
      expect(timer.pausedRemaining).toBe(7)
      
      // 再開
      timer.resume()
      expect(timer.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.RUNNING)
      
      // 再開後のカウントダウン検証
      jest.advanceTimersByTime(2000)
      expect(timer.lastKnownRemaining).toBe(5) // 7-2=5秒残り
    })

    test('pause状態での serialization/deserialization', () => {
      timer.start(30, {})
      jest.advanceTimersByTime(10000) // 10秒経過
      timer.pause()
      
      const serialized = timer.serialize()
      expect(serialized.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.PAUSED)
      expect(serialized.pausedRemaining).toBe(20)
      expect(serialized.pausedAt).toBeDefined()
      
      const newTimer = new PomodoroTimer()
      newTimer.deserialize(serialized, {})
      
      expect(newTimer.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.PAUSED)
      expect(newTimer.pausedRemaining).toBe(20)
    })
  })

  describe('serialization/deserialization 検証', () => {
    test('startTime を含む完全な状態保存', () => {
      const startTime = Date.now()
      const sessionData = { id: 1, type: 'focus' }
      
      timer.start(60, {}, sessionData)
      jest.advanceTimersByTime(10000) // 10秒経過
      
      const serialized = timer.serialize()
      
      expect(serialized.startTime).toBeGreaterThanOrEqual(startTime)
      expect(serialized.deadline).toBeDefined()
      expect(serialized.sessionData).toEqual(sessionData)
      expect(serialized.serializedAt).toBeDefined()
    })

    test('期限切れセッションの自動完了', () => {
      const onComplete = jest.fn()
      
      // 既に期限切れの状態データを復元
      const expiredState = {
        deadline: Date.now() - 5000, // 5秒前に期限切れ
        startTime: Date.now() - 65000, // 65秒前に開始
        state: POMODORO_CONSTANTS.TIMER_STATES.RUNNING,
        sessionData: { id: 1 }
      }
      
      timer.deserialize(expiredState, { onComplete })
      
      // 自動完了されることを確認
      expect(onComplete).toHaveBeenCalledTimes(1)
      expect(timer.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.COMPLETED)
    })

    test('破損データ時のフォールバック', () => {
      const onError = jest.fn()
      
      // 無効なデータで復元試行
      const restored1 = timer.deserialize(null, { onError })
      expect(restored1).toBe(false)
      
      const restored2 = timer.deserialize({ invalid: 'data' }, { onError })
      expect(restored2).toBe(false)
      
      expect(timer.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.IDLE)
    })
  })

  describe('actualDuration 計算', () => {
    test('正確な経過時間計算', () => {
      timer.start(60, {})
      
      jest.advanceTimersByTime(5000) // 5秒経過
      expect(timer.getActualDurationMinutes()).toBe(1) // Math.ceil(5/60) = 1分
      
      jest.advanceTimersByTime(55000) // さらに55秒経過（計60秒）
      expect(timer.getActualDurationMinutes()).toBe(1) // Math.ceil(60/60) = 1分
      
      jest.advanceTimersByTime(1000) // さらに1秒経過（計61秒）
      expect(timer.getActualDurationMinutes()).toBe(2) // Math.ceil(61/60) = 2分
    })
  })

  describe('重複実行防止', () => {
    test('forceStop による重複タイマー防止', () => {
      timer.start(10, {})
      expect(timer.tickInterval).toBeDefined()
      
      // 2回目の start で forceStop が呼ばれる
      timer.start(20, {})
      
      // 新しいタイマーのみが動作
      expect(timer.deadline).toBeGreaterThan(Date.now() + 15000)
    })
  })
})
```

#### tests/unit/PomodoroSessionManager.test.js - サイクルロジック厳密検証
```javascript
import PomodoroSessionManager from '@/utils/PomodoroSessionManager.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'

describe('PomodoroSessionManager', () => {
  let manager
  
  beforeEach(() => {
    manager = new PomodoroSessionManager()
  })

  describe('4セッションサイクル検証', () => {
    test('完全な1サイクル（4フォーカス + 長い休憩）', () => {
      const sessionHistory = []
      
      // 1回目: focus → short_break
      let next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      sessionHistory.push({ from: 'focus', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK)
      expect(manager.focusCompletedCount).toBe(1)
      
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK, 5)
      sessionHistory.push({ from: 'short_break', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS)
      
      // 2回目: focus → short_break
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      sessionHistory.push({ from: 'focus', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK)
      expect(manager.focusCompletedCount).toBe(2)
      
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK, 5)
      sessionHistory.push({ from: 'short_break', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS)
      
      // 3回目: focus → short_break
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      sessionHistory.push({ from: 'focus', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK)
      expect(manager.focusCompletedCount).toBe(3)
      
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.SHORT_BREAK, 5)
      sessionHistory.push({ from: 'short_break', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS)
      
      // 4回目: focus → long_break (重要！)
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      sessionHistory.push({ from: 'focus', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.LONG_BREAK)
      expect(manager.focusCompletedCount).toBe(4)
      expect(next.cyclePosition).toContain('長い休憩')
      
      // long_break → focus (サイクルリセット)
      next = manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.LONG_BREAK, 20)
      sessionHistory.push({ from: 'long_break', to: next.type, focusCount: manager.focusCompletedCount })
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS)
      expect(manager.focusCompletedCount).toBe(0) // リセット確認
      expect(next.cyclePosition).toContain('新しいサイクル')
      
      console.log('セッション履歴:', sessionHistory)
    })

    test('サイクル状況の正確な追跡', () => {
      // 2回のフォーカス完了
      manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      
      const status = manager.getCycleStatus()
      expect(status.focusCompleted).toBe(2)
      expect(status.nextLongBreakIn).toBe(2) // 4-2=2回残り
      
      // さらに1回完了
      manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      const status2 = manager.getCycleStatus()
      expect(status2.nextLongBreakIn).toBe(1) // 4-3=1回残り
    })
  })

  describe('状態保存・復元', () => {
    test('serialization/deserialization', () => {
      // 3回フォーカス完了状態
      manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      manager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      
      const serialized = manager.serialize()
      const newManager = new PomodoroSessionManager()
      newManager.deserialize(serialized)
      
      expect(newManager.focusCompletedCount).toBe(3)
      
      // 次のフォーカス完了で長い休憩になることを確認
      const next = newManager.getNextSessionType(POMODORO_CONSTANTS.SESSION_TYPES.FOCUS, 25)
      expect(next.type).toBe(POMODORO_CONSTANTS.SESSION_TYPES.LONG_BREAK)
    })
  })
})
```

#### tests/integration/PomodoroIntegration.test.js - 統合テスト
```javascript
import { mount } from '@vue/test-utils'
import App from '@/App.vue'
import PomodoroTimer from '@/utils/PomodoroTimer.js'

describe('ポモドーロタイマー統合テスト', () => {
  let wrapper
  
  beforeEach(() => {
    wrapper = mount(App)
    jest.useFakeTimers()
  })

  afterEach(() => {
    wrapper.unmount()
    jest.useRealTimers()
  })

  test('セッション開始〜完了の完全フロー', async () => {
    const session = {
      id: 1,
      session_type: 'focus',
      planned_duration: 1, // 1分のテストセッション
      subject_area_id: 1
    }

    // セッション開始
    await wrapper.vm.startGlobalPomodoroTimer(session)
    
    expect(wrapper.vm.pomodoroDisplay.isActive).toBe(true)
    expect(wrapper.vm.pomodoroDisplay.timeRemaining).toBe(60)
    
    // 30秒経過
    jest.advanceTimersByTime(30000)
    await wrapper.vm.$nextTick()
    
    expect(wrapper.vm.pomodoroDisplay.timeRemaining).toBe(30)
    expect(wrapper.vm.pomodoroDisplay.progress).toBeCloseTo(50)
    
    // 完了まで経過
    jest.advanceTimersByTime(30000)
    await wrapper.vm.$nextTick()
    
    expect(wrapper.vm.pomodoroDisplay.isActive).toBe(false)
    expect(wrapper.vm.pomodoroDisplay.timeRemaining).toBe(0)
  })

  test('ページリロード時の状態復元', async () => {
    const session = {
      id: 1,
      session_type: 'focus',
      planned_duration: 2
    }

    // セッション開始して30秒経過
    await wrapper.vm.startGlobalPomodoroTimer(session)
    jest.advanceTimersByTime(30000)
    
    // 状態を保存
    wrapper.vm.debouncedSaveTimerState()
    
    // 新しいコンポーネントを作成（リロード模擬）
    const newWrapper = mount(App)
    
    // 状態復元
    newWrapper.vm.restoreTimerStateFromStorage()
    await newWrapper.vm.$nextTick()
    
    // 残り時間が正しく復元されていることを確認
    expect(newWrapper.vm.pomodoroDisplay.timeRemaining).toBeLessThanOrEqual(90)
    expect(newWrapper.vm.pomodoroDisplay.timeRemaining).toBeGreaterThan(80)
    
    newWrapper.unmount()
  })
})
```

---

## 📅 実装スケジュール

### 第1週: フェーズ1実装（緊急対応）
- [ ] **PomodoroTimer.js v2.0 実装** (2.5日)
  - startTime追跡、レースコンディション対策、serialization修正
- [ ] **constants.js, debounce.js 実装** (0.5日)
  - ストレージ頻度調整、バリデーション追加
- [ ] **App.vue 統合（二重管理排除）** (1.5日)
  - 単一タイマーサービス化、UI状態分離
- [ ] **基本テスト作成** (1日)
  - レースコンディション検証、startTime検証
- [ ] **Issue #62 修正確認** (0.5日)
  - 負の値防止、復元時の自動完了

### 第2週: フェーズ2実装（安定性向上）
- [ ] **PomodoroSessionManager 実装** (2日)
  - 4セッションサイクル管理、状態追跡
- [ ] **エラーハンドリング強化** (1.5日)
  - バージョン管理、フォールバック処理、データ検証
- [ ] **OfflineQueue 実装** (1.5日)
  - オフライン対応、リトライ機能
- [ ] **統合テスト・パフォーマンステスト** (1日)

### 第3週: フェーズ3準備（長期改善）
- [ ] **TypeScript準備** (1日)
  - 型定義、インターフェース設計
- [ ] **Pinia ストア設計・実装** (2日)
  - 状態管理分離、責任分離
- [ ] **既存コードからの移行** (1.5日)
  - 段階的移行、互換性確保
- [ ] **総合テスト・ドキュメント** (1.5日)

---

## 🎯 成功指標 - 修正版

### Issue #62 完全解決確認
- [ ] **負の値の完全排除**: タイマーが-1を表示しない
- [ ] **レースコンディション解決**: 完了処理の重複実行防止
- [ ] **復元時の正確性**: ページリロード後の適切な状態復元
- [ ] **actualDuration の正確性**: startTime ベースの正確な時間計算

### 設計改善確認
- [ ] **二重管理排除**: globalPomodoroTimer と PomodoroTimer の統一
- [ ] **serialization 完全性**: startTime、pausedAt を含む完全な状態保存
- [ ] **pause/resume 正確性**: 一時停止・再開時の正確な時間計算
- [ ] **エラー耐性**: 破損データ・期限切れ時の適切なフォールバック

### パフォーマンス改善
- [ ] **ストレージ頻度最適化**: 3秒間隔でのデバウンス保存
- [ ] **メモリリーク完全排除**: setInterval の確実なクリーンアップ
- [ ] **タイマー精度向上**: デッドラインベースによる±1秒以内の精度

### サイクル管理精度
- [ ] **4セッションサイクル**: 4回フォーカス後の長い休憩確保
- [ ] **サイクルリセット**: 長い休憩後のフォーカスカウントリセット
- [ ] **状態追跡**: focusCompletedCount の正確な管理

### 堅牢性向上
- [ ] **バージョン管理**: シリアライゼーションバージョンによる互換性確保
- [ ] **データ検証**: 不正なデータに対する適切なエラーハンドリング
- [ ] **ネットワーク耐性**: API エラー時のオフライン対応
- [ ] **ブラウザ環境対応**: バックグラウンド状態・複数タブでの適切な動作

---

## 🔍 レビュー指摘事項対応確認

### ✅ 対応済み項目
1. **startTime の追加**: `PomodoroTimer.js` に `startTime` プロパティ追加
2. **serialize/deserialize 修正**: `startTime`, `pausedAt`, `sessionData` を含む完全な状態管理
3. **二重管理排除**: `globalPomodoroTimer` を computed property 化
4. **レースコンディション対策**: `isCompleting` フラグによる重複実行防止
5. **ストレージ頻度調整**: `STORAGE_DEBOUNCE_MS: 3000` (3秒間隔)
6. **状態復元厳密化**: データ検証、バージョン管理、フォールバック処理
7. **責任分離**: タイマー=時間管理、App.vue=UI同期+API連携

### 📋 追加検証項目
- **レースコンディション検証テスト**: 完了処理重複防止の確認
- **復元テスト**: 保存→リロード→正確な残り時間復元
- **パフォーマンステスト**: 3秒間隔保存でのUI滑らかさ
- **サイクルロジックテスト**: 4フォーカス→長い休憩の確実な実行
- **破損データテスト**: 不正データでのフォールバック動作

---

## 📄 関連ドキュメント

- [Issue #62](https://github.com/R-Tsukada/studylog/issues/62) - 対象問題
- [ポモドーロタイマー コードレビュー統合報告書](レビューで受領) - 問題分析
- [既存実装解析レポート](../analysis/) - 現状把握

---

## 🏆 実装完了基準

### Phase1 完了条件
1. Issue #62 の完全解決（負の値が出現しない）
2. レースコンディションの完全排除
3. startTime を含む正確な actualDuration 計算
4. 二重管理の完全排除
5. 全ユニットテストの合格

### Phase2 完了条件
1. 4セッションサイクルの正確な動作
2. オフライン対応の実装
3. エラーハンドリングの完全実装
4. パフォーマンステストの合格

### Phase3 完了条件
1. TypeScript 導入完了
2. Pinia への完全移行
3. 責任分離の完成
4. 総合テストの合格

---

## 👥 レビュー・承認 - 修正版

- [x] **コードレビュー**: 設計問題の特定・修正完了
- [ ] **技術レビュー**: v2.0 設計の技術的妥当性確認
- [ ] **実装レビュー**: Phase1 実装コードの品質確認
- [ ] **テストレビュー**: テストケースの網羅性確認
- [ ] **最終承認**: 本格実装開始承認

---

*最終更新: 2025年8月16日*
*作成者: Claude Code Assistant*
*バージョン: 2.0 (レビュー指摘事項反映版)*
*対応 Issue: #62 - ポモドーロのカウントがマイナスになる問題*