<template>
  <div class="space-y-6">
    <!-- ページヘッダー -->
    <div class="bg-white rounded-lg shadow p-6">
      <div class="flex items-center gap-4">
        <!-- ユーザーアバター -->
        <div class="relative">
          <img 
            v-if="user.avatar_url" 
            :src="user.avatar_url" 
            :alt="user.nickname"
            class="w-16 h-16 rounded-full object-cover border-2 border-gray-200"
            @error="handleImageError"
          />
          <div 
            v-else 
            class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-2xl font-bold text-white border-2 border-gray-200"
          >
            {{ user.nickname.charAt(0).toUpperCase() }}
          </div>
        </div>
        
        <!-- ユーザー情報 -->
        <div class="flex-1">
          <h1 class="text-2xl font-bold text-gray-800 mb-1">{{ user.nickname }}</h1>
          <p class="text-gray-600 mb-1">{{ user.email }}</p>
          <p class="text-sm text-gray-500">
            登録日: {{ formatDate(user.created_at) }}
            <span v-if="user.is_google_user" class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              Google連携
            </span>
          </p>
        </div>
      </div>
    </div>

    <!-- エラー・成功メッセージ -->
    <div v-if="message.text" 
         :class="message.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
         class="border rounded-lg p-4"
    >
      {{ message.text }}
    </div>

    <!-- プロフィール編集セクション -->
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
        🔧 プロフィール編集
      </h2>
      
      <ProfileEditForm 
        :user="user"
        @profile-updated="handleProfileUpdate"
        @show-message="showMessage"
      />
    </div>

    <!-- アカウント削除セクション -->
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-xl font-semibold mb-4 text-red-600 flex items-center gap-2">
        ⚠️ 危険な操作
      </h2>
      
      <AccountDeletionForm 
        :user="user"
        @account-deleted="handleAccountDeletion"
        @show-message="showMessage"
      />
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import ProfileEditForm from '../components/ProfileEditForm.vue'
import AccountDeletionForm from '../components/AccountDeletionForm.vue'

export default {
  name: 'MyPage',
  components: {
    ProfileEditForm,
    AccountDeletionForm
  },
  data() {
    return {
      user: {},
      message: {
        type: '',
        text: ''
      }
    }
  },
  async mounted() {
    await this.loadUserData()
  },
  methods: {
    async loadUserData() {
      try {
        // 親コンポーネント（App.vue）からユーザー情報を取得
        const userDataString = localStorage.getItem('user')
        if (userDataString) {
          this.user = JSON.parse(userDataString)
        } else {
          // localStorage にない場合は API から取得
          const response = await axios.get('/api/user')
          if (response.data.success) {
            this.user = response.data.user
          }
        }
      } catch (error) {
        console.error('ユーザー情報取得エラー:', error)
        this.showMessage({ type: 'error', message: 'ユーザー情報の取得に失敗しました' })
      }
    },

    handleProfileUpdate(updatedUser) {
      // ユーザー情報を更新
      this.user = { ...this.user, ...updatedUser }
      
      // localStorage も更新
      localStorage.setItem('user', JSON.stringify(this.user))
      
      // 親コンポーネントにも反映（グローバル状態更新）
      this.$parent.user = this.user
    },

    handleAccountDeletion() {
      // アカウント削除後は自動的にログアウト処理が実行される
      // AuthController で tokens().delete() と user.delete() が実行される
      this.$router.push('/login')
    },

    showMessage({ type, message }) {
      this.message = { type, text: message }
      
      // 5秒後にメッセージを自動で消す
      setTimeout(() => {
        this.message = { type: '', text: '' }
      }, 5000)
    },

    formatDate(dateString) {
      if (!dateString) return ''
      
      try {
        const date = new Date(dateString)
        return date.toLocaleDateString('ja-JP', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        })
      } catch (error) {
        console.error('日付フォーマットエラー:', error)
        return dateString
      }
    },

    handleImageError(event) {
      // 画像読み込みエラー時はアバターを非表示にする
      event.target.style.display = 'none'
    }
  }
}
</script>

<style scoped>
/* コンポーネント固有のスタイル */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>