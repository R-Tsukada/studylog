import { describe, test, expect } from '@jest/globals'
import { POMODORO_CONSTANTS } from '../../../resources/js/utils/constants.js'

describe('POMODORO_CONSTANTS 自動開始機能関連定数テスト', () => {
  describe('AUTO_START_DELAY_MS', () => {
    test('自動開始遅延時間が適切な値に設定されている', () => {
      expect(POMODORO_CONSTANTS.AUTO_START_DELAY_MS).toBe(3000)
      expect(typeof POMODORO_CONSTANTS.AUTO_START_DELAY_MS).toBe('number')
      expect(POMODORO_CONSTANTS.AUTO_START_DELAY_MS).toBeGreaterThan(0)
    })
  })

  describe('AUTO_START_COUNTDOWN_INTERVAL', () => {
    test('カウントダウン更新間隔が適切な値に設定されている', () => {
      expect(POMODORO_CONSTANTS.AUTO_START_COUNTDOWN_INTERVAL).toBe(100)
      expect(typeof POMODORO_CONSTANTS.AUTO_START_COUNTDOWN_INTERVAL).toBe('number')
      expect(POMODORO_CONSTANTS.AUTO_START_COUNTDOWN_INTERVAL).toBeGreaterThan(0)
    })
  })

  describe('POMODORO_CYCLE_LENGTH', () => {
    test('ポモドーロサイクル長が4に設定されている', () => {
      expect(POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH).toBe(4)
      expect(typeof POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH).toBe('number')
      expect(POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH).toBeGreaterThan(0)
    })
  })

  describe('NOTIFICATION_PERMISSION_REQUEST_DELAY_MS', () => {
    test('通知権限要求遅延時間が適切な値に設定されている', () => {
      expect(POMODORO_CONSTANTS.NOTIFICATION_PERMISSION_REQUEST_DELAY_MS).toBe(1000)
      expect(typeof POMODORO_CONSTANTS.NOTIFICATION_PERMISSION_REQUEST_DELAY_MS).toBe('number')
      expect(POMODORO_CONSTANTS.NOTIFICATION_PERMISSION_REQUEST_DELAY_MS).toBeGreaterThan(0)
    })
  })

  describe('セッション時間制限', () => {
    test('最大セッション時間が240分に設定されている', () => {
      expect(POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES).toBe(240)
      expect(typeof POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES).toBe('number')
      expect(POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES).toBeGreaterThan(0)
    })

    test('最小セッション時間が1分に設定されている', () => {
      expect(POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES).toBe(1)
      expect(typeof POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES).toBe('number')
      expect(POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES).toBeGreaterThan(0)
    })

    test('最大時間が最小時間より大きい', () => {
      expect(POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES)
        .toBeGreaterThan(POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES)
    })
  })

  describe('API通信設定', () => {
    test('APIタイムアウトが10秒に設定されている', () => {
      expect(POMODORO_CONSTANTS.API_TIMEOUT_MS).toBe(10000)
      expect(typeof POMODORO_CONSTANTS.API_TIMEOUT_MS).toBe('number')
      expect(POMODORO_CONSTANTS.API_TIMEOUT_MS).toBeGreaterThan(0)
    })

    test('最大リトライ回数が3回に設定されている', () => {
      expect(POMODORO_CONSTANTS.MAX_RETRY_ATTEMPTS).toBe(3)
      expect(typeof POMODORO_CONSTANTS.MAX_RETRY_ATTEMPTS).toBe('number')
      expect(POMODORO_CONSTANTS.MAX_RETRY_ATTEMPTS).toBeGreaterThan(0)
    })
  })

  describe('ALLOWED_SESSION_TYPES', () => {
    test('許可されたセッションタイプが正しく定義されている', () => {
      const expectedTypes = ['focus', 'short_break', 'long_break']
      expect(POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES).toEqual(expectedTypes)
      expect(Array.isArray(POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES)).toBe(true)
      expect(POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES.length).toBe(3)
    })

    test('セッションタイプに重複がない', () => {
      const types = POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES
      const uniqueTypes = [...new Set(types)]
      expect(types.length).toBe(uniqueTypes.length)
    })

    test('各セッションタイプが文字列である', () => {
      POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES.forEach(type => {
        expect(typeof type).toBe('string')
        expect(type.length).toBeGreaterThan(0)
      })
    })
  })

  describe('既存定数との整合性テスト', () => {
    test('既存のTIMER_STATESが存在する', () => {
      expect(POMODORO_CONSTANTS.TIMER_STATES).toBeDefined()
      expect(typeof POMODORO_CONSTANTS.TIMER_STATES).toBe('object')
    })

    test('既存のSTORAGE_DEBOUNCE_MSが存在する', () => {
      expect(POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS).toBeDefined()
      expect(typeof POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS).toBe('number')
    })

    test('新規追加の自動開始遅延が既存のストレージデバウンスと同等またはそれ以上', () => {
      expect(POMODORO_CONSTANTS.AUTO_START_DELAY_MS)
        .toBeGreaterThanOrEqual(POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS)
    })
  })

  describe('性能要件の検証', () => {
    test('カウントダウン更新間隔が適切（100ms以下）', () => {
      expect(POMODORO_CONSTANTS.AUTO_START_COUNTDOWN_INTERVAL).toBeLessThanOrEqual(100)
    })

    test('自動開始遅延が適切（1-10秒の範囲）', () => {
      expect(POMODORO_CONSTANTS.AUTO_START_DELAY_MS).toBeGreaterThanOrEqual(1000)
      expect(POMODORO_CONSTANTS.AUTO_START_DELAY_MS).toBeLessThanOrEqual(10000)
    })

    test('APIタイムアウトが適切（5-30秒の範囲）', () => {
      expect(POMODORO_CONSTANTS.API_TIMEOUT_MS).toBeGreaterThanOrEqual(5000)
      expect(POMODORO_CONSTANTS.API_TIMEOUT_MS).toBeLessThanOrEqual(30000)
    })
  })
})