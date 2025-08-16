<template>
  <div id="app" class="min-h-screen" style="background-color: var(--color-muted-white)">
    <!-- 認証が必要なページのレイアウト -->
    <div v-if="isAuthenticated">
      <!-- ヘッダー -->
      <header class="text-white px-4 py-3" style="background-color: var(--color-muted-blue)">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
          <router-link to="/dashboard" class="text-xl font-bold transition-colors text-white" @mouseover="handleHeaderLinkHover($event, true)" @mouseout="handleHeaderLinkHover($event, false)">
            📚 Study Log - すたログ
          </router-link>
          <div class="flex items-center gap-4">
            <button 
              @click="navigateToMyPage"
              class="flex items-center gap-2 text-sm hover:bg-blue-600 px-3 py-1 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300"
              title="マイページに移動"
            >
              <img 
                v-if="user.avatar_url" 
                :src="user.avatar_url" 
                :alt="user.nickname"
                class="w-6 h-6 rounded-full object-cover border border-gray-300"
                @error="handleImageError"
              />
              <div 
                v-else 
                class="w-6 h-6 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-xs font-bold text-white"
              >
                {{ user.nickname.charAt(0).toUpperCase() }}
              </div>
              <span>{{ user.nickname }}</span>
            </button>
            <button 
              @click="logout"
              class="text-xs px-3 py-1 rounded transition-colors text-white"
              style="background-color: var(--color-muted-blue-dark);"
              @mouseover="handleLogoutButtonHover($event, true)"
              @mouseout="handleLogoutButtonHover($event, false)"
            >
              ログアウト
            </button>
          </div>
        </div>
      </header>

      <!-- メインコンテンツ -->
      <main class="max-w-4xl mx-auto p-4">
        <!-- 成功メッセージ -->
        <div v-if="successMessage" class="mb-4 p-3 rounded-lg" style="background-color: var(--color-muted-green-light); border: 1px solid var(--color-muted-green); color: var(--color-muted-green-dark);">
          {{ successMessage }}
        </div>
        
        <!-- エラーメッセージ -->
        <div v-if="errorMessage" class="mb-4 p-3 rounded-lg" style="background-color: var(--color-muted-pink-light); border: 1px solid var(--color-muted-pink); color: var(--color-muted-pink-dark);">
          {{ errorMessage }}
        </div>

        <!-- ページコンテンツ -->
        <router-view />
      </main>

      <!-- ボトムナビゲーション -->
      <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 z-50">
        <!-- アクティブなタイマー表示 -->
        <!-- ポモドーロタイマー -->
        <div v-if="globalPomodoroTimer.isActive" 
             :class="[
               'text-white text-xs text-center py-1 mb-2 rounded',
               globalPomodoroTimer.currentSession?.session_type === 'focus' 
                 ? 'bg-red-500' 
                 : 'bg-green-500'
             ]"
        >
          <span v-if="globalPomodoroTimer.currentSession?.session_type === 'focus'">
            🎯 {{ Math.floor(globalPomodoroTimer.timeRemaining / 60).toString().padStart(2, '0') }}:{{ (globalPomodoroTimer.timeRemaining % 60).toString().padStart(2, '0') }} - 集中中
          </span>
          <span v-else-if="globalPomodoroTimer.currentSession?.session_type === 'short_break'">
            ☕ {{ Math.floor(globalPomodoroTimer.timeRemaining / 60).toString().padStart(2, '0') }}:{{ (globalPomodoroTimer.timeRemaining % 60).toString().padStart(2, '0') }} - 休憩中
          </span>
          <span v-else-if="globalPomodoroTimer.currentSession?.session_type === 'long_break'">
            🛋️ {{ Math.floor(globalPomodoroTimer.timeRemaining / 60).toString().padStart(2, '0') }}:{{ (globalPomodoroTimer.timeRemaining % 60).toString().padStart(2, '0') }} - 長い休憩中
          </span>
          <span v-else>
            🍅 {{ Math.floor(globalPomodoroTimer.timeRemaining / 60).toString().padStart(2, '0') }}:{{ (globalPomodoroTimer.timeRemaining % 60).toString().padStart(2, '0') }} - セッション中
          </span>
        </div>
        
        <!-- 時間計測タイマー -->
        <div v-if="globalStudyTimer.isActive"
             class="text-white text-xs text-center py-1 mb-2 rounded"
             style="background-color: var(--color-muted-blue);"
        >
          ⏰ {{ formatElapsedTime(globalStudyTimer.elapsedMinutes) }} - 学習中 ({{ globalStudyTimer.currentSession?.subject_area_name || '時間計測' }})
        </div>
        
        <div class="max-w-4xl mx-auto flex justify-around">
          <router-link 
            to="/dashboard" 
            class="flex flex-col items-center py-1 px-2 rounded-lg transition-colors"
            :style="getNavLinkStyle('Dashboard')"
            @mouseover="handleNavHover($event, 'Dashboard', true)"
            @mouseout="handleNavHover($event, 'Dashboard', false)"
          >
            <span class="text-lg">📊</span>
            <span class="text-xs mt-1">ダッシュボード</span>
          </router-link>
          
          
          <router-link 
            to="/history" 
            class="flex flex-col items-center py-1 px-2 rounded-lg transition-colors"
            :style="getNavLinkStyle('History')"
            @mouseover="handleNavHover($event, 'History', true)"
            @mouseout="handleNavHover($event, 'History', false)"
          >
            <span class="text-lg">📚</span>
            <span class="text-xs mt-1">学習履歴</span>
          </router-link>
          
          <router-link 
            to="/settings" 
            class="flex flex-col items-center py-1 px-2 rounded-lg transition-colors"
            :style="getNavLinkStyle('Settings')"
            @mouseover="handleNavHover($event, 'Settings', true)"
            @mouseout="handleNavHover($event, 'Settings', false)"
          >
            <span class="text-lg">⚙️</span>
            <span class="text-xs mt-1">設定</span>
          </router-link>
        </div>
      </nav>

      <!-- スペーサー（ボトムナビのため） -->
      <div class="h-20"></div>
      
      <!-- オンボーディングモーダル -->
      <OnboardingModal ref="onboardingModalRef" />
    </div>

    <!-- 認証前の画面 -->
    <div v-else>
      <router-view />
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { reactive } from 'vue'
import OnboardingModal from './components/onboarding/OnboardingModal.vue'
import PomodoroTimer from './utils/PomodoroTimer.js'
import { PomodorooCycleManager } from './utils/PomodorooCycleManager.js'
import { POMODORO_CONSTANTS } from './utils/constants.js'
import { debounce } from './utils/debounce.js'

export default {
  name: 'App',
  components: {
    OnboardingModal
  },
  data() {
    return {
      // 認証関連
      isAuthenticated: false,
      user: null,
      authToken: null,
      
      // メッセージ
      errorMessage: '',
      successMessage: '',
      
      // コンポーネント間通信用のイベントバス
      eventBus: new Map(),
      
      // 新しいポモドーロタイマー（v2.0）- Issue #62対応
      pomodoroTimerInstance: null,
      
      // ポモドーロサイクル管理（新規）
      pomodorooCycleManager: null,
      
      // 後方互換性のためのreactiveプロキシ（既存のコードが動作するように保持）
      globalPomodoroTimer: reactive({
        isActive: false,
        currentSession: null,
        timeRemaining: 0,
        startTime: 0,
        timer: null
      }),
      
      // 自動開始管理（新規）
      autoStartState: reactive({
        timeoutId: null,                   // setTimeout ID
        isPending: false,                  // 自動開始待機中フラグ
        pendingSession: null,              // 次のセッション情報
        startTime: null,                   // 自動開始スケジュール時刻
        remainingMs: 0                     // 残り時間（ミリ秒）
      }),
      
      // デバウンスされたストレージ保存関数
      debouncedSaveStorage: null,
      
      // グローバル時間計測タイマー
      globalStudyTimer: reactive({
        isActive: false,
        currentSession: null,
        elapsedMinutes: 0,
        startTime: 0,
        timer: null
      }),
      
      // オンボーディング関連
      onboardingModalRef: null
    }
  },
  async mounted() {
    // 認証状態をチェック
    this.checkAuthState()
    
    // v2.0ポモドーロタイマーを初期化
    this.initializePomodoroTimer()
    
    // ポモドーロサイクル管理を初期化
    this.initializePomodorooCycleManager()
    
    // タイマー状態を復元
    this.restoreTimerStateFromStorage()
    this.restoreStudyTimerStateFromStorage()
    this.restoreCycleStateFromStorage()
    
    // 通知権限を要求（遅延実行）
    setTimeout(() => {
      this.requestNotificationPermission()
    }, POMODORO_CONSTANTS.NOTIFICATION_PERMISSION_REQUEST_DELAY_MS)
  },
  methods: {
    // 認証状態をチェック
    checkAuthState() {
      const token = localStorage.getItem('auth_token')
      const userData = localStorage.getItem('user')
      
      if (token && userData) {
        try {
          this.authToken = token
          this.user = JSON.parse(userData)
          this.isAuthenticated = true
          
          // Axiosのデフォルトヘッダーにトークンを設定
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
          
          // 認証状態を確認
          this.verifyAuth()
          
          // 認証済みでログイン画面にいる場合はダッシュボードにリダイレクト
          if (this.$route.path === '/login' || this.$route.path === '/register') {
            this.$router.push('/dashboard')
          }
        } catch (error) {
          console.error('認証状態復元エラー:', error)
          this.handleLogout()
        }
      }
    },
    
    async verifyAuth() {
      try {
        const response = await axios.get('/api/user')
        if (response.data.success) {
          this.user = response.data.user
          localStorage.setItem('user', JSON.stringify(response.data.user))
          
          // 認証確認完了後、オンボーディングをチェック
          await this.checkAndShowOnboarding()
        } else {
          console.warn('認証状態確認失敗:', response.data)
          this.handleLogout()
        }
      } catch (error) {
        console.error('認証確認エラー:', error)
        console.error('エラーレスポンス:', error.response?.data)
        // 認証エラー（401）以外は再試行の余地があるかもしれないので、すぐにはログアウトしない
        if (error.response?.status === 401) {
          this.handleLogout()
        } else {
        }
      }
    },
    
    async logout() {
      try {
        await axios.post('/api/auth/logout')
      } catch (error) {
        console.error('ログアウトエラー:', error)
      } finally {
        this.handleLogout()
      }
    },
    
    handleLogout() {
      this.isAuthenticated = false
      this.user = null
      this.authToken = null
      
      // ローカルストレージをクリア
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      
      // Axiosヘッダーをクリア
      delete axios.defaults.headers.common['Authorization']
      
      // ログインページにリダイレクト
      if (this.$route.path !== '/login' && this.$route.path !== '/register') {
        this.$router.push('/login')
      }
    },
    
    // グローバルメッセージ表示
    showError(message) {
      this.errorMessage = message
      this.successMessage = ''
      setTimeout(() => {
        this.errorMessage = ''
      }, 5000)
    },
    
    showSuccess(message) {
      this.successMessage = message
      this.errorMessage = ''
      setTimeout(() => {
        this.successMessage = ''
      }, 5000)
    },
    
    // イベントバス：データ更新通知
    notifyDataUpdate(eventType) {
      const listeners = this.eventBus.get(eventType) || []
      listeners.forEach(callback => {
        if (typeof callback === 'function') {
          callback()
        }
      })
    },
    
    // イベント購読
    subscribeToDataUpdate(eventType, callback) {
      if (!this.eventBus.has(eventType)) {
        this.eventBus.set(eventType, [])
      }
      this.eventBus.get(eventType).push(callback)
    },
    
    // イベント購読解除
    unsubscribeFromDataUpdate(eventType, callback) {
      if (this.eventBus.has(eventType)) {
        const listeners = this.eventBus.get(eventType)
        const index = listeners.indexOf(callback)
        if (index > -1) {
          listeners.splice(index, 1)
        }
      }
    },
    
    // v2.0ポモドーロタイマー初期化
    initializePomodoroTimer() {
      this.pomodoroTimerInstance = new PomodoroTimer()
      
      // デバウンスされたストレージ保存関数を作成（パフォーマンス最適化）
      this.debouncedSaveStorage = debounce(() => {
        this.saveTimerStateToStorage()
      }, POMODORO_CONSTANTS.STORAGE_DEBOUNCE_MS)
    },
    
    // ポモドーロサイクル管理初期化
    initializePomodorooCycleManager() {
      this.pomodorooCycleManager = new PomodorooCycleManager()
      console.log('ポモドーロサイクル管理を初期化')
    },
    
    // 通知権限リクエスト
    async requestNotificationPermission() {
      if ('Notification' in window && Notification.permission === 'default') {
        try {
          const permission = await Notification.requestPermission()
          console.log('通知権限:', permission)
        } catch (error) {
          console.warn('通知権限リクエストエラー:', error)
        }
      }
    },
    
    // グローバルポモドーロタイマー管理（v2.0対応）- Issue #62修正
    startGlobalPomodoroTimer(session) {
      
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
          console.error('ポモドーロタイマーエラー:', error)
          this.stopGlobalPomodoroTimer()
        }
      }
      
      // v2.0タイマー開始（レースコンディション問題完全修正）
      this.pomodoroTimerInstance.start(durationSeconds, callbacks, session)
      
      // 後方互換性のため既存のreactiveオブジェクトを更新
      this.globalPomodoroTimer.isActive = true
      this.globalPomodoroTimer.currentSession = session
      this.globalPomodoroTimer.startTime = this.pomodoroTimerInstance.startTime
      this.globalPomodoroTimer.timer = 'v2.0' // v2.0使用の識別
    },
    
    stopGlobalPomodoroTimer() {
      
      if (this.pomodoroTimerInstance) {
        this.pomodoroTimerInstance.stop()
      }
      
      // 自動開始もキャンセル
      this.clearAutoStart()
      
      // 後方互換性のため既存のreactiveオブジェクトをクリア
      this.globalPomodoroTimer.isActive = false
      this.globalPomodoroTimer.currentSession = null
      this.globalPomodoroTimer.timeRemaining = 0
      this.globalPomodoroTimer.startTime = 0
      this.globalPomodoroTimer.timer = null
      
      // localStorage をクリア
      localStorage.removeItem('pomodoroTimer')
    },
    
    // 一時停止・再開機能（v2.0対応）
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
    
    async handleGlobalTimerComplete() {
      console.log('ポモドーロタイマー完了 (v2.0)')
      const completedSession = { ...this.globalPomodoroTimer.currentSession }
      
      // ポモドーロサイクル状態を更新
      if (this.pomodorooCycleManager && completedSession) {
        if (completedSession.session_type === 'focus') {
          this.pomodorooCycleManager.incrementFocusSession()
        } else {
          this.pomodorooCycleManager.completeBreakSession()
        }
        
        // サイクル状態を保存
        this.saveCycleStateToStorage()
      }
      
      // 通知表示
      if (Notification.permission === 'granted') {
        const sessionType = completedSession?.session_type
        const messages = {
          focus: '🎯 集中セッション完了！',
          short_break: '☕ 短い休憩完了！',
          long_break: '🛋️ 長い休憩完了！'
        }
        
        new Notification('ポモドーロタイマー', {
          body: messages[sessionType] || 'セッション完了！',
          icon: '/favicon.ico'
        })
      }
      
      // 音声通知
      this.playNotificationSound()
      
      // API セッション完了処理を先に実行
      await this.completeCurrentSession(completedSession)
      
      // 一旦タイマー停止（状態をクリア）
      this.stopGlobalPomodoroTimer()
      
      // サイクルベースの自動開始判定
      this.handleAutoStartWithCycleManagement(completedSession)
    },
    
    playNotificationSound() {
      try {
        // ブラウザの標準通知音を使用（音声ファイルエラーを回避）
        const context = new (window.AudioContext || window.webkitAudioContext)()
        const oscillator = context.createOscillator()
        const gainNode = context.createGain()
        
        oscillator.connect(gainNode)
        gainNode.connect(context.destination)
        
        oscillator.frequency.value = 800
        gainNode.gain.setValueAtTime(0.3, context.currentTime)
        gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.5)
        
        oscillator.start(context.currentTime)
        oscillator.stop(context.currentTime + 0.5)
      } catch (error) {
        // 音声が再生できなくてもエラーにしない
      }
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
    },
    
    // ポモドーロサイクル状態をローカルストレージに保存
    saveCycleStateToStorage() {
      if (this.pomodorooCycleManager) {
        const serializedState = this.pomodorooCycleManager.serialize()
        localStorage.setItem(POMODORO_CONSTANTS.STORAGE_KEYS.CYCLE_STATE, JSON.stringify(serializedState))
        console.log('サイクル状態保存')
      }
    },
    
    // ポモドーロサイクル状態をローカルストレージから復元
    restoreCycleStateFromStorage() {
      try {
        const saved = localStorage.getItem(POMODORO_CONSTANTS.STORAGE_KEYS.CYCLE_STATE)
        if (saved && this.pomodorooCycleManager) {
          const state = JSON.parse(saved)
          this.pomodorooCycleManager.restoreFromStorage(state)
          console.log('サイクル状態復元成功:', this.pomodorooCycleManager.getCycleStats())
        }
      } catch (error) {
        console.error('サイクル状態復元エラー:', error)
        localStorage.removeItem(POMODORO_CONSTANTS.STORAGE_KEYS.CYCLE_STATE)
      }
    },
    
    async completeCurrentSession(session) {
      try {
        // v2.0タイマーから正確な実際の経過時間を取得
        const actualDuration = this.pomodoroTimerInstance ? 
          this.pomodoroTimerInstance.getActualDurationMinutes() :
          (this.globalPomodoroTimer && this.globalPomodoroTimer.startTime ? 
            Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60) : 
            session.planned_duration)
        
        const response = await axios.post(`/api/pomodoro/${session.id}/complete`, {
          actual_duration: actualDuration,
          was_interrupted: false,
          notes: 'v2.0タイマー自動完了'
        })
        
        if (response.status === 200) {
        }
      } catch (error) {
        console.error('セッション完了エラー:', error)
        // エラーでも次の処理は続行する
      }
    },
    
    async startNextAutoSession(completedSession) {
      try {
        
        // 次のセッションタイプを決定
        let nextSessionType
        let nextDuration
        const settings = completedSession.settings
        
        if (completedSession.session_type === 'focus') {
          // 集中→休憩
          nextSessionType = 'short_break'
          nextDuration = settings?.short_break_duration || 5
        } else if (completedSession.session_type === 'short_break') {
          // 短い休憩→集中
          nextSessionType = 'focus'
          nextDuration = settings?.focus_duration || 25
        } else if (completedSession.session_type === 'long_break') {
          // 長い休憩→集中
          nextSessionType = 'focus'
          nextDuration = settings?.focus_duration || 25
        }
        
        // 自動開始の設定確認
        const shouldAutoStart = (
          (nextSessionType !== 'focus' && settings?.auto_start_break) ||
          (nextSessionType === 'focus' && settings?.auto_start_focus)
        )
        
        if (!shouldAutoStart) {
          return
        }
        
        // APIで次のセッションを作成
        const sessionData = {
          session_type: nextSessionType,
          planned_duration: nextDuration,
          study_session_id: null,
          subject_area_id: nextSessionType === 'focus' ? completedSession.subject_area_id : null,
          settings: settings
        }
        
        const response = await axios.post('/api/pomodoro', sessionData)
        
        if (response.status === 201 || response.status === 200) {
          const newSession = response.data
          
          // グローバルタイマーで新しいセッションを開始
          this.startGlobalPomodoroTimer(newSession)
          
          // 自動開始通知
          if (Notification.permission === 'granted') {
            const messages = {
              focus: '🎯 集中セッション自動開始！',
              short_break: '☕ 短い休憩自動開始！',
              long_break: '🛋️ 長い休憩自動開始！'
            }
            
            new Notification('ポモドーロタイマー', {
              body: messages[nextSessionType] || '次のセッション自動開始！',
              icon: '/favicon.ico'
            })
          }
        } else {
          console.error('次のセッション作成失敗:', response.status, response.data)
        }
      } catch (error) {
        console.error('次のセッション自動開始エラー:', error)
      }
    },
    
    // サイクル管理を使った自動開始処理
    handleAutoStartWithCycleManagement(completedSession) {
      console.log('🔄 自動開始処理開始:', { completedSession })
      
      if (!this.pomodorooCycleManager || !completedSession) {
        console.log('❌ 前提条件不足:', { 
          pomodorooCycleManager: !!this.pomodorooCycleManager, 
          completedSession: !!completedSession 
        })
        return
      }
      
      const settings = completedSession.settings
      
      if (!settings?.auto_start_break && !settings?.auto_start_focus) {
        return
      }
      
      // サイクル管理から次のセッションタイプを決定
      const nextSessionType = this.pomodorooCycleManager.getNextSessionType()
      const cycleStats = this.pomodorooCycleManager.getCycleStats()
      
      
      // 自動開始設定の個別チェック
      const breakCondition = (nextSessionType !== 'focus' && settings?.auto_start_break)
      const focusCondition = (nextSessionType === 'focus' && settings?.auto_start_focus)
      const shouldAutoStart = breakCondition || focusCondition
      
      console.log('🔍 自動開始判定詳細:', {
        nextSessionType,
        breakCondition: `${nextSessionType !== 'focus'} && ${settings?.auto_start_break} = ${breakCondition}`,
        focusCondition: `${nextSessionType === 'focus'} && ${settings?.auto_start_focus} = ${focusCondition}`,
        shouldAutoStart
      })
      
      if (!shouldAutoStart) {
        console.log(`❌ 自動開始設定が無効 (${nextSessionType})`)
        return
      }
      
      console.log('✅ 自動開始条件クリア - タイマー開始します')
      
      // 長い休憩の場合はサイクル完了処理
      if (nextSessionType === 'long_break' && cycleStats.isLongBreakTime) {
        const completedCycle = this.pomodorooCycleManager.completeCycle()
        console.log('ポモドーロサイクル完了:', completedCycle)
        this.saveCycleStateToStorage()
      }
      
      // 自動開始実行（遅延あり）
      this.scheduleAutoStart(() => {
        this.startNextAutoSessionWithCycleInfo(completedSession, nextSessionType)
      }, POMODORO_CONSTANTS.AUTO_START_DELAY_MS)
    },
    
    // サイクル情報を使った次セッション開始
    async startNextAutoSessionWithCycleInfo(completedSession, nextSessionType) {
      try {
        const settings = completedSession.settings
        
        // デフォルト時間設定
        let nextDuration
        if (nextSessionType === 'focus') {
          nextDuration = settings?.focus_duration || POMODORO_CONSTANTS.DEFAULT_FOCUS_DURATION
        } else if (nextSessionType === 'short_break') {
          nextDuration = settings?.short_break_duration || POMODORO_CONSTANTS.DEFAULT_SHORT_BREAK_DURATION
        } else if (nextSessionType === 'long_break') {
          nextDuration = settings?.long_break_duration || POMODORO_CONSTANTS.DEFAULT_LONG_BREAK_DURATION
        }
        
        console.log(`サイクルベース自動開始: ${nextSessionType} (${nextDuration}分)`)
        
        // APIで次のセッションを作成
        const sessionData = {
          session_type: nextSessionType,
          planned_duration: nextDuration,
          study_session_id: null,
          subject_area_id: nextSessionType === 'focus' ? completedSession.subject_area_id : null,
          settings: settings
        }
        
        const response = await axios.post('/api/pomodoro', sessionData)
        
        if (response.status === 201 || response.status === 200) {
          const newSession = response.data
          console.log('サイクルベース自動開始成功:', newSession.session_type)
          
          // グローバルタイマーで新しいセッションを開始
          this.startGlobalPomodoroTimer(newSession)
          
          // 自動開始通知
          if (Notification.permission === 'granted') {
            const messages = {
              focus: '🎯 集中セッション自動開始！',
              short_break: '☕ 短い休憩自動開始！',
              long_break: '🛋️ 長い休憩自動開始！'
            }
            
            new Notification('ポモドーロタイマー', {
              body: messages[nextSessionType] || '次のセッション自動開始！',
              icon: '/favicon.ico'
            })
          }
        } else {
          console.error('サイクルベース次セッション作成失敗:', response.status, response.data)
        }
      } catch (error) {
        console.error('サイクルベース自動開始エラー:', error)
      }
    },
    
    // ========== 自動開始管理メソッド ==========
    
    // 自動開始をスケジュール
    scheduleAutoStart(next, delayMs = POMODORO_CONSTANTS.AUTO_START_DELAY_MS) {
      // 既存の自動開始をクリア
      this.clearAutoStart()
      
      this.autoStartState.isPending = true
      // Accept either a session object or a callback that will start the next session
      this.autoStartState.pendingSession = next
      this.autoStartState.startTime = Date.now() + delayMs
      this.autoStartState.remainingMs = delayMs
      
      const typeLabel = typeof next === 'function' ? 'callback' : next?.session_type
      console.log(`自動開始スケジュール: ${typeLabel} (${delayMs}ms後)`)
      
      this.autoStartState.timeoutId = setTimeout(() => {
        this.executeAutoStart()
      }, delayMs)
    },
    
    // 自動開始を実行
    executeAutoStart() {
      if (this.autoStartState.isPending && this.autoStartState.pendingSession) {
        const pending = this.autoStartState.pendingSession
        const typeLabel = typeof pending === 'function' ? 'callback' : pending?.session_type
        console.log('自動開始実行:', typeLabel)
        
        // セッションを開始（既に稼働中ならスキップ）
        if (this.globalPomodoroTimer?.isActive) {
          console.log('自動開始スキップ: 既にタイマーが稼働中')
          return
        }
        
        // 状態をクリア
        this.clearAutoStart()
        
        // セッションを開始
        if (typeof pending === 'function') {
          pending()
        } else {
          this.startGlobalPomodoroTimer(pending)
        }
      }
    },
    
    // 自動開始をキャンセル/クリア
    clearAutoStart() {
      if (this.autoStartState.timeoutId) {
        clearTimeout(this.autoStartState.timeoutId)
        console.log('自動開始キャンセル')
      }
      
      this.autoStartState.timeoutId = null
      this.autoStartState.isPending = false
      this.autoStartState.pendingSession = null
      this.autoStartState.startTime = null
      this.autoStartState.remainingMs = 0
    },
    
    // 自動開始の残り時間を取得
    getAutoStartRemainingTime() {
      if (!this.autoStartState.isPending || !this.autoStartState.startTime) {
        return 0
      }
      
      const remaining = Math.max(0, this.autoStartState.startTime - Date.now())
      return Math.ceil(remaining / 1000) // 秒単位で返す
    },
    
    // ========== 時間計測タイマー管理 ==========
    
    // 時間計測タイマー開始
    startGlobalStudyTimer(session) {
      console.log('グローバル時間計測タイマー開始:', session)
      this.globalStudyTimer.currentSession = session
      this.globalStudyTimer.isActive = true
      this.globalStudyTimer.startTime = Date.now()
      this.globalStudyTimer.elapsedMinutes = 0
      
      // 既存のタイマーがあれば停止
      if (this.globalStudyTimer.timer) {
        clearInterval(this.globalStudyTimer.timer)
      }
      
      // 新しいタイマーを開始（1分ごとに更新）
      this.globalStudyTimer.timer = setInterval(() => {
        this.updateStudyElapsedTime()
        this.saveStudyTimerStateToStorage()
      }, 1000) // 1秒ごとに更新
    },
    
    // 時間計測タイマー停止
    stopGlobalStudyTimer() {
      console.log('グローバル時間計測タイマー停止')
      if (this.globalStudyTimer.timer) {
        clearInterval(this.globalStudyTimer.timer)
        this.globalStudyTimer.timer = null
      }
      
      this.globalStudyTimer.isActive = false
      this.globalStudyTimer.currentSession = null
      this.globalStudyTimer.elapsedMinutes = 0
      this.globalStudyTimer.startTime = 0
      
      // localStorage をクリア
      localStorage.removeItem('studyTimer')
    },
    
    // 経過時間を更新
    updateStudyElapsedTime() {
      if (this.globalStudyTimer.isActive && this.globalStudyTimer.startTime) {
        const now = Date.now()
        const elapsedMinutes = Math.floor((now - this.globalStudyTimer.startTime) / (1000 * 60))
        this.globalStudyTimer.elapsedMinutes = Math.max(0, elapsedMinutes)
      }
    },
    
    // 時間計測タイマー状態をlocalStorageに保存
    saveStudyTimerStateToStorage() {
      const state = {
        isActive: this.globalStudyTimer.isActive,
        currentSession: this.globalStudyTimer.currentSession,
        elapsedMinutes: this.globalStudyTimer.elapsedMinutes,
        startTime: this.globalStudyTimer.startTime
      }
      localStorage.setItem('studyTimer', JSON.stringify(state))
    },
    
    // 時間計測タイマー状態をlocalStorageから復元
    restoreStudyTimerStateFromStorage() {
      try {
        const saved = localStorage.getItem('studyTimer')
        if (saved) {
          const state = JSON.parse(saved)
          
          if (state.isActive && state.currentSession && state.startTime) {
            // 現在の経過時間を計算
            const elapsed = Math.floor((Date.now() - state.startTime) / (1000 * 60))
            
            // タイマーを復元
            this.globalStudyTimer.currentSession = state.currentSession
            this.globalStudyTimer.isActive = true
            this.globalStudyTimer.startTime = state.startTime
            this.globalStudyTimer.elapsedMinutes = elapsed
            
            // タイマーを再開
            this.globalStudyTimer.timer = setInterval(() => {
              this.updateStudyElapsedTime()
              this.saveStudyTimerStateToStorage()
            }, 1000)
            
          }
        }
      } catch (error) {
        console.error('時間計測タイマー状態復元エラー:', error)
        localStorage.removeItem('studyTimer')
      }
    },
    
    // 時間フォーマット関数
    formatElapsedTime(minutes) {
      const totalMinutes = Math.max(0, Math.floor(Number(minutes) || 0))
      const hours = Math.floor(totalMinutes / 60)
      const mins = totalMinutes % 60
      
      if (hours > 0) {
        return `${hours}時間${mins}分`
      } else {
        return `${mins}分`
      }
    },

    // ナビゲーションリンクのスタイル取得
    getNavLinkStyle(routeName) {
      const isActive = this.$route.name === routeName
      return {
        color: isActive ? 'var(--color-muted-blue-dark)' : 'var(--color-muted-gray-dark)',
        backgroundColor: isActive ? 'var(--color-muted-blue-light)' : 'transparent'
      }
    },

    // ナビゲーションホバーハンドラー
    handleNavHover(event, routeName, isHover) {
      if (this.$route.name !== routeName) {
        event.target.style.color = isHover ? 'var(--color-muted-blue)' : 'var(--color-muted-gray-dark)'
      }
    },

    // ヘッダーリンクホバーハンドラー
    handleHeaderLinkHover(event, isHover) {
      event.target.style.color = isHover ? 'var(--color-muted-blue-light)' : 'white'
    },

    // ログアウトボタンホバーハンドラー
    handleLogoutButtonHover(event, isHover) {
      event.target.style.backgroundColor = isHover ? 'var(--color-muted-blue-light)' : 'var(--color-muted-blue-dark)'
    },

    // マイページに移動
    navigateToMyPage() {
      this.$router.push('/mypage')
    },

    // 画像エラーハンドリング
    handleImageError(event) {
      // 画像読み込みエラー時は非表示にする
      event.target.style.display = 'none'
    },
    
    // オンボーディング関連メソッド
    async checkAndShowOnboarding() {
      try {
        // 少し遅延してから実行（UIが安定してから）
        await new Promise(resolve => setTimeout(resolve, 1000))
        
        // オンボーディングモーダルコンポーネントが利用可能かチェック
        if (this.$refs.onboardingModalRef && this.$refs.onboardingModalRef.showOnboarding) {
          await this.$refs.onboardingModalRef.showOnboarding()
        } else {
          console.warn('オンボーディングモーダルが利用できません')
        }
      } catch (error) {
        console.error('オンボーディングチェックエラー:', error)
        // エラーが発生してもアプリの動作は継続
      }
    },
    
    // 手動でオンボーディングを表示（設定画面から呼び出し用）
    async showOnboardingManually() {
      try {
        if (this.$refs.onboardingModalRef && this.$refs.onboardingModalRef.showOnboarding) {
          await this.$refs.onboardingModalRef.showOnboarding()
        }
      } catch (error) {
        console.error('手動オンボーディング表示エラー:', error)
        this.showError('オンボーディング表示中にエラーが発生しました')
      }
    }
  },
  
  // グローバルエラーハンドラ
  provide() {
    return {
      showError: this.showError,
      showSuccess: this.showSuccess,
      globalPomodoroTimer: this.globalPomodoroTimer,
      startGlobalPomodoroTimer: this.startGlobalPomodoroTimer,
      stopGlobalPomodoroTimer: this.stopGlobalPomodoroTimer,
      pauseGlobalPomodoroTimer: this.pauseGlobalPomodoroTimer,  // v2.0新機能
      resumeGlobalPomodoroTimer: this.resumeGlobalPomodoroTimer, // v2.0新機能
      globalStudyTimer: this.globalStudyTimer,
      startGlobalStudyTimer: this.startGlobalStudyTimer,
      stopGlobalStudyTimer: this.stopGlobalStudyTimer,
      notifyDataUpdate: this.notifyDataUpdate,
      subscribeToDataUpdate: this.subscribeToDataUpdate,
      unsubscribeFromDataUpdate: this.unsubscribeFromDataUpdate
    }
  }
}
</script>

<style scoped>
/* Vue scoped styles */
</style>