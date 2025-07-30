<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600">
    <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">📚 Study Log - すたログ</h1>
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
              @blur="validateLoginEmail"
              required
              :class="[
                'w-full p-3 border rounded-lg focus:ring-2',
                loginEmailError ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
              ]"
              placeholder="your-email@example.com"
            />
            <p v-if="loginEmailError" class="mt-1 text-sm text-red-600">{{ loginEmailError }}</p>
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">パスワード</label>
            <input 
              type="password" 
              v-model="loginForm.password"
              @blur="validateLoginPassword"
              required
              :class="[
                'w-full p-3 border rounded-lg focus:ring-2',
                loginPasswordError ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
              ]"
              placeholder="パスワード"
            />
            <p v-if="loginPasswordError" class="mt-1 text-sm text-red-600">{{ loginPasswordError }}</p>
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
          disabled
          class="w-full bg-gray-400 cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg mb-4 opacity-60"
          title="Google認証機能は現在準備中です"
        >
          🔍 Googleでログイン（準備中）
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
            <label class="block text-sm font-medium text-gray-700 mb-2">ニックネーム</label>
            <input 
              type="text" 
              v-model="registerForm.nickname"
              @input="validateNickname"
              @blur="validateNickname"
              required
              :class="[
                'w-full p-3 border rounded-lg focus:ring-2',
                nicknameError ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
              ]"
              placeholder="ニックネーム（2-50文字）"
            />
            <p v-if="nicknameError" class="mt-1 text-sm text-red-600">{{ nicknameError }}</p>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">メールアドレス</label>
            <input 
              type="email" 
              v-model="registerForm.email"
              @input="validateRegisterEmail"
              @blur="validateRegisterEmail"
              required
              :class="[
                'w-full p-3 border rounded-lg focus:ring-2',
                registerEmailError ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
              ]"
              placeholder="your-email@example.com"
            />
            <p v-if="registerEmailError" class="mt-1 text-sm text-red-600">{{ registerEmailError }}</p>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">パスワード</label>
            <input 
              type="password" 
              v-model="registerForm.password"
              @input="validatePassword"
              @blur="validatePassword"
              required
              :class="[
                'w-full p-3 border rounded-lg focus:ring-2',
                passwordError ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
              ]"
              placeholder="8文字以上、英数字記号を含む"
            />
            <p v-if="passwordError" class="mt-1 text-sm text-red-600">{{ passwordError }}</p>
            <div v-if="registerForm.password" class="mt-2">
              <div class="text-xs text-gray-600 mb-1">パスワード強度:</div>
              <div class="flex space-x-1">
                <div v-for="(check, index) in passwordChecks" :key="index" 
                     :class="['h-2 w-full rounded', check.valid ? 'bg-green-500' : 'bg-gray-300']">
                </div>
              </div>
              <div class="text-xs text-gray-600 mt-1">
                <div v-for="(check, index) in passwordChecks" :key="index" 
                     :class="['flex items-center', check.valid ? 'text-green-600' : 'text-gray-500']">
                  <span class="mr-1">{{ check.valid ? '✓' : '○' }}</span>
                  {{ check.label }}
                </div>
              </div>
            </div>
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">パスワード確認</label>
            <input 
              type="password" 
              v-model="registerForm.password_confirmation"
              @input="validatePasswordConfirmation"
              @blur="validatePasswordConfirmation"
              required
              :class="[
                'w-full p-3 border rounded-lg focus:ring-2',
                passwordConfirmationError ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'
              ]"
              placeholder="パスワードを再入力"
            />
            <p v-if="passwordConfirmationError" class="mt-1 text-sm text-red-600">{{ passwordConfirmationError }}</p>
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
        nickname: '',
        email: '',
        password: '',
        password_confirmation: ''
      },
      
      // バリデーションエラー
      loginEmailError: '',
      loginPasswordError: '',
      nicknameError: '',
      registerEmailError: '',
      passwordError: '',
      passwordConfirmationError: ''
    }
  },
  computed: {
    isRegisterMode() {
      return this.showRegister || this.$route.name === 'Register'
    },
    
    passwordChecks() {
      const password = this.registerForm.password
      return [
        { label: '8文字以上', valid: password.length >= 8 },
        { label: '英字を含む', valid: /[a-zA-Z]/.test(password) },
        { label: '数字を含む', valid: /\d/.test(password) },
        { label: '記号を含む', valid: /[!@#$%^&*(),.?":{}|<>]/.test(password) }
      ]
    }
  },
  methods: {
    // バリデーションメソッド
    validateLoginEmail() {
      const email = this.loginForm.email
      if (!email) {
        this.loginEmailError = 'メールアドレスは必須です'
        return false
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        this.loginEmailError = '正しいメールアドレス形式で入力してください'
        return false
      }
      this.loginEmailError = ''
      return true
    },
    
    validateLoginPassword() {
      const password = this.loginForm.password
      if (!password) {
        this.loginPasswordError = 'パスワードは必須です'
        return false
      }
      if (password.length < 8) {
        this.loginPasswordError = 'パスワードは8文字以上で入力してください'
        return false
      }
      this.loginPasswordError = ''
      return true
    },
    
    validateNickname() {
      const nickname = this.registerForm.nickname
      if (!nickname) {
        this.nicknameError = 'ニックネームは必須です'
        return false
      }
      if (nickname.length < 2) {
        this.nicknameError = 'ニックネームは2文字以上で入力してください'
        return false
      }
      if (nickname.length > 50) {
        this.nicknameError = 'ニックネームは50文字以内で入力してください'
        return false
      }
      if (!/^[a-zA-Z0-9ぁ-んァ-ン一-龠]+$/u.test(nickname)) {
        this.nicknameError = 'ニックネームは英数字、ひらがな、カタカナ、漢字のみ使用できます'
        return false
      }
      this.nicknameError = ''
      return true
    },
    
    validateRegisterEmail() {
      const email = this.registerForm.email
      if (!email) {
        this.registerEmailError = 'メールアドレスは必須です'
        return false
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        this.registerEmailError = '正しいメールアドレス形式で入力してください'
        return false
      }
      const validDomains = ['.com', '.net', '.org', '.jp', '.edu', '.gov']
      if (!validDomains.some(domain => email.endsWith(domain))) {
        this.registerEmailError = '有効なドメインのメールアドレスを入力してください（.com, .net, .org, .jp, .edu, .gov）'
        return false
      }
      this.registerEmailError = ''
      return true
    },
    
    validatePassword() {
      const password = this.registerForm.password
      if (!password) {
        this.passwordError = 'パスワードは必須です'
        return false
      }
      if (password.length < 8) {
        this.passwordError = 'パスワードは8文字以上で入力してください'
        return false
      }
      if (!/[a-zA-Z]/.test(password)) {
        this.passwordError = 'パスワードには英字を含めてください'
        return false
      }
      if (!/\d/.test(password)) {
        this.passwordError = 'パスワードには数字を含めてください'
        return false
      }
      if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        this.passwordError = 'パスワードには記号を含めてください'
        return false
      }
      this.passwordError = ''
      return true
    },
    
    validatePasswordConfirmation() {
      const password = this.registerForm.password
      const confirmation = this.registerForm.password_confirmation
      if (!confirmation) {
        this.passwordConfirmationError = 'パスワード確認は必須です'
        return false
      }
      if (password !== confirmation) {
        this.passwordConfirmationError = 'パスワードが一致しません'
        return false
      }
      this.passwordConfirmationError = ''
      return true
    },
    
    // 認証メソッド
    async login() {
      // フロントエンドバリデーション
      const emailValid = this.validateLoginEmail()
      const passwordValid = this.validateLoginPassword()
      
      if (!emailValid || !passwordValid) {
        return
      }
      
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
      // フロントエンドバリデーション
      const nicknameValid = this.validateNickname()
      const emailValid = this.validateRegisterEmail()
      const passwordValid = this.validatePassword()
      const confirmationValid = this.validatePasswordConfirmation()
      
      if (!nicknameValid || !emailValid || !passwordValid || !confirmationValid) {
        return
      }
      
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
      // Google認証機能は現在未実装のため無効化
      this.showError('Google認証機能は現在準備中です。通常のメールアドレス・パスワードでログインしてください。')
      return
      
      /* 以下は将来の実装用にコメントアウト
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
      */
    },
    
    handleAuthSuccess(data) {
      // トークンをローカルストレージに保存
      localStorage.setItem('auth_token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      
      // Axiosのデフォルトヘッダーにトークンを設定
      axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
      
      // ページリロードして認証状態を更新
      window.location.href = '/dashboard'
    },
    
    showError(message) {
      this.errorMessage = message
    }
  }
}
</script>