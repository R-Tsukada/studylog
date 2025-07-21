<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">📚 資格学習アプリ</h1>
        <p class="text-gray-600">学習記録で目標達成をサポート</p>
      </div>

      <!-- ログイン・登録フォーム -->
      <div v-if="!isRegisterMode">
        <h2 class="text-xl font-semibold mb-4 text-center">ログイン</h2>
        <form @submit.prevent="login">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">メールアドレス</label>
            <input 
              type="email" 
              v-model="loginForm.email"
              required
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="your-email@example.com"
            />
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">パスワード</label>
            <input 
              type="password" 
              v-model="loginForm.password"
              required
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="パスワード"
            />
          </div>
          <button 
            type="submit" 
            :disabled="loading"
            class="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg mb-4"
          >
            {{ loading ? 'ログイン中...' : 'ログイン' }}
          </button>
        </form>
        
        <div class="text-center mb-4">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white text-gray-500">または</span>
            </div>
          </div>
        </div>
        
        <button 
          @click="loginWithGoogle"
          :disabled="loading"
          class="w-full bg-red-500 hover:bg-red-600 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg mb-4"
        >
          🔍 Googleでログイン
        </button>
        
        <p class="text-center text-sm text-gray-600">
          アカウントをお持ちでない方は
          <router-link to="/register" class="text-blue-500 hover:text-blue-600 font-medium">
            新規登録
          </router-link>
        </p>
      </div>

      <!-- 登録フォーム -->
      <div v-else>
        <h2 class="text-xl font-semibold mb-4 text-center">新規登録</h2>
        <form @submit.prevent="register">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">お名前</label>
            <input 
              type="text" 
              v-model="registerForm.name"
              required
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="お名前"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">メールアドレス</label>
            <input 
              type="email" 
              v-model="registerForm.email"
              required
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="your-email@example.com"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">パスワード</label>
            <input 
              type="password" 
              v-model="registerForm.password"
              required
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="8文字以上のパスワード"
            />
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">パスワード確認</label>
            <input 
              type="password" 
              v-model="registerForm.password_confirmation"
              required
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="パスワードを再入力"
            />
          </div>
          <button 
            type="submit" 
            :disabled="loading"
            class="w-full bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg mb-4"
          >
            {{ loading ? '登録中...' : '新規登録' }}
          </button>
        </form>
        
        <p class="text-center text-sm text-gray-600">
          既にアカウントをお持ちの方は
          <router-link to="/login" class="text-blue-500 hover:text-blue-600 font-medium">
            ログイン
          </router-link>
        </p>
      </div>

      <!-- エラーメッセージ -->
      <div v-if="errorMessage" class="mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        {{ errorMessage }}
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'LoginPage',
  props: {
    showRegister: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      loading: false,
      errorMessage: '',
      
      loginForm: {
        email: '',
        password: ''
      },
      registerForm: {
        name: '',
        email: '',
        password: '',
        password_confirmation: ''
      }
    }
  },
  computed: {
    isRegisterMode() {
      return this.showRegister || this.$route.name === 'Register'
    }
  },
  methods: {
    async login() {
      this.loading = true
      this.errorMessage = ''
      
      try {
        const response = await axios.post('/api/auth/login', this.loginForm)
        
        if (response.data.success) {
          this.handleAuthSuccess(response.data)
        } else {
          this.showError(response.data.message || 'ログインに失敗しました')
        }
      } catch (error) {
        console.error('ログインエラー:', error)
        if (error.response?.data?.message) {
          this.showError(error.response.data.message)
        } else {
          this.showError('ログイン中にエラーが発生しました')
        }
      } finally {
        this.loading = false
      }
    },
    
    async register() {
      this.loading = true
      this.errorMessage = ''
      
      try {
        const response = await axios.post('/api/auth/register', this.registerForm)
        
        if (response.data.success) {
          this.handleAuthSuccess(response.data)
        } else {
          this.showError(response.data.message || '登録に失敗しました')
        }
      } catch (error) {
        console.error('登録エラー:', error)
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat()
          this.showError(errors.join('\n'))
        } else if (error.response?.data?.message) {
          this.showError(error.response.data.message)
        } else {
          this.showError('登録中にエラーが発生しました')
        }
      } finally {
        this.loading = false
      }
    },
    
    async loginWithGoogle() {
      this.loading = true
      this.errorMessage = ''
      
      try {
        const response = await axios.get('/api/auth/google')
        
        if (response.data.success && response.data.redirect_url) {
          window.location.href = response.data.redirect_url
        } else {
          this.showError('Google認証の初期化に失敗しました')
        }
      } catch (error) {
        console.error('Google認証エラー:', error)
        this.showError('Google認証中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },
    
    handleAuthSuccess(data) {
      // トークンをローカルストレージに保存
      localStorage.setItem('auth_token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      
      // Axiosのデフォルトヘッダーにトークンを設定
      axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
      
      // ダッシュボードにリダイレクト
      this.$router.push('/dashboard')
    },
    
    showError(message) {
      this.errorMessage = message
    }
  }
}
</script>