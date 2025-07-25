<template>
  <div class="border-2 border-red-200 rounded-lg p-6 bg-red-50">
    <!-- 警告メッセージ -->
    <div class="mb-6">
      <h3 class="text-lg font-semibold text-red-800 mb-3 flex items-center gap-2">
        ⚠️ アカウント完全削除
      </h3>
      <div class="bg-red-100 border border-red-300 rounded-lg p-4 mb-4">
        <p class="text-red-800 font-medium mb-2">
          ⚡ この操作は取り消すことができません
        </p>
        <p class="text-red-700 text-sm mb-3">
          アカウントを削除すると、以下のデータが完全に削除されます：
        </p>
        <ul class="text-red-600 text-sm list-disc list-inside space-y-1 ml-4">
          <li>すべての学習記録・学習セッション</li>
          <li>ポモドーロセッション・タイマー履歴</li>
          <li>学習目標・試験予定日の設定</li>
          <li>学習統計・分析データ</li>
          <li>ユーザー設定・カスタマイズ</li>
          <li>その他すべての個人データ</li>
        </ul>
      </div>
      <p class="text-red-700 text-sm font-medium">
        削除されたデータは復元できません。十分にご検討ください。
      </p>
    </div>

    <!-- 削除確認ステップ -->
    <div v-if="!showConfirmation" class="text-center">
      <button
        @click="showConfirmation = true"
        class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
      >
        アカウント削除の手続きを開始
      </button>
    </div>

    <!-- 削除確認フォーム -->
    <div v-else class="space-y-6">
      <!-- ステップ表示 -->
      <div class="bg-white rounded-lg p-4 border border-red-200">
        <h4 class="font-medium text-red-800 mb-2">削除手続き（3つのステップ）</h4>
        <div class="flex items-center space-x-4 text-sm">
          <div :class="['flex items-center', currentStep >= 1 ? 'text-red-600' : 'text-gray-400']">
            <span class="w-6 h-6 rounded-full border-2 border-current flex items-center justify-center mr-2 text-xs font-bold">1</span>
            認証確認
          </div>
          <div :class="['flex items-center', currentStep >= 2 ? 'text-red-600' : 'text-gray-400']">
            <span class="w-6 h-6 rounded-full border-2 border-current flex items-center justify-center mr-2 text-xs font-bold">2</span>
            削除同意
          </div>
          <div :class="['flex items-center', currentStep >= 3 ? 'text-red-600' : 'text-gray-400']">
            <span class="w-6 h-6 rounded-full border-2 border-current flex items-center justify-center mr-2 text-xs font-bold">3</span>
            削除実行
          </div>
        </div>
      </div>

      <!-- ステップ1: パスワード確認（Googleユーザー以外） -->
      <div v-if="!user.is_google_user" class="bg-white rounded-lg p-4 border border-red-200">
        <label class="block text-sm font-medium text-red-700 mb-3">
          <span class="flex items-center gap-2">
            🔒 削除確認のため、現在のパスワードを入力してください
          </span>
        </label>
        <input
          v-model="password"
          type="password"
          class="w-full border border-red-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent transition-colors"
          :class="{ 'border-red-500': passwordError }"
          placeholder="現在のパスワードを入力"
          required
          @input="passwordError = ''"
        />
        <p v-if="passwordError" class="text-red-600 text-sm mt-2 flex items-center gap-1">
          ❌ {{ passwordError }}
        </p>
      </div>

      <!-- Googleユーザーの場合の認証確認 -->  
      <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
          <div class="text-blue-500 text-lg mr-3">🔐</div>
          <div>
            <h4 class="text-sm font-medium text-blue-800 mb-1">Google認証確認</h4>
            <p class="text-blue-700 text-sm">
              Googleアカウントでログインしているため、パスワード入力は不要です。
            </p>
          </div>
        </div>
      </div>

      <!-- ステップ2: 最終確認チェックボックス -->
      <div class="bg-white rounded-lg p-4 border border-red-200">
        <h4 class="text-sm font-medium text-red-700 mb-3">最終確認</h4>
        <div class="space-y-3">
          <label class="flex items-start cursor-pointer">
            <input
              v-model="confirmations.dataLoss"
              type="checkbox"
              class="mt-1 mr-3 h-4 w-4 text-red-600 focus:ring-red-500 border-red-300 rounded"
              required
            />
            <span class="text-sm text-red-700">
              すべてのデータが完全に削除されることを理解しています
            </span>
          </label>
          
          <label class="flex items-start cursor-pointer">
            <input
              v-model="confirmations.noRestore"
              type="checkbox"
              class="mt-1 mr-3 h-4 w-4 text-red-600 focus:ring-red-500 border-red-300 rounded"
              required
            />
            <span class="text-sm text-red-700">
              削除されたデータは復元できないことを理解しています
            </span>
          </label>
          
          <label class="flex items-start cursor-pointer">
            <input
              v-model="confirmations.finalAgree"
              type="checkbox"
              class="mt-1 mr-3 h-4 w-4 text-red-600 focus:ring-red-500 border-red-300 rounded"
              required
            />
            <span class="text-sm text-red-700 font-medium">
              上記の内容をすべて理解し、アカウント削除に同意します
            </span>
          </label>
        </div>
      </div>

      <!-- ステップ3: 削除実行ボタンエリア -->
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div class="flex flex-col sm:flex-row gap-3 justify-between items-center">
          <div class="text-sm text-gray-600">
            上記をすべて確認してから削除を実行してください
          </div>
          <div class="flex gap-3">
            <button
              @click="cancelDeletion"
              class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
            >
              キャンセル
            </button>
            <button
              @click="deleteAccount"
              :disabled="!canDelete || isDeleting"
              class="bg-red-600 hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-bold py-2 px-6 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
            >
              <span v-if="isDeleting" class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                削除中...
              </span>
              <span v-else>
                🗑️ アカウントを完全に削除
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AccountDeletionForm',
  props: {
    user: {
      type: Object,
      required: true
    }
  },
  emits: ['account-deleted', 'show-message'],
  data() {
    return {
      showConfirmation: false,
      password: '',
      confirmations: {
        dataLoss: false,
        noRestore: false,
        finalAgree: false
      },
      passwordError: '',
      isDeleting: false
    }
  },
  computed: {
    currentStep() {
      if (!this.showConfirmation) return 0
      if (!this.user.is_google_user && !this.password) return 1
      if (!this.allConfirmationsChecked) return 2
      return 3
    },
    
    allConfirmationsChecked() {
      return this.confirmations.dataLoss && 
             this.confirmations.noRestore && 
             this.confirmations.finalAgree
    },
    
    canDelete() {
      const authValid = this.user.is_google_user || this.password.length > 0
      return authValid && this.allConfirmationsChecked
    }
  },
  methods: {
    cancelDeletion() {
      this.showConfirmation = false
      this.password = ''
      this.confirmations = {
        dataLoss: false,
        noRestore: false,
        finalAgree: false
      }
      this.passwordError = ''
    },
    
    async deleteAccount() {
      if (!this.canDelete) {
        this.$emit('show-message', { 
          type: 'error', 
          message: 'すべての確認事項をチェックしてください' 
        })
        return
      }

      // 最終確認ダイアログ
      const finalConfirm = confirm(
        '本当にアカウントを削除しますか？\n\nこの操作は取り消すことができません。\nすべてのデータが完全に削除されます。\n\n削除する場合は「OK」を押してください。'
      )
      
      if (!finalConfirm) {
        return
      }

      this.isDeleting = true
      this.passwordError = ''
      
      try {
        const deleteData = {
          confirmation: '削除します'  // 必須の確認フィールド
        }
        
        // Googleユーザー以外はパスワード確認が必要
        if (!this.user.is_google_user) {
          deleteData.password = this.password
        }
        
        const response = await axios.delete('/api/auth/account', {
          data: deleteData
        })
        
        if (response.data.success) {
          this.$emit('show-message', { 
            type: 'success', 
            message: 'アカウントを削除しました。ご利用ありがとうございました。' 
          })
          
          // 少し遅延させてからログアウト処理を実行
          setTimeout(() => {
            this.$emit('account-deleted')
          }, 2000)
        }
      } catch (error) {
        console.error('Account deletion error:', error)
        
        if (error.response?.status === 422) {
          // バリデーションエラーの詳細チェック
          const errors = error.response.data.errors || {}
          
          if (errors.password) {
            this.passwordError = 'パスワードが間違っています'
            this.$emit('show-message', { 
              type: 'error', 
              message: 'パスワードが間違っています' 
            })
          } else if (errors.confirmation) {
            this.$emit('show-message', { 
              type: 'error', 
              message: '削除確認に失敗しました。画面を更新してやり直してください' 
            })
          } else {
            this.$emit('show-message', { 
              type: 'error', 
              message: '入力内容に問題があります。確認してください' 
            })
          }
        } else if (error.response?.status === 401) {
          // 認証エラー
          this.$emit('show-message', { 
            type: 'error', 
            message: '認証が切れました。再ログインしてください' 
          })
          // 少し遅延させてからログイン画面にリダイレクト
          setTimeout(() => {
            this.$router?.push('/login')
          }, 2000)
        } else if (error.response?.status === 403) {
          // 権限エラー
          this.$emit('show-message', { 
            type: 'error', 
            message: 'アカウント削除の権限がありません' 
          })
        } else if (!error.response) {
          // ネットワークエラー
          this.$emit('show-message', { 
            type: 'error', 
            message: 'ネットワークエラーが発生しました。インターネット接続を確認してください' 
          })
        } else {
          // その他のエラー
          const message = error.response?.data?.message || 'アカウント削除に失敗しました'
          this.$emit('show-message', { 
            type: 'error', 
            message: `削除エラー: ${message}` 
          })
        }
      } finally {
        this.isDeleting = false
      }
    }
  }
}
</script>

<style scoped>
/* チェックボックスのカスタムスタイル */
input[type="checkbox"]:checked {
  background-color: #dc2626;
  border-color: #dc2626;
}

/* 削除ボタンの特別なホバーエフェクト */
button:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* 警告エリアのパルスアニメーション */
@keyframes pulse-red {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}

.border-red-200 {
  animation: pulse-red 3s ease-in-out infinite;
}
</style>