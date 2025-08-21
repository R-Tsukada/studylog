/**
 * 学習セッション管理サービス
 * バックエンドとフロントエンド状態の確実な同期を保証
 */
export default class SessionManager {
  constructor() {
    this.apiClient = null
    this.authToken = null
    this.syncCheckInterval = null
    this.lastSyncTime = null
  }

  /**
   * 初期化
   */
  init(apiClient) {
    this.apiClient = apiClient
    this.authToken = localStorage.getItem('auth_token')
    
    // 定期的な同期チェックを開始（5分間隔）
    this.startPeriodicSync()
    
    // ページフォーカス時の同期
    window.addEventListener('focus', () => this.forceSyncCheck())
    
    // ページ離脱時のクリーンアップ
    window.addEventListener('beforeunload', () => this.cleanup())
  }

  /**
   * 安全な学習セッション開始（自動クリーンアップ付き）
   */
  async startSessionSafe(sessionData) {
    try {
      console.log('SessionManager: 安全なセッション開始処理開始', sessionData)

      // 1. 事前同期チェック
      const syncStatus = await this.getSyncStatus()
      console.log('SessionManager: 同期状態確認', syncStatus)

      // 2. 古いセッションがあり、かつクリーンアップが必要な場合は警告
      if (syncStatus.has_active_session && syncStatus.needs_cleanup) {
        console.warn('SessionManager: 長時間のアクティブセッションを検出、自動クリーンアップ実行')
      }

      // 3. 安全なセッション開始API呼び出し
      const response = await this.apiClient.post('/study-sessions/start-safe', {
        subject_area_id: sessionData.subject_area_id,
        study_comment: sessionData.study_comment,
      })

      console.log('SessionManager: セッション開始成功', response)

      // 4. 成功時のログと統計更新
      if (response.success) {
        this.lastSyncTime = Date.now()
        
        // 古いセッションがクリーンアップされた場合の通知
        if (response.cleaned_sessions_count > 0) {
          console.info(`SessionManager: ${response.cleaned_sessions_count}件の古いセッションを自動クリーンアップしました`)
        }
        
        return {
          success: true,
          session: response.session,
          hadOldSessions: response.cleaned_sessions_count > 0,
          cleanedSessionsCount: response.cleaned_sessions_count || 0,
          message: response.message,
        }
      } else {
        return {
          success: false,
          error: response.message || 'セッション開始に失敗しました',
        }
      }

    } catch (error) {
      console.error('SessionManager: セッション開始エラー', error)
      
      if (error.response?.status === 422) {
        return {
          success: false,
          error: 'バリデーションエラー',
          validationErrors: error.response.data?.errors,
        }
      }
      
      if (error.response?.status === 401) {
        return {
          success: false,
          error: '認証が必要です。ログインしてください。',
          needsAuth: true,
        }
      }

      return {
        success: false,
        error: error.response?.data?.message || (error.message === 'Network Error' ? 'ネットワークエラーが発生しました' : error.message) || 'ネットワークエラーが発生しました',
      }
    }
  }

  /**
   * セッション終了
   */
  async endSession(sessionId = null, comment = null) {
    try {
      console.log('SessionManager: endSession呼び出し - sessionId:', sessionId, 'comment:', comment)
      
      // session_idが指定されていない場合は、現在のアクティブセッションを終了する
      // これによりフロントエンドの状態同期問題を回避
      const requestData = {}
      if (sessionId) {
        requestData.session_id = sessionId
      }
      if (comment !== null && comment !== undefined && comment !== '') {
        requestData.study_comment = comment
      }
      
      console.log('SessionManager: 送信データ:', requestData)
      
      const response = await this.apiClient.post('/study-sessions/end', requestData)

      if (response.success) {
        this.lastSyncTime = Date.now()
        return {
          success: true,
          session: response.session,
          message: response.message,
        }
      }

      return {
        success: false,
        error: response.message || 'セッション終了に失敗しました',
      }

    } catch (error) {
      console.error('SessionManager: セッション終了エラー', error)
      return {
        success: false,
        error: error.response?.data?.message || error.message || 'セッション終了中にエラーが発生しました',
      }
    }
  }

  /**
   * 現在のセッション状態取得
   */
  async getCurrentSession() {
    try {
      const response = await this.apiClient.get('/study-sessions/current')
      
      if (response.success) {
        this.lastSyncTime = Date.now()
        return {
          success: true,
          session: response.session,
          hasActiveSession: !!response.session,
        }
      }

      return {
        success: true,
        session: null,
        hasActiveSession: false,
      }

    } catch (error) {
      console.error('SessionManager: 現在セッション取得エラー', error)
      return {
        success: false,
        error: error.response?.data?.message || error.message || 'セッション状態取得に失敗しました',
      }
    }
  }

  /**
   * 同期状態確認
   */
  async getSyncStatus() {
    try {
      const response = await this.apiClient.get('/study-sessions/sync-status')
      
      if (response.success) {
        this.lastSyncTime = Date.now()
        return response
      }

      return {
        success: false,
        has_active_session: false,
        needs_cleanup: false,
        error: response.message,
      }

    } catch (error) {
      console.error('SessionManager: 同期状態確認エラー', error)
      return {
        success: false,
        has_active_session: false,
        needs_cleanup: false,
        error: error.response?.data?.message || error.message || '同期状態確認に失敗しました',
      }
    }
  }

  /**
   * 強制クリーンアップ（ユーザー操作）
   */
  async forceCleanup(reason = 'User requested cleanup') {
    try {
      const response = await this.apiClient.post('/study-sessions/force-cleanup', {
        reason: reason,
      })

      if (response.success) {
        this.lastSyncTime = Date.now()
        return {
          success: true,
          cleanedSessionsCount: response.cleaned_sessions_count,
          message: response.message,
        }
      }

      return {
        success: false,
        error: response.message || 'クリーンアップに失敗しました',
      }

    } catch (error) {
      console.error('SessionManager: 強制クリーンアップエラー', error)
      return {
        success: false,
        error: error.response?.data?.message || error.message || 'クリーンアップ中にエラーが発生しました',
      }
    }
  }

  /**
   * 強制同期チェック
   */
  async forceSyncCheck() {
    console.log('SessionManager: 強制同期チェック実行')
    const syncStatus = await this.getSyncStatus()
    
    if (syncStatus.has_active_session && syncStatus.needs_cleanup) {
      console.warn('SessionManager: 長時間のアクティブセッションを検出')
      // 必要に応じてユーザーに通知やアクションを促す
    }
    
    return syncStatus
  }

  /**
   * 定期同期開始
   */
  startPeriodicSync() {
    // 既存のインターバルをクリア
    if (this.syncCheckInterval) {
      clearInterval(this.syncCheckInterval)
    }

    // 5分間隔で同期チェック
    this.syncCheckInterval = setInterval(() => {
      this.forceSyncCheck()
    }, 5 * 60 * 1000)
  }

  /**
   * クリーンアップ
   */
  cleanup() {
    if (this.syncCheckInterval) {
      clearInterval(this.syncCheckInterval)
      this.syncCheckInterval = null
    }

    window.removeEventListener('focus', this.forceSyncCheck)
    window.removeEventListener('beforeunload', this.cleanup)
  }

  /**
   * 破棄
   */
  destroy() {
    this.cleanup()
    this.apiClient = null
    this.authToken = null
    this.lastSyncTime = null
  }
}