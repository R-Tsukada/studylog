import { describe, test, expect, beforeEach, jest } from '@jest/globals'

// グローバルfetchのモック
global.fetch = jest.fn()

describe('SessionManager フロントエンド統合テスト', () => {
  let SessionManager
  let mockApiClient

  beforeEach(() => {
    fetch.mockClear()
    jest.resetModules()
    
    // モックのAPIクライアント（ApiClient.js の構造に合わせる）
    mockApiClient = {
      get: jest.fn(),
      post: jest.fn()
    }

    try {
      SessionManager = require('../../../resources/js/services/SessionManager.js').default
    } catch (error) {
      SessionManager = undefined
    }
  })

  describe('基本機能テスト', () => {
    test('SessionManagerクラスが存在し、正常にインスタンス化できる', () => {
      expect(SessionManager).toBeDefined()
      
      const sessionManager = new SessionManager()
      expect(sessionManager).toBeInstanceOf(SessionManager)
    })

    test('初期化時にAPIクライアントが正しく設定される', () => {
      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)
      
      expect(sessionManager.apiClient).toBe(mockApiClient)
    })
  })

  describe('安全なセッション開始機能', () => {
    test('正常系: 新規セッション開始が成功する', async () => {
      // 同期状態確認のモック（アクティブセッションなし）
      // ApiClient.get() は response.data を直接返すため
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        has_active_session: false,
        needs_cleanup: false,
        recommendation: 'ready_to_start',
      })

      // セッション開始のモック（成功）
      // ApiClient.post() は response.data を直接返すため
      mockApiClient.post.mockResolvedValueOnce({
        success: true,
        message: '学習セッションを開始しました',
        session: {
          id: 1,
          subject_area_id: 1,
          subject_area_name: 'Test Subject',
          started_at: '2025-08-20 12:00:00',
          study_comment: 'Test comment'
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.startSessionSafe({
        subject_area_id: 1,
        study_comment: 'Test comment'
      })

      expect(result.success).toBe(true)
      expect(result.session.id).toBe(1)
      expect(result.hadOldSessions).toBe(false)
      expect(result.cleanedSessionsCount).toBe(0)

      // API呼び出しの確認
      expect(mockApiClient.get).toHaveBeenCalledWith('/study-sessions/sync-status')
      expect(mockApiClient.post).toHaveBeenCalledWith('/study-sessions/start-safe', {
        subject_area_id: 1,
        study_comment: 'Test comment'
      })
    })

    test('古いセッション自動終了機能が正常動作する', async () => {
      // 同期状態確認のモック（クリーンアップが必要な古いセッション有り）
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        has_active_session: true,
        session_id: 999,
        needs_cleanup: true,
        recommendation: 'force_end_and_start_new',
      })

      // セッション開始のモック（古いセッションクリーンアップ込み）
      mockApiClient.post.mockResolvedValueOnce({
        success: true,
        message: '学習セッションを開始しました（前のセッションを自動終了）',
        session: {
          id: 2,
          subject_area_id: 1,
          subject_area_name: 'Test Subject',
          started_at: '2025-08-20 12:00:00',
          study_comment: 'Test comment'
        },
        cleaned_sessions_count: 1,
        auto_closed_session: {
          id: 999,
          reason: 'システム自動終了（新セッション開始のため）'
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.startSessionSafe({
        subject_area_id: 1,
        study_comment: 'Test comment'
      })

      expect(result.success).toBe(true)
      expect(result.session.id).toBe(2)
      expect(result.hadOldSessions).toBe(true)
      expect(result.cleanedSessionsCount).toBe(1)
    })

    test('バリデーションエラーが適切に処理される', async () => {
      // 同期状態確認のモック
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        has_active_session: false,
        needs_cleanup: false,
      })

      // バリデーションエラーのモック
      mockApiClient.post.mockRejectedValueOnce({
        response: {
          status: 422,
          data: {
            success: false,
            message: 'バリデーションエラー',
            errors: {
              subject_area_id: ['The subject area id field is required.'],
              study_comment: ['The study comment field is required.']
            }
          }
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.startSessionSafe({
        // 必須フィールドを故意に省略
      })

      expect(result.success).toBe(false)
      expect(result.error).toBe('バリデーションエラー')
      expect(result.validationErrors).toBeDefined()
    })

    test('認証エラーが適切に処理される', async () => {
      // 同期状態確認のモック
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        has_active_session: false,
        needs_cleanup: false,
      })

      // 認証エラーのモック
      mockApiClient.post.mockRejectedValueOnce({
        response: {
          status: 401,
          data: {
            success: false,
            message: 'Unauthenticated'
          }
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.startSessionSafe({
        subject_area_id: 1,
        study_comment: 'Test comment'
      })

      expect(result.success).toBe(false)
      expect(result.needsAuth).toBe(true)
      expect(result.error).toBe('認証が必要です。ログインしてください。')
    })
  })

  describe('セッション終了機能', () => {
    test('セッション終了が正常動作する', async () => {
      mockApiClient.post.mockResolvedValueOnce({
        success: true,
        message: '学習セッションを終了しました',
        session: {
          id: 1,
          duration_minutes: 45,
          ended_at: '2025-08-20 12:45:00'
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.endSession(1, 'Final comment')

      expect(result.success).toBe(true)
      expect(result.session.duration_minutes).toBe(45)
      expect(mockApiClient.post).toHaveBeenCalledWith('/study-sessions/end', {
        session_id: 1,
        study_comment: 'Final comment'
      })
    })
  })

  describe('同期状態確認機能', () => {
    test('同期状態確認が正常動作する', async () => {
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        has_active_session: true,
        session_id: 1,
        session_started_at: '2025-08-20 11:00:00',
        elapsed_hours: 1.5,
        needs_cleanup: false,
        recommendation: 'continue_or_end'
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.getSyncStatus()

      expect(result.success).toBe(true)
      expect(result.has_active_session).toBe(true)
      expect(result.session_id).toBe(1)
      expect(result.elapsed_hours).toBe(1.5)
      expect(result.needs_cleanup).toBe(false)
    })
  })

  describe('強制クリーンアップ機能', () => {
    test('強制クリーンアップが正常動作する', async () => {
      mockApiClient.post.mockResolvedValueOnce({
        success: true,
        cleaned_sessions_count: 3,
        message: '古いセッションを3件クリーンアップしました',
        reason: 'User requested cleanup'
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.forceCleanup('User requested cleanup')

      expect(result.success).toBe(true)
      expect(result.cleanedSessionsCount).toBe(3)
      expect(mockApiClient.post).toHaveBeenCalledWith('/study-sessions/force-cleanup', {
        reason: 'User requested cleanup'
      })
    })
  })

  describe('現在セッション取得機能', () => {
    test('アクティブセッション取得が正常動作する', async () => {
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        session: {
          id: 1,
          subject_area_name: 'Test Subject',
          started_at: '2025-08-20 11:00:00',
          elapsed_minutes: 90
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.getCurrentSession()

      expect(result.success).toBe(true)
      expect(result.hasActiveSession).toBe(true)
      expect(result.session.id).toBe(1)
      expect(result.session.elapsed_minutes).toBe(90)
    })

    test('アクティブセッションなしの場合の処理', async () => {
      mockApiClient.get.mockResolvedValueOnce({
        success: true,
        message: '現在進行中のセッションはありません',
        session: null
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.getCurrentSession()

      expect(result.success).toBe(true)
      expect(result.hasActiveSession).toBe(false)
      expect(result.session).toBeNull()
    })
  })

  describe('エラーハンドリング', () => {
    test('ネットワークエラーが適切に処理される', async () => {
      // 同期状態確認は成功
      mockApiClient.get.mockResolvedValueOnce({
        success: true, has_active_session: false, needs_cleanup: false
      })

      // セッション開始でネットワークエラー
      mockApiClient.post.mockRejectedValueOnce(new Error('Network Error'))

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.startSessionSafe({
        subject_area_id: 1,
        study_comment: 'Test comment'
      })

      expect(result.success).toBe(false)
      expect(result.error).toBe('ネットワークエラーが発生しました')
    })

    test('APIエラーレスポンスが適切に処理される', async () => {
      mockApiClient.get.mockRejectedValueOnce({
        response: {
          status: 500,
          data: {
            success: false,
            message: 'Internal Server Error'
          }
        }
      })

      const sessionManager = new SessionManager()
      sessionManager.init(mockApiClient)

      const result = await sessionManager.getSyncStatus()

      expect(result.success).toBe(false)
      expect(result.has_active_session).toBe(false)
      expect(result.needs_cleanup).toBe(false)
    })
  })
})