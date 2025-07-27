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
      
      // グローバルポモドーロタイマー（reactiveで明示的にリアクティブ化）
      globalPomodoroTimer: reactive({
        isActive: false,
        currentSession: null,
        timeRemaining: 0,
        startTime: 0,
        timer: null
      }),
      
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
    
    // タイマー状態を復元
    this.restoreTimerStateFromStorage()
    this.restoreStudyTimerStateFromStorage()
    
    // 通知権限を要求
    if (Notification.permission === 'default') {
      Notification.requestPermission()
    }
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
          console.log('認証トークンが無効です。ログアウトします。')
          this.handleLogout()
        } else {
          console.log('一時的なエラーの可能性があります。認証状態を保持します。')
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
    
    // グローバルポモドーロタイマー管理
    startGlobalPomodoroTimer(session) {
      console.log('グローバルタイマー開始:', session)
      this.globalPomodoroTimer.currentSession = session
      this.globalPomodoroTimer.isActive = true
      this.globalPomodoroTimer.startTime = Date.now()
      this.globalPomodoroTimer.timeRemaining = session.planned_duration * 60
      
      // 既存のタイマーがあれば停止
      if (this.globalPomodoroTimer.timer) {
        clearInterval(this.globalPomodoroTimer.timer)
      }
      
      // 新しいタイマーを開始
      this.globalPomodoroTimer.timer = setInterval(() => {
        this.globalPomodoroTimer.timeRemaining--
        
        // 毎秒localStorage を更新
        this.saveTimerStateToStorage()
        
        if (this.globalPomodoroTimer.timeRemaining <= 0) {
          this.handleGlobalTimerComplete()
        }
      }, 1000)
    },
    
    stopGlobalPomodoroTimer() {
      console.log('グローバルタイマー停止')
      if (this.globalPomodoroTimer.timer) {
        clearInterval(this.globalPomodoroTimer.timer)
        this.globalPomodoroTimer.timer = null
      }
      
      this.globalPomodoroTimer.isActive = false
      this.globalPomodoroTimer.currentSession = null
      this.globalPomodoroTimer.timeRemaining = 0
      this.globalPomodoroTimer.startTime = 0
      
      // localStorage をクリア
      localStorage.removeItem('pomodoroTimer')
    },
    
    async handleGlobalTimerComplete() {
      console.log('ポモドーロタイマー完了')
      const completedSession = { ...this.globalPomodoroTimer.currentSession }
      
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
      
      // 一旦タイマー停止（状態をクリア）
      this.stopGlobalPomodoroTimer()
      
      // API セッション完了処理
      await this.completeCurrentSession(completedSession)
      
      // 自動開始設定がONの場合、次のセッションを自動開始
      const settings = completedSession.settings
      const shouldAutoStart = settings?.auto_start_break || settings?.auto_start_focus
      
      if (shouldAutoStart) {
        console.log('次のセッション自動開始準備:', completedSession.session_type)
        setTimeout(() => {
          this.startNextAutoSession(completedSession)
        }, 2000) // 2秒後に自動開始
      }
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
        console.log('音声通知をスキップ:', error)
        // 音声が再生できなくてもエラーにしない
      }
    },
    
    saveTimerStateToStorage() {
      const state = {
        isActive: this.globalPomodoroTimer.isActive,
        currentSession: this.globalPomodoroTimer.currentSession,
        timeRemaining: this.globalPomodoroTimer.timeRemaining,
        startTime: this.globalPomodoroTimer.startTime
      }
      localStorage.setItem('pomodoroTimer', JSON.stringify(state))
    },
    
    restoreTimerStateFromStorage() {
      try {
        const saved = localStorage.getItem('pomodoroTimer')
        if (saved) {
          const state = JSON.parse(saved)
          
          if (state.isActive && state.currentSession) {
            // 経過時間を計算
            const elapsed = Math.floor((Date.now() - state.startTime) / 1000)
            const remaining = state.timeRemaining - elapsed
            
            if (remaining > 0) {
              // タイマーを復元
              this.globalPomodoroTimer.currentSession = state.currentSession
              this.globalPomodoroTimer.isActive = true
              this.globalPomodoroTimer.startTime = state.startTime
              this.globalPomodoroTimer.timeRemaining = remaining
              
              this.globalPomodoroTimer.timer = setInterval(() => {
                this.globalPomodoroTimer.timeRemaining--
                
                if (this.globalPomodoroTimer.timeRemaining <= 0) {
                  this.handleGlobalTimerComplete()
                }
              }, 1000)
              
              console.log('タイマー状態復元成功:', remaining, '秒残り')
            } else {
              // 時間切れ
              this.handleGlobalTimerComplete()
            }
          }
        }
      } catch (error) {
        console.error('タイマー状態復元エラー:', error)
        localStorage.removeItem('pomodoroTimer')
      }
    },
    
    async completeCurrentSession(session) {
      try {
        const actualDuration = Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60)
        
        const response = await axios.post(`/api/pomodoro/${session.id}/complete`, {
          actual_duration: actualDuration,
          was_interrupted: false,
          notes: '自動完了'
        })
        
        if (response.status === 200) {
          console.log('セッション自動完了:', session.session_type)
        }
      } catch (error) {
        console.error('セッション完了エラー:', error)
        // エラーでも次の処理は続行する
      }
    },
    
    async startNextAutoSession(completedSession) {
      try {
        console.log('次のセッション自動開始:', completedSession.session_type)
        
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
          console.log('自動開始設定が無効なため、次のセッションは開始しません')
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
          console.log('次のセッション自動開始:', newSession.session_type)
          
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
            
            console.log('時間計測タイマー状態復元成功:', elapsed, '分経過')
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
      globalStudyTimer: this.globalStudyTimer,
      startGlobalStudyTimer: this.startGlobalStudyTimer,
      stopGlobalStudyTimer: this.stopGlobalStudyTimer
    }
  }
}
</script>

<style scoped>
/* Vue scoped styles */
</style>