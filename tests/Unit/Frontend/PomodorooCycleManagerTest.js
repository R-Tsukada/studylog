import { describe, test, expect, beforeEach, jest } from '@jest/globals'
import { PomodorooCycleManager } from '../../../resources/js/utils/PomodorooCycleManager.js'

describe('PomodorooCycleManager ポモドーロサイクル管理テスト', () => {
  let cycleManager

  beforeEach(() => {
    cycleManager = new PomodorooCycleManager()
  })

  describe('初期状態', () => {
    test('初期値が正しく設定されている', () => {
      const stats = cycleManager.getCycleStats()
      
      expect(stats.completedFocusSessions).toBe(0)
      expect(stats.currentCycleStartTime).toBeNull()
      expect(stats.lastSessionCompletedAt).toBeNull()
      expect(stats.cycleHistoryLength).toBe(0)
      expect(stats.nextSessionType).toBe('focus')
      expect(stats.isLongBreakTime).toBe(false)
    })
  })

  describe('集中セッションのカウント機能', () => {
    test('初回集中セッション完了で正しくカウントされる', () => {
      const beforeTime = Date.now()
      cycleManager.incrementFocusSession()
      const afterTime = Date.now()
      
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(1)
      expect(stats.currentCycleStartTime).toBeGreaterThanOrEqual(beforeTime)
      expect(stats.currentCycleStartTime).toBeLessThanOrEqual(afterTime)
      expect(stats.lastSessionCompletedAt).toBeGreaterThanOrEqual(beforeTime)
      expect(stats.nextSessionType).toBe('short_break')
    })

    test('複数の集中セッション完了で正しくカウントされる', () => {
      // 3回集中セッションを完了
      for (let i = 0; i < 3; i++) {
        cycleManager.incrementFocusSession()
      }
      
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(3)
      expect(stats.nextSessionType).toBe('short_break')
      expect(stats.isLongBreakTime).toBe(false)
      expect(stats.cycleHistoryLength).toBe(3)
    })

    test('4回目の集中セッション完了で長い休憩が提案される', () => {
      // 4回集中セッションを完了
      for (let i = 0; i < 4; i++) {
        cycleManager.incrementFocusSession()
      }
      
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(4)
      expect(stats.nextSessionType).toBe('long_break')
      expect(stats.isLongBreakTime).toBe(true)
      expect(stats.cycleHistoryLength).toBe(4)
    })

    test('5回目以降も長い休憩が提案される', () => {
      // 5回集中セッションを完了
      for (let i = 0; i < 5; i++) {
        cycleManager.incrementFocusSession()
      }
      
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(5)
      expect(stats.nextSessionType).toBe('long_break')
      expect(stats.isLongBreakTime).toBe(true)
    })
  })

  describe('サイクル完了機能', () => {
    test('サイクル完了時に正しい情報が返される', () => {
      // 4回集中セッションを完了
      for (let i = 0; i < 4; i++) {
        cycleManager.incrementFocusSession()
      }
      
      const beforeComplete = Date.now()
      const completedCycle = cycleManager.completeCycle()
      const afterComplete = Date.now()
      
      // 完了したサイクル情報の確認
      expect(completedCycle.completedFocusSessions).toBe(4)
      expect(completedCycle.cycleStartTime).toBeDefined()
      expect(completedCycle.cycleEndTime).toBeGreaterThanOrEqual(beforeComplete)
      expect(completedCycle.cycleEndTime).toBeLessThanOrEqual(afterComplete)
      expect(completedCycle.history).toHaveLength(4)
      
      // リセット後の状態確認
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(0)
      expect(stats.currentCycleStartTime).toBeNull()
      expect(stats.cycleHistoryLength).toBe(0)
      expect(stats.nextSessionType).toBe('focus')
    })
  })

  describe('休憩セッション処理', () => {
    test('休憩セッション完了が正しく記録される', () => {
      // 1回集中セッション→休憩セッション
      cycleManager.incrementFocusSession()
      
      const beforeBreak = Date.now()
      cycleManager.completeBreakSession()
      const afterBreak = Date.now()
      
      const stats = cycleManager.getCycleStats()
      expect(stats.lastSessionCompletedAt).toBeGreaterThanOrEqual(beforeBreak)
      expect(stats.lastSessionCompletedAt).toBeLessThanOrEqual(afterBreak)
      expect(stats.cycleHistoryLength).toBe(2) // focus + break
      expect(stats.completedFocusSessions).toBe(1) // 集中セッションカウントは変わらず
    })
  })

  describe('次のセッションタイプ決定ロジック', () => {
    test('セッション完了パターンに応じた正しいタイプが返される', () => {
      // 初期状態
      expect(cycleManager.getNextSessionType()).toBe('focus')
      
      // 1回集中セッション完了後
      cycleManager.incrementFocusSession()
      expect(cycleManager.getNextSessionType()).toBe('short_break')
      
      // 2回集中セッション完了後
      cycleManager.incrementFocusSession()
      expect(cycleManager.getNextSessionType()).toBe('short_break')
      
      // 3回集中セッション完了後
      cycleManager.incrementFocusSession()
      expect(cycleManager.getNextSessionType()).toBe('short_break')
      
      // 4回集中セッション完了後
      cycleManager.incrementFocusSession()
      expect(cycleManager.getNextSessionType()).toBe('long_break')
    })
  })

  describe('リセット機能', () => {
    test('リセット後に初期状態に戻る', () => {
      // データを設定
      for (let i = 0; i < 3; i++) {
        cycleManager.incrementFocusSession()
        cycleManager.completeBreakSession()
      }
      
      // リセット実行
      cycleManager.resetCycleState()
      
      // 初期状態に戻ることを確認
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(0)
      expect(stats.currentCycleStartTime).toBeNull()
      expect(stats.lastSessionCompletedAt).toBeNull()
      expect(stats.cycleHistoryLength).toBe(0)
      expect(stats.nextSessionType).toBe('focus')
      expect(stats.isLongBreakTime).toBe(false)
    })
  })

  describe('履歴機能', () => {
    test('セッション履歴が正しく記録される', () => {
      cycleManager.incrementFocusSession()
      cycleManager.completeBreakSession()
      cycleManager.incrementFocusSession()
      
      const history = cycleManager.pomodoroCounterState.cycleHistory
      
      expect(history).toHaveLength(3)
      expect(history[0].sessionType).toBe('focus')
      expect(history[0].sessionCount).toBe(1)
      expect(history[1].sessionType).toBe('break')
      expect(history[1].sessionCount).toBe(1)
      expect(history[2].sessionType).toBe('focus')
      expect(history[2].sessionCount).toBe(2)
      
      // タイムスタンプの確認
      expect(history[0].completedAt).toBeDefined()
      expect(history[1].completedAt).toBeGreaterThanOrEqual(history[0].completedAt)
      expect(history[2].completedAt).toBeGreaterThanOrEqual(history[1].completedAt)
    })
  })

  describe('ストレージ機能', () => {
    test('状態のシリアライズが正しく動作する', () => {
      cycleManager.incrementFocusSession()
      cycleManager.completeBreakSession()
      
      const serialized = cycleManager.serialize()
      
      expect(serialized.completedFocusSessions).toBe(1)
      expect(serialized.currentCycleStartTime).toBeDefined()
      expect(serialized.lastSessionCompletedAt).toBeDefined()
      expect(serialized.cycleHistory).toHaveLength(2)
      expect(serialized.version).toBeDefined()
    })

    test('状態の復元が正しく動作する', () => {
      const savedState = {
        completedFocusSessions: 2,
        currentCycleStartTime: Date.now() - 100000,
        lastSessionCompletedAt: Date.now() - 50000,
        cycleHistory: [
          { sessionType: 'focus', completedAt: Date.now() - 80000, sessionCount: 1 },
          { sessionType: 'break', completedAt: Date.now() - 60000, sessionCount: 1 }
        ]
      }
      
      cycleManager.restoreFromStorage(savedState)
      
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(2)
      expect(stats.currentCycleStartTime).toBe(savedState.currentCycleStartTime)
      expect(stats.lastSessionCompletedAt).toBe(savedState.lastSessionCompletedAt)
      expect(stats.cycleHistoryLength).toBe(2)
      // 修正後: 最後がbreakなので次は focus が提案される
      expect(stats.nextSessionType).toBe('focus')
    })

    test('不正な復元データを安全に処理する', () => {
      // null データ
      cycleManager.restoreFromStorage(null)
      let stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(0)
      
      // 不正なオブジェクト
      cycleManager.restoreFromStorage({ invalid: 'data' })
      stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(0)
      expect(stats.cycleHistoryLength).toBe(0)
      
      // 文字列データ
      cycleManager.restoreFromStorage('invalid')
      stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(0)
    })
  })

  // ✅ Issue #62 修正確認 - 修正後の正しい動作確認
  describe('✅ 休憩サイクル問題 (Issue #62) 修正確認', () => {
    test('修正後: 短い休憩完了後は集中セッションが提案される', () => {
      // 1. 集中セッション完了 → 短い休憩提案 (正常)
      cycleManager.incrementFocusSession()
      expect(cycleManager.getNextSessionType()).toBe('short_break')
      
      // 2. 短い休憩完了
      cycleManager.completeBreakSession()
      
      // 3. 修正後: 集中セッションが提案される（修正済み）
      const nextType = cycleManager.getNextSessionType()
      expect(nextType).toBe('focus') // 修正後の正しい動作
      
      // 履歴確認: focus → break の順で記録されているはず
      const history = cycleManager.pomodoroCounterState.cycleHistory
      expect(history).toHaveLength(2)
      expect(history[0].sessionType).toBe('focus')
      expect(history[1].sessionType).toBe('break')
    })

    test('修正後: 長い休憩完了後は新サイクルの集中セッションが提案される', () => {
      // 4回の集中セッション完了
      for (let i = 0; i < 4; i++) {
        cycleManager.incrementFocusSession()
      }
      expect(cycleManager.getNextSessionType()).toBe('long_break')
      
      // 長い休憩完了
      cycleManager.completeBreakSession()
      
      // 修正後: 新サイクルの集中セッションが提案される（修正済み）
      const nextType = cycleManager.getNextSessionType()
      expect(nextType).toBe('focus') // 修正後の正しい動作
      
      // 履歴確認
      const history = cycleManager.pomodoroCounterState.cycleHistory
      expect(history).toHaveLength(5) // focus×4 + break×1
      expect(history[4].sessionType).toBe('break')
    })

    test('修正確認: getNextSessionTypeは履歴も参照するようになった', () => {
      // 集中 → 休憩 → (次のセッションタイプ決定)
      cycleManager.incrementFocusSession()
      expect(cycleManager.getCycleStats().completedFocusSessions).toBe(1)
      
      cycleManager.completeBreakSession()
      // 休憩完了後も集中セッション数は変わらない
      expect(cycleManager.getCycleStats().completedFocusSessions).toBe(1)
      
      // 修正後: 履歴を参照して正しく focus が提案される
      expect(cycleManager.getNextSessionType()).toBe('focus')
    })
  })

  // 🟢 TDD Green Phase - 修正後の期待動作
  describe('🟢 休憩サイクル修正 - 期待される動作 (実装後に成功)', () => {
    test('【修正目標】短い休憩完了後は集中セッションが提案される', () => {
      // 集中 → 短い休憩 → 集中 のサイクル
      cycleManager.incrementFocusSession()
      expect(cycleManager.getNextSessionType()).toBe('short_break')
      
      cycleManager.completeBreakSession()
      
      // 修正後: 集中セッションが提案されるべき
      const nextType = cycleManager.getNextSessionType()
      expect(nextType).toBe('focus') // 修正目標
    })

    test('【修正目標】長い休憩完了後は新サイクルの集中セッションが提案される', () => {
      // 4回集中 → 長い休憩 → 新サイクル集中
      for (let i = 0; i < 4; i++) {
        cycleManager.incrementFocusSession()
      }
      expect(cycleManager.getNextSessionType()).toBe('long_break')
      
      cycleManager.completeBreakSession()
      
      // 修正後: 新サイクルの集中セッションが提案されるべき
      const nextType = cycleManager.getNextSessionType()
      expect(nextType).toBe('focus') // 修正目標
    })

    test('【修正目標】完全なポモドーロサイクルテスト', () => {
      const expectedSequence = [
        'focus',       // 初回
        'short_break', // 1回目集中完了後
        'focus',       // 1回目休憩完了後
        'short_break', // 2回目集中完了後
        'focus',       // 2回目休憩完了後
        'short_break', // 3回目集中完了後
        'focus',       // 3回目休憩完了後
        'long_break',  // 4回目集中完了後
        'focus'        // 長い休憩完了後（新サイクル）
      ]
      
      let actualSequence = []
      
      // 初回
      actualSequence.push(cycleManager.getNextSessionType())
      
      // 4回のサイクル
      for (let cycle = 0; cycle < 4; cycle++) {
        // 集中セッション完了
        cycleManager.incrementFocusSession()
        actualSequence.push(cycleManager.getNextSessionType())
        
        // 休憩セッション完了
        cycleManager.completeBreakSession()
        actualSequence.push(cycleManager.getNextSessionType())
      }
      
      // 修正後は期待するシーケンスと一致するはず
      expect(actualSequence).toEqual(expectedSequence)
    })
  })

  // 🔵 TDD Refactor Phase - エッジケースと安全性テスト
  describe('🔵 Refactor Phase - エッジケースと安全性', () => {
    test('空の履歴でも安全に動作する', () => {
      // 初期状態（履歴なし）
      expect(cycleManager.pomodoroCounterState.cycleHistory.length).toBe(0)
      
      // 初回は集中セッションが提案される
      expect(cycleManager.getNextSessionType()).toBe('focus')
    })

    test('不正な履歴データでも安全に動作する', () => {
      // 意図的に不正なデータを設定
      cycleManager.pomodoroCounterState.cycleHistory = [
        { sessionType: 'invalid_type', completedAt: Date.now() }
      ]
      
      // フォールバックでfocusが返される
      expect(cycleManager.getNextSessionType()).toBe('focus')
    })

    test('履歴データが不完全でも安全に動作する', () => {
      // sessionTypeが欠けているデータ
      cycleManager.pomodoroCounterState.cycleHistory = [
        { completedAt: Date.now() }
      ]
      
      // フォールバックでfocusが返される
      expect(cycleManager.getNextSessionType()).toBe('focus')
    })

    test('サイクルリセット後の動作確認', () => {
      // 長いサイクルを完了
      for (let i = 0; i < 4; i++) {
        cycleManager.incrementFocusSession()
        cycleManager.completeBreakSession()
      }
      
      // サイクル完了
      const completedCycle = cycleManager.completeCycle()
      expect(completedCycle.completedFocusSessions).toBe(4)
      
      // リセット後は初回状態
      expect(cycleManager.getNextSessionType()).toBe('focus')
      expect(cycleManager.getCycleStats().completedFocusSessions).toBe(0)
    })

    test('大量のセッション履歴でも正しく動作する', () => {
      // 大量のセッションをシミュレート
      for (let i = 0; i < 100; i++) {
        cycleManager.incrementFocusSession()
        cycleManager.completeBreakSession()
      }
      
      // 最後は休憩セッションなので次は集中
      expect(cycleManager.getNextSessionType()).toBe('focus')
      
      // 履歴数確認
      expect(cycleManager.getCycleStats().cycleHistoryLength).toBe(200) // focus×100 + break×100
    })

    test('長い休憩後のサイクルリセット確認', () => {
      // 4回集中 + 3回短い休憩 + 長い休憩
      for (let i = 0; i < 4; i++) {
        cycleManager.incrementFocusSession()
        if (i < 3) {
          // 短い休憩
          expect(cycleManager.getNextSessionType()).toBe('short_break')
          cycleManager.completeBreakSession()
        }
      }
      
      // 4回目後は長い休憩
      expect(cycleManager.getNextSessionType()).toBe('long_break')
      cycleManager.completeBreakSession()
      
      // 長い休憩後は新サイクルの集中
      expect(cycleManager.getNextSessionType()).toBe('focus')
      
      // 状態確認
      const stats = cycleManager.getCycleStats()
      expect(stats.completedFocusSessions).toBe(4) // まだリセットされていない
      expect(stats.cycleHistoryLength).toBe(8) // focus×4 + break×4
    })
  })
})