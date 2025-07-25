<template>
  <form @submit.prevent="updateProfile" class="space-y-6">
    <!-- ニックネーム編集 -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        ニックネーム <span class="text-red-500">*</span>
      </label>
      <input
        v-model="form.nickname"
        type="text"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
        :class="{ 'border-red-500 focus:ring-red-500': errors.nickname }"
        required
        maxlength="255"
        placeholder="表示名を入力してください"
      />
      <p v-if="errors.nickname" class="text-red-500 text-sm mt-1">
        {{ errors.nickname[0] }}
      </p>
    </div>

    <!-- メールアドレス編集 -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        メールアドレス <span class="text-red-500">*</span>
      </label>
      <input
        v-model="form.email"
        type="email"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
        :class="{ 'border-red-500 focus:ring-red-500': errors.email }"
        required
        maxlength="255"
        placeholder="メールアドレスを入力してください"
      />
      <p v-if="errors.email" class="text-red-500 text-sm mt-1">
        {{ errors.email[0] }}
      </p>
    </div>

    <!-- パスワード変更セクション -->
    <div v-if="!user.is_google_user" class="border-t pt-6">
      <h3 class="text-lg font-medium text-gray-800 mb-4">パスワード変更</h3>
      
      <div class="space-y-4">
        <!-- 新しいパスワード -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            新しいパスワード（変更する場合のみ）
          </label>
          <input
            v-model="form.password"
            type="password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
            :class="{ 'border-red-500 focus:ring-red-500': errors.password }"
            minlength="8"
            placeholder="8文字以上で入力してください"
          />
          <p v-if="errors.password" class="text-red-500 text-sm mt-1">
            {{ errors.password[0] }}
          </p>
          <p class="text-gray-500 text-xs mt-1">
            ※ パスワードを変更しない場合は空欄のままにしてください
          </p>
        </div>

        <!-- パスワード確認 -->
        <div v-if="form.password">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            パスワード確認 <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
            :class="{ 'border-red-500': passwordMismatch }"
            required
            placeholder="同じパスワードを再入力してください"
          />
          <p v-if="passwordMismatch" class="text-red-500 text-sm mt-1">
            パスワードが一致しません
          </p>
        </div>
      </div>
    </div>

    <!-- Google認証ユーザーの場合のパスワード変更不可メッセージ -->
    <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <div class="text-blue-400 text-lg">🔒</div>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-blue-800">
            Googleアカウント連携中
          </h3>
          <p class="text-blue-700 text-sm mt-1">
            Googleアカウントでログインしているため、パスワード変更はできません。パスワードを変更したい場合は、Googleアカウントの設定で行ってください。
          </p>
        </div>
      </div>
    </div>

    <!-- 送信ボタン -->
    <div class="flex justify-end pt-4">
      <button
        type="submit"
        :disabled="isSubmitting || !isFormValid"
        class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-3 px-6 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
      >
        <span v-if="isSubmitting" class="flex items-center">
          <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          更新中...
        </span>
        <span v-else>
          プロフィール更新
        </span>
      </button>
    </div>
  </form>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ProfileEditForm',
  props: {
    user: {
      type: Object,
      required: true
    }
  },
  emits: ['profile-updated', 'show-message'],
  data() {
    return {
      form: {
        nickname: this.user.nickname || '',
        email: this.user.email || '',
        password: '',
        password_confirmation: ''
      },
      errors: {},
      isSubmitting: false
    }
  },
  computed: {
    passwordMismatch() {
      return this.form.password && 
             this.form.password_confirmation && 
             this.form.password !== this.form.password_confirmation
    },
    
    isFormValid() {
      // 基本フィールドの検証
      const basicValid = this.form.nickname.trim() && this.form.email.trim()
      
      // パスワード変更時の検証
      if (this.form.password) {
        return basicValid && 
               this.form.password.length >= 8 && 
               this.form.password === this.form.password_confirmation
      }
      
      return basicValid
    }
  },
  watch: {
    // propsが変更された場合にフォームを更新
    user: {
      handler(newUser) {
        if (newUser) {
          this.form.nickname = newUser.nickname || ''
          this.form.email = newUser.email || ''
        }
      },
      deep: true,
      immediate: true
    }
  },
  methods: {
    async updateProfile() {
      if (!this.isFormValid) {
        this.$emit('show-message', { type: 'error', message: 'フォームの入力内容を確認してください' })
        return
      }

      this.isSubmitting = true
      this.errors = {}
      
      try {
        const updateData = {
          nickname: this.form.nickname.trim(),
          email: this.form.email.trim()
        }
        
        // パスワードが入力されている場合のみ追加
        if (this.form.password) {
          updateData.password = this.form.password
          updateData.password_confirmation = this.form.password_confirmation
        }
        
        const response = await axios.put('/api/auth/profile', updateData)
        
        if (response.data.success) {
          // 成功時の処理
          this.$emit('profile-updated', response.data.user)
          this.$emit('show-message', { type: 'success', message: 'プロフィールを更新しました' })
          
          // パスワードフィールドをクリア
          this.form.password = ''
          this.form.password_confirmation = ''
          
          // フォームをリセット（最新のユーザー情報で）
          this.form.nickname = response.data.user.nickname
          this.form.email = response.data.user.email
        }
      } catch (error) {
        console.error('Profile update error:', error)
        
        if (error.response?.status === 422) {
          // バリデーションエラー
          this.errors = error.response.data.errors || {}
          this.$emit('show-message', { 
            type: 'error', 
            message: 'フォームの入力内容を確認してください' 
          })
        } else if (error.response?.status === 401) {
          // 認証エラー
          this.$emit('show-message', { 
            type: 'error', 
            message: '認証が切れました。再ログインしてください' 
          })
          // 自動ログアウト処理は親コンポーネントで行う
          setTimeout(() => {
            this.$router.push('/login')
          }, 2000)
        } else if (!error.response) {
          // ネットワークエラー
          this.$emit('show-message', { 
            type: 'error', 
            message: 'ネットワークエラーが発生しました。しばらく経ってから再度お試しください' 
          })
        } else {
          // その他のエラー
          this.$emit('show-message', { 
            type: 'error', 
            message: error.response?.data?.message || 'プロフィールの更新に失敗しました' 
          })
        }
      } finally {
        this.isSubmitting = false
      }
    }
  }
}
</script>

<style scoped>
/* フォーカス時のアニメーション */
input:focus {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

/* 送信ボタンのホバーアニメーション */
button[type="submit"]:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
</style>