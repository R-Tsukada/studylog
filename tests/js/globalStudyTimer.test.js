/**
 * グローバル時間計測タイマーのフロントエンドテスト
 * 
 * @vitest-environment jsdom
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive } from 'vue'

// localStorage のモック
const localStorageMock = {
  data: {},
  getItem: vi.fn((key) => localStorageMock.data[key] || null),
  setItem: vi.fn((key, value) => {
    localStorageMock.data[key] = value
  }),
  removeItem: vi.fn((key) => {
    delete localStorageMock.data[key]
  }),
  clear: vi.fn(() => {
    localStorageMock.data = {}
  })
}

// グローバルタイマーの実装をモック
const createMockGlobalStudyTimer = () => reactive({
  isActive: false,
  currentSession: null,
  elapsedMinutes: 0,
  startTime: 0,
  timer: null
})

// App.vue のタイマー管理メソッドをモック
const createMockTimerMethods = (globalStudyTimer) => ({
  startGlobalStudyTimer: (session) => {
    globalStudyTimer.currentSession = session
    globalStudyTimer.isActive = true
    globalStudyTimer.startTime = Date.now()
    globalStudyTimer.elapsedMinutes = 0
    
    // localStorage に保存
    const state = {
      isActive: globalStudyTimer.isActive,
      currentSession: globalStudyTimer.currentSession,
      elapsedMinutes: globalStudyTimer.elapsedMinutes,
      startTime: globalStudyTimer.startTime
    }
    localStorageMock.setItem('studyTimer', JSON.stringify(state))
  },

  stopGlobalStudyTimer: () => {
    if (globalStudyTimer.timer) {
      clearInterval(globalStudyTimer.timer)
      globalStudyTimer.timer = null
    }
    
    globalStudyTimer.isActive = false
    globalStudyTimer.currentSession = null
    globalStudyTimer.elapsedMinutes = 0
    globalStudyTimer.startTime = 0
    
    localStorageMock.removeItem('studyTimer')
  },

  updateStudyElapsedTime: () => {
    if (globalStudyTimer.isActive && globalStudyTimer.startTime) {
      const now = Date.now()
      const elapsedMinutes = Math.floor((now - globalStudyTimer.startTime) / (1000 * 60))
      globalStudyTimer.elapsedMinutes = Math.max(0, elapsedMinutes)
    }
  },

  restoreStudyTimerStateFromStorage: () => {
    const saved = localStorageMock.getItem('studyTimer')
    if (saved) {
      try {
        const state = JSON.parse(saved)
        
        if (state.isActive && state.currentSession && state.startTime) {
          // 現在の経過時間を計算
          const elapsed = Math.floor((Date.now() - state.startTime) / (1000 * 60))
          
          // タイマーを復元
          globalStudyTimer.currentSession = state.currentSession
          globalStudyTimer.isActive = true
          globalStudyTimer.startTime = state.startTime
          globalStudyTimer.elapsedMinutes = elapsed
          
          return true
        }
      } catch (error) {
        localStorageMock.removeItem('studyTimer')
        return false
      }
    }
    return false
  }
})

describe('グローバル時間計測タイマー', () => {
  let globalStudyTimer
  let timerMethods

  beforeEach(() => {
    // localStorage モックを設定
    Object.defineProperty(window, 'localStorage', {
      value: localStorageMock
    })
    
    // モックを初期化
    localStorageMock.clear()
    vi.clearAllMocks()
    
    // タイマーとメソッドを初期化
    globalStudyTimer = createMockGlobalStudyTimer()
    timerMethods = createMockTimerMethods(globalStudyTimer)
    
    // Date.now() をモック
    vi.spyOn(Date, 'now').mockReturnValue(1000000) // 固定時刻
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('基本的な状態管理', () => {
    it('初期状態では非アクティブである', () => {
      expect(globalStudyTimer.isActive).toBe(false)
      expect(globalStudyTimer.currentSession).toBeNull()
      expect(globalStudyTimer.elapsedMinutes).toBe(0)
      expect(globalStudyTimer.startTime).toBe(0)
    })

    it('セッション開始時に状態が正しく設定される', () => {
      const mockSession = {
        id: 1,
        subject_area_name: 'テスト分野',
        exam_type_name: 'テスト資格',
        study_comment: 'テストコメント'
      }

      timerMethods.startGlobalStudyTimer(mockSession)

      expect(globalStudyTimer.isActive).toBe(true)
      expect(globalStudyTimer.currentSession).toEqual(mockSession)
      expect(globalStudyTimer.elapsedMinutes).toBe(0)
      expect(globalStudyTimer.startTime).toBe(1000000)
    })

    it('セッション停止時に状態が正しくリセットされる', () => {
      const mockSession = {
        id: 1,
        subject_area_name: 'テスト分野'
      }

      // セッション開始
      timerMethods.startGlobalStudyTimer(mockSession)
      expect(globalStudyTimer.isActive).toBe(true)

      // セッション停止
      timerMethods.stopGlobalStudyTimer()
      
      expect(globalStudyTimer.isActive).toBe(false)
      expect(globalStudyTimer.currentSession).toBeNull()
      expect(globalStudyTimer.elapsedMinutes).toBe(0)
      expect(globalStudyTimer.startTime).toBe(0)
    })
  })

  describe('localStorage との連携', () => {
    it('セッション開始時にlocalStorageに状態が保存される', () => {
      const mockSession = {
        id: 1,
        subject_area_name: 'テスト分野'
      }

      timerMethods.startGlobalStudyTimer(mockSession)

      expect(localStorageMock.setItem).toHaveBeenCalledWith(
        'studyTimer',
        expect.stringContaining('"isActive":true')
      )

      const savedData = JSON.parse(localStorageMock.getItem('studyTimer'))
      expect(savedData.isActive).toBe(true)
      expect(savedData.currentSession).toEqual(mockSession)
      expect(savedData.startTime).toBe(1000000)
    })

    it('セッション停止時にlocalStorageがクリアされる', () => {
      const mockSession = { id: 1, subject_area_name: 'テスト分野' }

      timerMethods.startGlobalStudyTimer(mockSession)
      timerMethods.stopGlobalStudyTimer()

      expect(localStorageMock.removeItem).toHaveBeenCalledWith('studyTimer')
    })

    it('localStorageから状態が正しく復元される', () => {
      const mockSession = {
        id: 1,
        subject_area_name: 'テスト分野',
        study_comment: '復元テスト'
      }

      // 5分前に開始されたセッションをシミュレート
      const fiveMinutesAgo = 1000000 - (5 * 60 * 1000)
      const savedState = {
        isActive: true,
        currentSession: mockSession,
        elapsedMinutes: 0,
        startTime: fiveMinutesAgo
      }

      localStorageMock.setItem('studyTimer', JSON.stringify(savedState))

      const restored = timerMethods.restoreStudyTimerStateFromStorage()

      expect(restored).toBe(true)
      expect(globalStudyTimer.isActive).toBe(true)
      expect(globalStudyTimer.currentSession).toEqual(mockSession)
      expect(globalStudyTimer.elapsedMinutes).toBe(5) // 5分経過
    })

    it('無効なlocalStorageデータは無視される', () => {
      localStorageMock.setItem('studyTimer', 'invalid json')

      const restored = timerMethods.restoreStudyTimerStateFromStorage()

      expect(restored).toBe(false)
      expect(globalStudyTimer.isActive).toBe(false)
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('studyTimer')
    })
  })

  describe('時間計算', () => {
    it('経過時間が正しく計算される', () => {
      const mockSession = { id: 1, subject_area_name: 'テスト分野' }
      
      // タイマー開始
      timerMethods.startGlobalStudyTimer(mockSession)
      
      // 10分後をシミュレート
      vi.spyOn(Date, 'now').mockReturnValue(1000000 + (10 * 60 * 1000))
      
      // 経過時間を更新
      timerMethods.updateStudyElapsedTime()
      
      expect(globalStudyTimer.elapsedMinutes).toBe(10)
    })

    it('負の時間は0分として扱われる', () => {
      const mockSession = { id: 1, subject_area_name: 'テスト分野' }
      
      // 未来の時刻でタイマー開始（異常なケース）
      globalStudyTimer.startTime = 2000000 // 現在時刻より未来
      globalStudyTimer.isActive = true
      
      timerMethods.updateStudyElapsedTime()
      
      expect(globalStudyTimer.elapsedMinutes).toBe(0)
    })

    it('長時間セッションでも正しく計算される', () => {
      const mockSession = { id: 1, subject_area_name: 'テスト分野' }
      
      timerMethods.startGlobalStudyTimer(mockSession)
      
      // 2時間30分後をシミュレート
      const hoursLater = 1000000 + (2.5 * 60 * 60 * 1000)
      vi.spyOn(Date, 'now').mockReturnValue(hoursLater)
      
      timerMethods.updateStudyElapsedTime()
      
      expect(globalStudyTimer.elapsedMinutes).toBe(150) // 2時間30分 = 150分
    })
  })

  describe('エッジケース', () => {
    it('非アクティブ状態での時間更新は何もしない', () => {
      globalStudyTimer.isActive = false
      globalStudyTimer.startTime = 1000000
      
      timerMethods.updateStudyElapsedTime()
      
      expect(globalStudyTimer.elapsedMinutes).toBe(0)
    })

    it('startTimeが0の場合は時間更新しない', () => {
      globalStudyTimer.isActive = true
      globalStudyTimer.startTime = 0
      
      timerMethods.updateStudyElapsedTime()
      
      expect(globalStudyTimer.elapsedMinutes).toBe(0)
    })

    it('空のlocalStorageデータは無視される', () => {
      localStorageMock.setItem('studyTimer', null)

      const restored = timerMethods.restoreStudyTimerStateFromStorage()

      expect(restored).toBe(false)
      expect(globalStudyTimer.isActive).toBe(false)
    })

    it('不完全なセッションデータは復元しない', () => {
      const incompleteState = {
        isActive: true,
        currentSession: null, // セッションがnull
        startTime: 1000000
      }

      localStorageMock.setItem('studyTimer', JSON.stringify(incompleteState))

      const restored = timerMethods.restoreStudyTimerStateFromStorage()

      expect(restored).toBe(false)
      expect(globalStudyTimer.isActive).toBe(false)
    })
  })

  describe('複数セッションの処理', () => {
    it('既存セッション停止後に新しいセッションを開始できる', () => {
      const session1 = { id: 1, subject_area_name: '分野1' }
      const session2 = { id: 2, subject_area_name: '分野2' }

      // 最初のセッション
      timerMethods.startGlobalStudyTimer(session1)
      expect(globalStudyTimer.currentSession.id).toBe(1)

      // セッション停止
      timerMethods.stopGlobalStudyTimer()
      expect(globalStudyTimer.isActive).toBe(false)

      // 新しいセッション開始
      timerMethods.startGlobalStudyTimer(session2)
      expect(globalStudyTimer.currentSession.id).toBe(2)
      expect(globalStudyTimer.isActive).toBe(true)
    })
  })
})

describe('時間フォーマット関数', () => {
  const formatElapsedTime = (minutes) => {
    const totalMinutes = Math.max(0, Math.floor(Number(minutes) || 0))
    const hours = Math.floor(totalMinutes / 60)
    const mins = totalMinutes % 60
    
    if (hours > 0) {
      return `${hours}時間${mins}分`
    } else {
      return `${mins}分`
    }
  }

  it('分のみの場合は分で表示', () => {
    expect(formatElapsedTime(30)).toBe('30分')
    expect(formatElapsedTime(59)).toBe('59分')
  })

  it('時間がある場合は時間と分で表示', () => {
    expect(formatElapsedTime(60)).toBe('1時間0分')
    expect(formatElapsedTime(90)).toBe('1時間30分')
    expect(formatElapsedTime(150)).toBe('2時間30分')
  })

  it('0分の場合は0分で表示', () => {
    expect(formatElapsedTime(0)).toBe('0分')
    expect(formatElapsedTime(null)).toBe('0分')
    expect(formatElapsedTime(undefined)).toBe('0分')
  })

  it('負の値は0分として扱う', () => {
    expect(formatElapsedTime(-10)).toBe('0分')
  })

  it('小数点は切り捨て', () => {
    expect(formatElapsedTime(30.9)).toBe('30分')
    expect(formatElapsedTime(60.5)).toBe('1時間0分')
  })
})