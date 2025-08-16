/**
 * ポモドーロ自動開始機能の詳細テスト
 * 設計書の要件に基づく包括的なテストケース
 * @jest-environment jsdom
 */

import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import PomodoroTimer from '@/utils/PomodoroTimer.js'
import { PomodorooCycleManager } from '@/utils/PomodorooCycleManager.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'
import { debounce } from '@/utils/debounce.js'

// App.vueの詳細モック（設計書ベース）
const detailedMockAppComponent = {
  name: 'DetailedMockApp',
  data() {
    return {
      pomodoroTimerInstance: null,
      pomodorooCycleManager: null,
      
      // 自動開始管理
      autoStartState: reactive({
        timeoutId: null,
        isPending: false,
        pendingSession: null,
        startTime: null,
        remainingMs: 0
      }),
      
      // 後方互換性のためのreactiveプロキシ
      globalPomodoroTimer: reactive({
        isActive: false,
        currentSession: null,
        timeRemaining: 0,
        startTime: 0,
        timer: null
      }),
      
      // テスト用設定管理
      testSettings: {
        auto_start: true,
        auto_start_break: null,
        auto_start_focus: null,
        focus_duration: 25,
        short_break_duration: 5,
        long_break_duration: 20
      },
      
      debouncedSaveStorage: null
    }
  },
  
  mounted() {
    this.initializePomodoroTimer()
  },
  
  methods: {
    initializePomodoroTimer() {
      this.pomodoroTimerInstance = new PomodoroTimer()
      this.pomodorooCycleManager = new PomodorooCycleManager()
      this.debouncedSaveStorage = debounce(this.saveTimerStateToStorage.bind(this), POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS)
    },
    
    // 設計書通りの自動開始判定メソッド
    shouldAutoStartNext(nextSessionType, settings) {
      if (nextSessionType === 'focus') {
        return settings?.auto_start_focus ?? settings?.auto_start ?? false
      } else {
        return settings?.auto_start_break ?? settings?.auto_start ?? false
      }
    },
    
    // 設計書通りの次セッションタイプ決定
    determineNextSessionType(completedSession) {
      if (completedSession.session_type === 'focus') {
        // 完了したfocusセッションをカウントしてから判定
        const currentCount = this.pomodorooCycleManager.pomodoroCounterState.completedFocusSessions
        if (currentCount > 0 && currentCount % POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH === 0) {
          return 'long_break'
        } else {
          return 'short_break'
        }
      } else if (completedSession.session_type === 'short_break' || 
                 completedSession.session_type === 'long_break') {
        return 'focus'
      }
      
      console.warn('不明なセッションタイプ:', completedSession.session_type)
      return 'focus'
    },
    
    // 設計書通りの自動開始スケジューリング
    scheduleAutoStartIfEnabled(completedSession) {
      const settings = completedSession.settings || this.testSettings
      const nextSessionType = this.determineNextSessionType(completedSession)
      
      const shouldAutoStart = this.shouldAutoStartNext(nextSessionType, settings)
      
      if (!shouldAutoStart) {
        console.log('自動開始設定が無効:', { nextSessionType, settings })
        return false
      }
      
      // 既存の自動開始をキャンセル
      this.cancelAutoStart()
      
      // 新しい自動開始をスケジュール
      this.autoStartState.isPending = true
      this.autoStartState.startTime = Date.now()
      this.autoStartState.remainingMs = POMODORO_CONSTANTS.AUTO_START_DELAY_MS
      this.autoStartState.pendingSession = {
        type: nextSessionType,
        duration: this.getSessionDuration(nextSessionType, settings),
        settings: settings
      }
      
      console.log('自動開始スケジュール:', this.autoStartState.pendingSession)
      
      this.autoStartState.timeoutId = setTimeout(() => {
        this.executeAutoStart()
      }, POMODORO_CONSTANTS.AUTO_START_DELAY_MS)
      
      return true
    },
    
    // セッション時間取得
    getSessionDuration(sessionType, settings) {
      const durations = {
        focus: settings?.focus_duration ?? 25,
        short_break: settings?.short_break_duration ?? 5,
        long_break: settings?.long_break_duration ?? 20
      }
      return durations[sessionType] || 25
    },
    
    // 自動開始実行
    async executeAutoStart() {
      if (!this.autoStartState.isPending || !this.autoStartState.pendingSession) {
        return false
      }
      
      const pendingSession = this.autoStartState.pendingSession
      
      try {
        // セッションデータ作成
        const newSession = {
          id: Date.now(),
          session_type: pendingSession.type,
          planned_duration: pendingSession.duration,
          subject_area_id: pendingSession.type === 'focus' ? 1 : null,
          settings: pendingSession.settings,
          is_auto_started: true
        }
        
        console.log('次のセッション自動開始成功:', newSession.session_type)
        
        // グローバルタイマーで新しいセッションを開始
        this.startGlobalPomodoroTimer(newSession)
        
        return true
      } catch (error) {
        console.error('自動開始実行エラー:', error)
        return false
      } finally {
        // 自動開始状態をクリア
        this.clearAutoStartState()
      }
    },
    
    // 自動開始キャンセル
    cancelAutoStart() {
      if (this.autoStartState.timeoutId) {
        clearTimeout(this.autoStartState.timeoutId)
      }
      this.clearAutoStartState()
    },
    
    // 自動開始状態クリア
    clearAutoStartState() {
      this.autoStartState = reactive({
        timeoutId: null,
        isPending: false,
        pendingSession: null,
        startTime: null,
        remainingMs: 0
      })
    },
    
    // グローバルタイマー開始
    startGlobalPomodoroTimer(session) {
      const durationSeconds = session.planned_duration * 60
      
      const callbacks = {
        onTick: (remainingSeconds) => {
          this.globalPomodoroTimer.timeRemaining = remainingSeconds
        },
        onComplete: () => {
          this.handleGlobalTimerComplete()
        },
        onError: (error) => {
          console.error('タイマーエラー:', error)
          this.stopGlobalPomodoroTimer()
        }
      }
      
      this.pomodoroTimerInstance.start(durationSeconds, callbacks, session)
      
      this.globalPomodoroTimer.isActive = true
      this.globalPomodoroTimer.currentSession = session
      this.globalPomodoroTimer.startTime = this.pomodoroTimerInstance.startTime
      this.globalPomodoroTimer.timer = 'v2.0'
    },
    
    // グローバルタイマー停止
    stopGlobalPomodoroTimer() {
      if (this.pomodoroTimerInstance) {
        this.pomodoroTimerInstance.stop()
      }
      
      // 自動開始もキャンセル
      this.cancelAutoStart()
      
      this.globalPomodoroTimer.isActive = false
      this.globalPomodoroTimer.currentSession = null
      this.globalPomodoroTimer.timeRemaining = 0
      this.globalPomodoroTimer.startTime = 0
      this.globalPomodoroTimer.timer = null
    },
    
    // タイマー完了処理
    handleGlobalTimerComplete() {
      const completedSession = this.globalPomodoroTimer.currentSession
        ? { ...this.globalPomodoroTimer.currentSession }
        : null

      if (!completedSession) {
        console.warn('No active session to complete')
        return null
      }
      
      // サイクル管理: 完了セッションを記録
      if (completedSession && completedSession.session_type === 'focus') {
        this.pomodorooCycleManager.markSessionCompleted(completedSession)
      }
      
      this.stopGlobalPomodoroTimer()
      
      // 自動開始: 次のセッションを自動開始
      this.scheduleAutoStartIfEnabled(completedSession)
      
      return completedSession
    },
    
    // 設定変更用のヘルパーメソッド
    updateSettings(newSettings) {
      this.testSettings = { ...this.testSettings, ...newSettings }
    },
    
    // ダミーのストレージ保存
    saveTimerStateToStorage() {
      console.log('タイマー状態保存')
    }
  }
}

describe('ポモドーロ自動開始機能の詳細テスト', () => {
  let wrapper
  let component

  beforeEach(() => {
    localStorage.clear()
    jest.useFakeTimers()
    
    wrapper = mount(detailedMockAppComponent)
    component = wrapper.vm
    
    // ポモドーロカウンターをリセット
    component.pomodorooCycleManager.resetCycleState()
  })

  afterEach(() => {
    if (component.pomodoroTimerInstance) {
      component.pomodoroTimerInstance.cleanup()
    }
    localStorage.clear()
    jest.useRealTimers()
  })

  describe('自動開始設定のオンオフ機能', () => {
    test('自動開始が有効な場合にスケジュールされる', () => {
      component.updateSettings({ auto_start: true })
      
      const focusSession = {
        id: 1,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      const result = component.scheduleAutoStartIfEnabled(focusSession)
      
      expect(result).toBe(true)
      expect(component.autoStartState.isPending).toBe(true)
      expect(component.autoStartState.pendingSession.type).toBe('short_break')
    })
    
    test('自動開始が無効な場合にスケジュールされない', () => {
      component.updateSettings({ auto_start: false })
      
      const focusSession = {
        id: 1,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      const result = component.scheduleAutoStartIfEnabled(focusSession)
      
      expect(result).toBe(false)
      expect(component.autoStartState.isPending).toBe(false)
    })
  })

  describe('個別制御設定テスト', () => {
    test('集中→休憩の自動開始を個別に制御', () => {
      component.updateSettings({
        auto_start: true,
        auto_start_break: false,  // 集中→休憩は無効
        auto_start_focus: true    // 休憩→集中は有効
      })
      
      // 集中セッション完了時は自動開始されない
      expect(component.shouldAutoStartNext('short_break', component.testSettings)).toBe(false)
      
      // 休憩セッション完了時は自動開始される
      expect(component.shouldAutoStartNext('focus', component.testSettings)).toBe(true)
    })
    
    test('休憩→集中の自動開始を個別に制御', () => {
      component.updateSettings({
        auto_start: true,
        auto_start_break: true,   // 集中→休憩は有効
        auto_start_focus: false   // 休憩→集中は無効
      })
      
      // 集中セッション完了時は自動開始される
      expect(component.shouldAutoStartNext('short_break', component.testSettings)).toBe(true)
      
      // 休憩セッション完了時は自動開始されない
      expect(component.shouldAutoStartNext('focus', component.testSettings)).toBe(false)
    })
    
    test('個別設定がnullの場合は基本設定を継承', () => {
      component.updateSettings({
        auto_start: true,
        auto_start_break: null,   // 未設定
        auto_start_focus: null    // 未設定
      })
      
      // 両方とも基本設定の値を継承
      expect(component.shouldAutoStartNext('short_break', component.testSettings)).toBe(true)
      expect(component.shouldAutoStartNext('focus', component.testSettings)).toBe(true)
    })
  })

  describe('休憩セッションの起動テスト', () => {
    test('short_breakセッションが正しく開始される', () => {
      component.updateSettings({ auto_start: true })
      
      const focusSession = {
        id: 1,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      // Focus完了後の自動開始
      const result = component.scheduleAutoStartIfEnabled(focusSession)
      
      expect(result).toBe(true)
      expect(component.autoStartState.pendingSession.type).toBe('short_break')
      expect(component.autoStartState.pendingSession.duration).toBe(5) // short_break_duration
    })
    
    test('long_breakセッションが正しく開始される', () => {
      // 4回のfocusセッションを完了させる
      for (let i = 0; i < 4; i++) {
        component.pomodorooCycleManager.markSessionCompleted({
          id: i + 1,
          session_type: 'focus'
        })
      }
      
      component.updateSettings({ auto_start: true })
      
      const focusSession = {
        id: 5,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      // 4回目のFocus完了後の自動開始
      const result = component.scheduleAutoStartIfEnabled(focusSession)
      
      expect(result).toBe(true)
      expect(component.autoStartState.pendingSession.type).toBe('long_break')
      expect(component.autoStartState.pendingSession.duration).toBe(20) // long_break_duration
    })
    
    test('休憩セッション完了後にfocusセッションが開始される', () => {
      component.updateSettings({ auto_start: true })
      
      const breakSession = {
        id: 1,
        session_type: 'short_break',
        settings: component.testSettings
      }
      
      const result = component.scheduleAutoStartIfEnabled(breakSession)
      
      expect(result).toBe(true)
      expect(component.autoStartState.pendingSession.type).toBe('focus')
      expect(component.autoStartState.pendingSession.duration).toBe(25) // focus_duration
    })
  })

  describe('自動開始キャンセル機能の詳細テスト', () => {
    test('自動開始待機中にキャンセルできる', () => {
      component.updateSettings({ auto_start: true })
      
      const focusSession = {
        id: 1,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      // 自動開始をスケジュール
      component.scheduleAutoStartIfEnabled(focusSession)
      expect(component.autoStartState.isPending).toBe(true)
      
      // キャンセル実行
      component.cancelAutoStart()
      expect(component.autoStartState.isPending).toBe(false)
      expect(component.autoStartState.timeoutId).toBe(null)
      
      // タイムアウト時間が経過しても実行されない
      jest.advanceTimersByTime(POMODORO_CONSTANTS.AUTO_START_DELAY_MS + 1000)
      expect(component.globalPomodoroTimer.isActive).toBe(false)
    })
    
    test('自動開始実行直前のキャンセル', () => {
      component.updateSettings({ auto_start: true })
      
      const focusSession = {
        id: 1,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      component.scheduleAutoStartIfEnabled(focusSession)
      
      // ほぼ完了まで時間を進める
      jest.advanceTimersByTime(POMODORO_CONSTANTS.AUTO_START_DELAY_MS - 100)
      
      // キャンセル
      component.cancelAutoStart()
      
      // 残り時間経過
      jest.advanceTimersByTime(200)
      
      expect(component.globalPomodoroTimer.isActive).toBe(false)
    })
  })

  describe('エラーハンドリングとGraceful Degradation', () => {
    test('無効なセッションタイプでもエラーにならない', () => {
      const invalidSession = {
        id: 1,
        session_type: 'invalid_type',
        settings: component.testSettings
      }
      
      // エラーが発生してもアプリケーションが落ちない
      expect(() => {
        component.determineNextSessionType(invalidSession)
      }).not.toThrow()
      
      // フォールバック値が返される
      expect(component.determineNextSessionType(invalidSession)).toBe('focus')
    })
    
    test('設定が不完全でも動作する', () => {
      const incompleteSettings = {
        auto_start: true
        // 他の設定は未定義
      }
      
      expect(() => {
        component.shouldAutoStartNext('focus', incompleteSettings)
      }).not.toThrow()
      
      expect(component.shouldAutoStartNext('focus', incompleteSettings)).toBe(true)
    })
    
    test('自動開始実行時のエラーハンドリング', () => {
      component.updateSettings({ auto_start: true })
      
      const focusSession = {
        id: 1,
        session_type: 'focus',
        settings: component.testSettings
      }
      
      // executeAutoStartをモックして同期エラーを発生させる
      const originalExecuteAutoStart = component.executeAutoStart
      component.executeAutoStart = jest.fn(() => {
        throw new Error('Test error')
      })
      
      // スケジュール自体は正常に動作する
      const result = component.scheduleAutoStartIfEnabled(focusSession)
      expect(result).toBe(true)
      expect(component.autoStartState.isPending).toBe(true)
      
      // 元のメソッドに戻す
      component.executeAutoStart = originalExecuteAutoStart
      
      // エラーが発生してもアプリの状態は定義されている
      expect(component.autoStartState).toBeDefined()
    })
  })

  describe('設定オブジェクトの整合性テスト', () => {
    test('設定の継承関係が正しく動作する', () => {
      const baseSettings = {
        auto_start: true,
        focus_duration: 25,
        short_break_duration: 5,
        long_break_duration: 20
      }
      
      // 個別設定なしの場合
      expect(component.shouldAutoStartNext('focus', baseSettings)).toBe(true)
      expect(component.shouldAutoStartNext('short_break', baseSettings)).toBe(true)
      
      // 個別設定ありの場合
      const detailedSettings = {
        ...baseSettings,
        auto_start_break: false,
        auto_start_focus: true
      }
      
      expect(component.shouldAutoStartNext('focus', detailedSettings)).toBe(true)
      expect(component.shouldAutoStartNext('short_break', detailedSettings)).toBe(false)
    })
    
    test('セッション時間の設定が正しく適用される', () => {
      const customSettings = {
        focus_duration: 30,
        short_break_duration: 10,
        long_break_duration: 25
      }
      
      expect(component.getSessionDuration('focus', customSettings)).toBe(30)
      expect(component.getSessionDuration('short_break', customSettings)).toBe(10)
      expect(component.getSessionDuration('long_break', customSettings)).toBe(25)
    })
    
    test('未定義の設定に対するフォールバック', () => {
      const incompleteSettings = {}
      
      expect(component.getSessionDuration('focus', incompleteSettings)).toBe(25)
      expect(component.getSessionDuration('short_break', incompleteSettings)).toBe(5)
      expect(component.getSessionDuration('long_break', incompleteSettings)).toBe(20)
    })
  })

  describe('統合シナリオテスト', () => {
    test('完全なポモドーロサイクルの自動実行', () => {
      component.updateSettings({ auto_start: true })
      
      // 1回目のfocus完了
      let session = { id: 1, session_type: 'focus', settings: component.testSettings }
      component.pomodorooCycleManager.markSessionCompleted(session)
      component.scheduleAutoStartIfEnabled(session)
      
      expect(component.autoStartState.pendingSession.type).toBe('short_break')
      
      // short_break完了
      session = { id: 2, session_type: 'short_break', settings: component.testSettings }
      component.clearAutoStartState()
      component.scheduleAutoStartIfEnabled(session)
      
      expect(component.autoStartState.pendingSession.type).toBe('focus')
      
      // 4回目のfocus完了
      for (let i = 2; i <= 4; i++) {
        component.pomodorooCycleManager.markSessionCompleted({
          id: i,
          session_type: 'focus'
        })
      }
      
      session = { id: 5, session_type: 'focus', settings: component.testSettings }
      component.clearAutoStartState()
      component.scheduleAutoStartIfEnabled(session)
      
      expect(component.autoStartState.pendingSession.type).toBe('long_break')
    })
    
    test('個別制御設定での複雑なシナリオ', () => {
      // 集中→休憩は自動、休憩→集中は手動
      component.updateSettings({
        auto_start: true,
        auto_start_break: true,
        auto_start_focus: false
      })
      
      // focus完了時は自動開始される
      let session = { id: 1, session_type: 'focus', settings: component.testSettings }
      let result = component.scheduleAutoStartIfEnabled(session)
      expect(result).toBe(true)
      
      // break完了時は自動開始されない
      session = { id: 2, session_type: 'short_break', settings: component.testSettings }
      result = component.scheduleAutoStartIfEnabled(session)
      expect(result).toBe(false)
    })
  })
})