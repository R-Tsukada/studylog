/**
 * App.vue × PomodoroTimer v2.0 統合テスト
 * Issue #62 対応: App.vueのグローバルタイマーをv2.0に置き換える
 */

import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import PomodoroTimer from '@/utils/PomodoroTimer.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'
import { debounce } from '@/utils/debounce.js'

// App.vueの簡易モック（統合テスト用）
const mockAppComponent = {
  name: 'MockApp',
  data() {
    return {
      // 新しいポモドーロタイマー（v2.0）
      pomodoroTimerInstance: null,
      
      // 後方互換性のためのreactiveプロキシ
      globalPomodoroTimer: reactive({
        isActive: false,
        currentSession: null,
        timeRemaining: 0,
        startTime: 0,
        timer: null
      }),
      
      // デバウンスされたストレージ保存
      debouncedSaveStorage: null
    }
  },
  
  mounted() {
    this.initializePomodoroTimer()
  },
  
  methods: {
    initializePomodoroTimer() {
      this.pomodoroTimerInstance = new PomodoroTimer()
      
      // デバウンスされたストレージ保存関数を作成
      this.debouncedSaveStorage = debounce(() => {
        this.saveTimerStateToStorage()
      }, POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS)
    },
    
    // 新しいAPI: v2.0タイマーを使用
    startGlobalPomodoroTimer(session) {
      console.log('グローバルタイマー開始 (v2.0):', session)
      
      const durationSeconds = session.planned_duration * 60
      
      const callbacks = {
        onTick: (remainingSeconds) => {
          // 後方互換性のため既存のreactiveオブジェクトを更新
          this.globalPomodoroTimer.timeRemaining = remainingSeconds
          this.debouncedSaveStorage()
        },
        onComplete: () => {
          this.handleGlobalTimerComplete()
        },
        onError: (error) => {
          console.error('タイマーエラー:', error)
          this.stopGlobalPomodoroTimer()
        }
      }
      
      // v2.0タイマー開始
      this.pomodoroTimerInstance.start(durationSeconds, callbacks, session)
      
      // 後方互換性のため既存のreactiveオブジェクトを更新
      this.globalPomodoroTimer.isActive = true
      this.globalPomodoroTimer.currentSession = session
      this.globalPomodoroTimer.startTime = this.pomodoroTimerInstance.startTime
      this.globalPomodoroTimer.timer = 'v2.0' // 識別用
    },
    
    stopGlobalPomodoroTimer() {
      console.log('グローバルタイマー停止 (v2.0)')
      
      if (this.pomodoroTimerInstance) {
        this.pomodoroTimerInstance.stop()
      }
      
      // 後方互換性のため既存のreactiveオブジェクトをクリア
      this.globalPomodoroTimer.isActive = false
      this.globalPomodoroTimer.currentSession = null
      this.globalPomodoroTimer.timeRemaining = 0
      this.globalPomodoroTimer.startTime = 0
      this.globalPomodoroTimer.timer = null
      
      // localStorage をクリア
      localStorage.removeItem('pomodoroTimer')
    },
    
    pauseGlobalPomodoroTimer() {
      if (this.pomodoroTimerInstance) {
        this.pomodoroTimerInstance.pause()
      }
    },
    
    resumeGlobalPomodoroTimer() {
      if (this.pomodoroTimerInstance) {
        this.pomodoroTimerInstance.resume()
      }
    },
    
    handleGlobalTimerComplete() {
      console.log('ポモドーロタイマー完了 (v2.0)')
      const completedSession = { ...this.globalPomodoroTimer.currentSession }
      
      // 通知処理など（実際のApp.vueと同じ）
      this.stopGlobalPomodoroTimer()
      
      // テスト用にcompletedSessionを返す
      return completedSession
    },
    
    saveTimerStateToStorage() {
      if (this.pomodoroTimerInstance) {
        const serializedState = this.pomodoroTimerInstance.serialize()
        localStorage.setItem('pomodoroTimer', JSON.stringify(serializedState))
        console.log('タイマー状態保存 (v2.0)')
      }
    },
    
    restoreTimerStateFromStorage() {
      try {
        const saved = localStorage.getItem('pomodoroTimer')
        if (saved) {
          const state = JSON.parse(saved)
          
          if (this.pomodoroTimerInstance) {
            const callbacks = {
              onTick: (remainingSeconds) => {
                this.globalPomodoroTimer.timeRemaining = remainingSeconds
                this.debouncedSaveStorage()
              },
              onComplete: () => {
                this.handleGlobalTimerComplete()
              },
              onError: (error) => {
                console.error('復元時タイマーエラー:', error)
                this.stopGlobalPomodoroTimer()
              }
            }
            
            const restored = this.pomodoroTimerInstance.deserialize(state, callbacks)
            
            if (restored && this.pomodoroTimerInstance.state !== POMODORO_CONSTANTS.TIMER_STATES.IDLE) {
              // 後方互換性のため既存のreactiveオブジェクトを更新
              this.globalPomodoroTimer.isActive = this.pomodoroTimerInstance.state === POMODORO_CONSTANTS.TIMER_STATES.RUNNING
              this.globalPomodoroTimer.currentSession = this.pomodoroTimerInstance.sessionData
              this.globalPomodoroTimer.startTime = this.pomodoroTimerInstance.startTime
              this.globalPomodoroTimer.timer = 'v2.0'
              
              console.log('タイマー状態復元成功 (v2.0)')
            }
          }
        }
      } catch (error) {
        console.error('タイマー状態復元エラー (v2.0):', error)
        localStorage.removeItem('pomodoroTimer')
      }
    }
  }
}

describe('App.vue × PomodoroTimer v2.0 統合テスト', () => {
  let wrapper
  let component

  beforeEach(() => {
    // localStorage クリア
    localStorage.clear()
    jest.useFakeTimers()
    
    wrapper = mount(mockAppComponent)
    component = wrapper.vm
  })

  afterEach(() => {
    if (component.pomodoroTimerInstance) {
      component.pomodoroTimerInstance.cleanup()
    }
    localStorage.clear()
    jest.useRealTimers()
  })

  describe('基本統合機能', () => {
    test('App.vueにPomodoroTimer v2.0が正しく統合される', () => {
      expect(component.pomodoroTimerInstance).toBeInstanceOf(PomodoroTimer)
      expect(component.globalPomodoroTimer).toBeDefined()
      expect(component.debouncedSaveStorage).toBeDefined()
    })

    test('startGlobalPomodoroTimerがv2.0タイマーを開始する', () => {
      const session = {
        id: 1,
        session_type: 'focus',
        planned_duration: 25,
        subject_area_id: 1
      }

      component.startGlobalPomodoroTimer(session)

      // v2.0タイマーが開始されている
      expect(component.pomodoroTimerInstance.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.RUNNING)
      expect(component.pomodoroTimerInstance.sessionData).toEqual(session)
      
      // 後方互換性のためreactiveオブジェクトも更新されている
      expect(component.globalPomodoroTimer.isActive).toBe(true)
      expect(component.globalPomodoroTimer.currentSession).toEqual(session)
      expect(component.globalPomodoroTimer.timer).toBe('v2.0')
    })

    test('stopGlobalPomodoroTimerがv2.0タイマーを停止する', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 25 }
      
      component.startGlobalPomodoroTimer(session)
      component.stopGlobalPomodoroTimer()

      // v2.0タイマーが停止されている
      expect(component.pomodoroTimerInstance.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.IDLE)
      
      // 後方互換性のためreactiveオブジェクトもクリアされている
      expect(component.globalPomodoroTimer.isActive).toBe(false)
      expect(component.globalPomodoroTimer.currentSession).toBe(null)
      expect(component.globalPomodoroTimer.timer).toBe(null)
    })
  })

  describe('Issue #62: レースコンディション修正検証', () => {
    test('タイマーカウントが負の値にならない', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 1 } // 1分
      let capturedTimeRemaining = []

      // onTickコールバックをモックして値を記録
      const originalOnTick = component.pomodoroTimerInstance.callbacks.onTick
      jest.spyOn(component, 'startGlobalPomodoroTimer').mockImplementation((session) => {
        const durationSeconds = session.planned_duration * 60
        
        const callbacks = {
          onTick: (remainingSeconds) => {
            capturedTimeRemaining.push(remainingSeconds)
            component.globalPomodoroTimer.timeRemaining = remainingSeconds
          },
          onComplete: () => component.handleGlobalTimerComplete()
        }
        
        component.pomodoroTimerInstance.start(durationSeconds, callbacks, session)
        component.globalPomodoroTimer.isActive = true
        component.globalPomodoroTimer.currentSession = session
      })

      component.startGlobalPomodoroTimer(session)

      // 61秒経過させる（予定時間60秒を超過）
      jest.advanceTimersByTime(61000)

      // 記録された値がすべて0以上であることを確認
      capturedTimeRemaining.forEach(timeRemaining => {
        expect(timeRemaining).toBeGreaterThanOrEqual(0)
      })

      // 最後の値は0であることを確認
      expect(capturedTimeRemaining[capturedTimeRemaining.length - 1]).toBe(0)
    })

    test('完了処理が重複実行されない', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 1 }
      const completeSpy = jest.spyOn(component, 'handleGlobalTimerComplete')

      component.startGlobalPomodoroTimer(session)

      // 時間経過で完了
      jest.advanceTimersByTime(60000)

      // さらに時間が経過しても完了処理は1回のみ
      jest.advanceTimersByTime(5000)
      expect(completeSpy).toHaveBeenCalledTimes(1)
    })
  })

  describe('localStorage統合', () => {
    test('デバウンスされたストレージ保存が動作する', (done) => {
      const session = { id: 1, session_type: 'focus', planned_duration: 25 }
      const saveSpy = jest.spyOn(component, 'saveTimerStateToStorage')

      component.startGlobalPomodoroTimer(session)

      // 短時間で複数回onTickが呼ばれてもデバウンスされる
      jest.advanceTimersByTime(3000) // 3秒経過
      
      // デバウンス時間後にストレージ保存が1回だけ呼ばれることを確認
      setTimeout(() => {
        expect(saveSpy).toHaveBeenCalled()
        expect(localStorage.getItem('pomodoroTimer')).toBeTruthy()
        done()
      }, POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS + 100)

      jest.advanceTimersByTime(POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS + 100)
    })

    test('タイマー状態の復元が正しく動作する', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 25 }
      
      // タイマーを開始して状態を保存
      component.startGlobalPomodoroTimer(session)
      jest.advanceTimersByTime(5000) // 5秒経過
      component.saveTimerStateToStorage()
      
      // 新しいコンポーネントインスタンスで復元
      const newWrapper = mount(mockAppComponent)
      const newComponent = newWrapper.vm
      
      newComponent.restoreTimerStateFromStorage()
      
      // 復元されたタイマー状態を確認
      expect(newComponent.globalPomodoroTimer.isActive).toBe(true)
      expect(newComponent.globalPomodoroTimer.currentSession).toEqual(session)
      expect(newComponent.pomodoroTimerInstance.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.RUNNING)
      
      newComponent.pomodoroTimerInstance.cleanup()
    })
  })

  describe('pause/resume機能', () => {
    test('一時停止・再開が正しく動作する', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 25 }
      
      component.startGlobalPomodoroTimer(session)
      jest.advanceTimersByTime(5000) // 5秒経過
      
      // 一時停止
      component.pauseGlobalPomodoroTimer()
      expect(component.pomodoroTimerInstance.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.PAUSED)
      
      // さらに時間が経過してもカウントは止まる
      const pausedRemaining = component.pomodoroTimerInstance.pausedRemaining
      jest.advanceTimersByTime(3000)
      expect(component.pomodoroTimerInstance.pausedRemaining).toBe(pausedRemaining)
      
      // 再開
      component.resumeGlobalPomodoroTimer()
      expect(component.pomodoroTimerInstance.state).toBe(POMODORO_CONSTANTS.TIMER_STATES.RUNNING)
    })
  })

  describe('後方互換性', () => {
    test('既存のglobalPomodoroTimerオブジェクトが引き続き動作する', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 25 }
      
      component.startGlobalPomodoroTimer(session)
      
      // 既存のコードが参照するプロパティが正しく更新される
      expect(component.globalPomodoroTimer.isActive).toBe(true)
      expect(component.globalPomodoroTimer.currentSession).toEqual(session)
      expect(typeof component.globalPomodoroTimer.startTime).toBe('number')
      expect(component.globalPomodoroTimer.timeRemaining).toBeGreaterThan(0)
    })

    test('テンプレート内の時間表示が正しく動作する', () => {
      const session = { id: 1, session_type: 'focus', planned_duration: 2 } // 2分
      
      component.startGlobalPomodoroTimer(session)
      
      // 30秒経過
      jest.advanceTimersByTime(30000)
      
      // 残り時間が正しく計算される（1分30秒 = 90秒）
      expect(component.globalPomodoroTimer.timeRemaining).toBe(90)
      
      // テンプレートで使用する時間フォーマット計算
      const minutes = Math.floor(component.globalPomodoroTimer.timeRemaining / 60)
      const seconds = component.globalPomodoroTimer.timeRemaining % 60
      
      expect(minutes).toBe(1)
      expect(seconds).toBe(30)
    })
  })
})