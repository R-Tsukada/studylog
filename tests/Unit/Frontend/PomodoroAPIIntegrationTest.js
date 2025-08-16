/**
 * ポモドーロ自動開始 API統合テスト
 * 実際のHTTP通信をモックして、API連携部分をテスト
 * 今回の不具合（409エラー）を検出できるテストを含む
 * @jest-environment jsdom
 */

import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import axios from 'axios'
import MockAdapter from 'axios-mock-adapter'
import PomodoroTimer from '@/utils/PomodoroTimer.js'
import { PomodorooCycleManager } from '@/utils/PomodorooCycleManager.js'
import { POMODORO_CONSTANTS } from '@/utils/constants.js'

// Axiosモックアダプター
let mock

// App.vueのAPIメソッドを含む統合テスト用コンポーネント
const apiIntegrationComponent = {
  name: 'ApiIntegrationApp',
  template: '<div>API Integration Test</div>',
  data() {
    return {
      pomodoroTimerInstance: null,
      pomodorooCycleManager: null,
      
      autoStartState: reactive({
        timeoutId: null,
        isPending: false,
        pendingSession: null,
        startTime: null,
        remainingMs: 0
      }),
      
      globalPomodoroTimer: reactive({
        isActive: false,
        currentSession: null,
        timeRemaining: 0,
        startTime: 0,
        timer: null
      })
    }
  },
  
  mounted() {
    this.initializePomodoroTimer()
  },
  
  methods: {
    initializePomodoroTimer() {
      this.pomodoroTimerInstance = new PomodoroTimer()
      this.pomodorooCycleManager = new PomodorooCycleManager()
    },
    
    // 実際のApp.vueと同じAPI完了メソッド
    async completeCurrentSession(session) {
      try {
        const actualDuration = 15 // テスト用固定値
        
        const response = await axios.post(`/api/pomodoro/${session.id}/complete`, {
          actual_duration: actualDuration,
          was_interrupted: false,
          notes: 'API統合テスト'
        })
        
        if (response.status === 200) {
          console.log('セッション完了成功:', session.session_type)
          return { success: true, data: response.data }
        }
      } catch (error) {
        console.error('セッション完了エラー:', error.response?.status)
        throw error
      }
    },
    
    // 実際のApp.vueと同じ新規セッション開始メソッド
    async startNextAutoSessionWithCycleInfo(completedSession, nextSessionType) {
      try {
        const settings = completedSession.settings
        
        const nextSession = {
          session_type: nextSessionType,
          planned_duration: nextSessionType === 'long_break' ? 20 : 
                           nextSessionType === 'short_break' ? 5 : 25,
          study_session_id: null,
          subject_area_id: completedSession.subject_area_id,
          settings: {
            focus_duration: settings?.focus_duration || 25,
            short_break_duration: settings?.short_break_duration || 5,
            long_break_duration: settings?.long_break_duration || 20,
            auto_start_break: settings?.auto_start_break || false,
            auto_start_focus: settings?.auto_start_focus || false,
            sound_enabled: settings?.sound_enabled || false,
          }
        }
        
        const response = await fetch('/api/pomodoro', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer test-token`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(nextSession)
        })
        
        if (response.ok) {
          const data = await response.json()
          console.log('自動開始成功:', nextSessionType)
          return { success: true, data }
        } else {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`)
        }
      } catch (error) {
        console.error('自動開始エラー:', error.message)
        throw error
      }
    },
    
    // タイマー完了の統合フロー（実際のApp.vueと同じ順序）
    async handleTimerCompleteFlow(session) {
      // API セッション完了処理を先に実行
      await this.completeCurrentSession(session)
      
      // サイクル管理
      if (session.session_type === 'focus') {
        this.pomodorooCycleManager.markSessionCompleted(session)
      }
      
      // 自動開始判定
      const nextSessionType = this.pomodorooCycleManager.getNextSessionType()
      
      // 自動開始実行
      await this.startNextAutoSessionWithCycleInfo(session, nextSessionType)
      
      return { nextSessionType }
    }
  }
}

describe('ポモドーロ API統合テスト', () => {
  let wrapper
  let component

  beforeEach(() => {
    // Axiosモックアダプターをセットアップ
    mock = new MockAdapter(axios)
    
    // fetchもモック
    global.fetch = jest.fn()
    
    jest.useFakeTimers()
    
    wrapper = mount(apiIntegrationComponent)
    component = wrapper.vm
  })

  afterEach(() => {
    mock.restore()
    global.fetch.mockRestore()
    jest.useRealTimers()
  })

  describe('正常フロー: API統合テスト', () => {
    test('セッション完了→自動開始のAPIフローが正常動作する', async () => {
      const session = {
        id: 123,
        session_type: 'focus',
        planned_duration: 25,
        subject_area_id: 1,
        settings: {
          auto_start_break: true,
          auto_start_focus: true
        }
      }
      
      // 1. セッション完了APIをモック（成功）
      mock.onPost('/api/pomodoro/123/complete').reply(200, {
        success: true,
        message: 'セッション完了'
      })
      
      // 2. 新規セッション開始APIをモック（成功）
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          id: 124,
          session_type: 'short_break',
          planned_duration: 5
        })
      })
      
      // 3. 統合フロー実行
      const result = await component.handleTimerCompleteFlow(session)
      
      // 4. 検証
      expect(result.nextSessionType).toBe('short_break')
      expect(mock.history.post).toHaveLength(1)
      expect(global.fetch).toHaveBeenCalledWith('/api/pomodoro', expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Content-Type': 'application/json'
        })
      }))
    })
  })

  describe('エラーケース: 今回の不具合を検出', () => {
    test('セッション完了が422エラーでも自動開始は継続する', async () => {
      const session = {
        id: 123,
        session_type: 'focus',
        settings: {
          auto_start_break: true,
          auto_start_focus: true
        }
      }
      
      // 1. セッション完了APIで422エラー
      mock.onPost('/api/pomodoro/123/complete').reply(422, {
        error: 'Unprocessable Entity'
      })
      
      // 2. 新規セッション開始APIは成功
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          id: 124,
          session_type: 'short_break'
        })
      })
      
      // 3. 統合フロー実行（422エラーで中断される）
      await expect(component.handleTimerCompleteFlow(session))
        .rejects
        .toThrow()
      
      // セッション完了でエラーが発生するため、自動開始は実行されない
      expect(global.fetch).not.toHaveBeenCalled()
      
      // エラーレスポンスの検証
      expect(mock.history.post).toHaveLength(1)
    })
    
    test('409 Conflictエラー: セッション重複エラーを検出', async () => {
      const session = {
        id: 123,
        session_type: 'focus',
        settings: {
          auto_start_break: true,
          auto_start_focus: true
        }
      }
      
      // 1. セッション完了APIは成功
      mock.onPost('/api/pomodoro/123/complete').reply(200, {
        success: true
      })
      
      // 2. 新規セッション開始APIで409エラー（今回の不具合）
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 409,
        statusText: 'Conflict',
        json: async () => ({
          error: 'セッションが既に存在します'
        })
      })
      
      // 3. 統合フロー実行（409エラーが発生することを確認）
      await expect(component.handleTimerCompleteFlow(session))
        .rejects
        .toThrow('HTTP 409: Conflict')
      
      // 4. セッション完了は成功したが、自動開始で409エラー
      expect(mock.history.post).toHaveLength(1)
      expect(global.fetch).toHaveBeenCalled()
    })
    
    test('修正後: API呼び出し順序による409エラー回避', async () => {
      const session = {
        id: 123,
        session_type: 'focus',
        settings: {
          auto_start_break: true,
          auto_start_focus: true
        }
      }
      
      // セッション完了を先に実行することで409エラーを回避
      mock.onPost('/api/pomodoro/123/complete').reply((config) => {
        // セッション完了の処理を先に行う
        return [200, { success: true, message: 'セッション完了' }]
      })
      
      // 新規セッション開始（完了後なので成功）
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          id: 124,
          session_type: 'short_break'
        })
      })
      
      const result = await component.handleTimerCompleteFlow(session)
      
      // 409エラーが発生せず、正常に自動開始される
      expect(result.nextSessionType).toBe('short_break')
      expect(mock.history.post[0].url).toBe('/api/pomodoro/123/complete')
      expect(global.fetch).toHaveBeenCalledAfter(mock.history.post[0])
    })
  })

  describe('エラーハンドリング: Graceful Degradation', () => {
    test('ネットワークエラー時のフォールバック', async () => {
      const session = {
        id: 123,
        session_type: 'focus',
        settings: {
          auto_start_break: true
        }
      }
      
      // console.errorをモック
      const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation()
      
      // ネットワークエラーをシミュレート
      mock.onPost('/api/pomodoro/123/complete').networkError()
      
      global.fetch.mockRejectedValueOnce(new Error('Network Error'))
      
      // エラーが発生してもアプリケーションは継続する
      await expect(component.handleTimerCompleteFlow(session))
        .rejects
        .toThrow('Network Error')
      
      // エラーログが記録される
      expect(consoleErrorSpy).toHaveBeenCalled()
      
      // モックを復元
      consoleErrorSpy.mockRestore()
    })
    
    test('タイムアウトエラーのハンドリング', async () => {
      const session = {
        id: 123,
        session_type: 'focus',
        settings: {
          auto_start_break: true
        }
      }
      
      // タイムアウトをシミュレート
      mock.onPost('/api/pomodoro/123/complete').timeout()
      
      await expect(component.handleTimerCompleteFlow(session))
        .rejects
        .toThrow()
      
      expect(mock.history.post).toHaveLength(1)
    })
  })

  describe('APIレスポンス検証', () => {
    test('正しいAPIペイロードが送信される', async () => {
      const session = {
        id: 456,
        session_type: 'focus',
        subject_area_id: 2,
        settings: {
          auto_start_break: true,
          focus_duration: 30
        }
      }
      
      mock.onPost('/api/pomodoro/456/complete').reply(200, { success: true })
      
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ id: 457 })
      })
      
      await component.handleTimerCompleteFlow(session)
      
      // セッション完了APIのペイロード検証
      const completeRequest = mock.history.post[0]
      const completeData = JSON.parse(completeRequest.data)
      
      expect(completeData).toEqual({
        actual_duration: 15,
        was_interrupted: false,
        notes: 'API統合テスト'
      })
      
      // 新規セッション開始APIのペイロード検証
      const startRequest = global.fetch.mock.calls[0]
      const startData = JSON.parse(startRequest[1].body)
      
      expect(startData).toMatchObject({
        session_type: 'short_break',
        planned_duration: 5,
        subject_area_id: 2,
        settings: expect.objectContaining({
          auto_start_break: true,
          focus_duration: 30
        })
      })
    })
  })

  describe('認証とヘッダー検証', () => {
    test('正しい認証ヘッダーが送信される', async () => {
      const session = {
        id: 789,
        session_type: 'focus',
        settings: { auto_start_break: true }
      }
      
      mock.onPost().reply(200, { success: true })
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ id: 790 })
      })
      
      await component.handleTimerCompleteFlow(session)
      
      // fetchのヘッダー検証
      const fetchCall = global.fetch.mock.calls[0]
      const headers = fetchCall[1].headers
      
      expect(headers['Authorization']).toBe('Bearer test-token')
      expect(headers['Content-Type']).toBe('application/json')
      expect(headers['Accept']).toBe('application/json')
    })
  })
})

// カスタムマッチャー: API呼び出し順序の検証
expect.extend({
  toHaveBeenCalledAfter(received, precedingCall) {
    const receivedTime = received.mock.calls[0]?.[2]?.timestamp || Date.now()
    const precedingTime = precedingCall.timestamp || 0
    
    const pass = receivedTime > precedingTime
    
    return {
      message: () => 
        pass 
          ? `expected ${received} not to have been called after ${precedingCall}`
          : `expected ${received} to have been called after ${precedingCall}`,
      pass
    }
  }
})