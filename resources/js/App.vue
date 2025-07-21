<template>
  <div id="app" class="min-h-screen bg-gray-100">
    <!-- 認証が必要なページのレイアウト -->
    <div v-if="isAuthenticated">
      <!-- ヘッダー -->
      <header class="bg-blue-600 text-white px-4 py-3">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
          <router-link to="/dashboard" class="text-xl font-bold hover:text-blue-200">
            📚 資格学習アプリ
          </router-link>
          <div class="flex items-center gap-4">
            <div class="text-sm">
              👤 {{ user.name }}
            </div>
            <button 
              @click="logout"
              class="text-xs bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded transition-colors"
            >
              ログアウト
            </button>
          </div>
        </div>
      </header>

      <!-- メインコンテンツ -->
      <main class="max-w-4xl mx-auto p-4">
        <!-- 成功メッセージ -->
        <div v-if="successMessage" class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
          {{ successMessage }}
        </div>
        
        <!-- エラーメッセージ -->
        <div v-if="errorMessage" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
          {{ errorMessage }}
        </div>

        <!-- ページコンテンツ -->
        <router-view />
      </main>

      <!-- ボトムナビゲーション -->
      <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 z-50">
        <div class="max-w-4xl mx-auto flex justify-around">
          <router-link 
            to="/dashboard" 
            class="flex flex-col items-center py-2 px-3 rounded-lg transition-colors"
            :class="$route.name === 'Dashboard' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600'"
          >
            <span class="text-lg">📊</span>
            <span class="text-xs mt-1">ダッシュボード</span>
          </router-link>
          
          <router-link 
            to="/study" 
            class="flex flex-col items-center py-2 px-3 rounded-lg transition-colors"
            :class="$route.name === 'StudySession' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600'"
          >
            <span class="text-lg">🚀</span>
            <span class="text-xs mt-1">学習開始</span>
          </router-link>
          
          <router-link 
            to="/history" 
            class="flex flex-col items-center py-2 px-3 rounded-lg transition-colors"
            :class="$route.name === 'History' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600'"
          >
            <span class="text-lg">📚</span>
            <span class="text-xs mt-1">学習履歴</span>
          </router-link>
          
          <router-link 
            to="/settings" 
            class="flex flex-col items-center py-2 px-3 rounded-lg transition-colors"
            :class="$route.name === 'Settings' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-blue-600'"
          >
            <span class="text-lg">⚙️</span>
            <span class="text-xs mt-1">設定</span>
          </router-link>
        </div>
      </nav>

      <!-- スペーサー（ボトムナビのため） -->
      <div class="h-20"></div>
    </div>

    <!-- 認証前の画面 -->
    <div v-else>
      <router-view />
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'App',
  data() {
    return {
      // 認証関連
      isAuthenticated: false,
      user: null,
      authToken: null,
      
      // メッセージ
      errorMessage: '',
      successMessage: ''
    }
  },
  async mounted() {
    // 認証状態をチェック
    this.checkAuthState()
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
    }
  },
  
  // グローバルエラーハンドラ
  provide() {
    return {
      showError: this.showError,
      showSuccess: this.showSuccess
    }
  }
}
</script>

<style scoped>
/* Vue scoped styles */
</style>