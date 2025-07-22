<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4 text-gray-800">⏰ 時間計測</h2>
    <p class="text-sm text-gray-600 mb-6">自由な時間で学習を計測できます。長時間の学習や読書に最適です。</p>
    
    <!-- 現在のセッション -->
    <div v-if="currentSession" class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4 text-blue-800">🔥 学習中</h3>
      <div class="bg-white rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
          <div>
            <div class="font-bold text-lg">{{ currentSession.subject_area_name }}</div>
            <div class="text-sm text-gray-600">{{ currentSession.exam_type_name }}</div>
            <div class="text-sm text-gray-700 mt-2">{{ currentSession.study_comment }}</div>
          </div>
          <div class="text-right">
            <div class="text-3xl font-bold text-blue-600">{{ formatElapsedTime(globalStudyTimer.elapsedMinutes) }}</div>
            <div class="text-sm text-gray-600">経過時間</div>
          </div>
        </div>
        <button 
          @click="endStudySession" 
          :disabled="loading"
          class="w-full bg-red-500 hover:bg-red-600 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors duration-200"
        >
          ⏹️ 学習終了
        </button>
      </div>
    </div>

    <!-- 学習開始フォーム -->
    <div v-else>
      <!-- エラーメッセージ -->
      <div v-if="errorMessage" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        {{ errorMessage }}
      </div>
      
      <!-- 成功メッセージ -->
      <div v-if="successMessage" class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        {{ successMessage }}
      </div>
      
      <form @submit.prevent="startStudySession" class="space-y-6">
        <!-- 学習分野選択 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">学習分野を選択</label>
          <select 
            v-model="selectedSubjectAreaId" 
            required
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">学習分野を選択してください</option>
            <option 
              v-for="area in subjectAreas" 
              :key="area.id" 
              :value="area.id"
            >
              {{ area.exam_type?.name }} - {{ area.name }}
            </option>
          </select>
        </div>

        <!-- 学習コメント -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">今日の学習内容</label>
          <textarea
            v-model="studyComment"
            required
            rows="3"
            placeholder="例：第3章の復習、過去問演習など"
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- 開始ボタン -->
        <button 
          type="submit" 
          :disabled="loading || !selectedSubjectAreaId || !studyComment.trim()"
          class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors duration-200"
        >
          <span v-if="loading">開始中...</span>
          <span v-else>🚀 学習開始</span>
        </button>
      </form>

      <!-- ポモドーロ紹介 -->
      <div class="mt-8 p-4 bg-red-50 border border-red-200 rounded-lg">
        <h4 class="font-semibold text-red-800 mb-2">🍅 集中力を高めたい方に</h4>
        <p class="text-sm text-red-700 mb-3">
          25分間の集中セッションと休憩を組み合わせたポモドーロテクニックもお試しください。
        </p>
        <router-link 
          to="/pomodoro" 
          class="inline-block bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-2 rounded transition-colors"
        >
          ポモドーロを試す
        </router-link>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StudySession',
  inject: ['globalStudyTimer', 'startGlobalStudyTimer', 'stopGlobalStudyTimer'],
  data() {
    return {
      // フォーム
      selectedSubjectAreaId: '',
      studyComment: '',
      
      // 状態
      subjectAreas: [],
      loading: false,
      
      // メッセージ
      errorMessage: '',
      successMessage: ''
    }
  },
  
  computed: {
    // グローバルタイマーの状態を参照
    currentSession() {
      return this.globalStudyTimer.currentSession
    },
    
    isActive() {
      return this.globalStudyTimer.isActive
    }
  },
  
  async mounted() {
    await this.loadSubjectAreas()
    await this.checkCurrentSession()
  },
  
  methods: {
    async loadSubjectAreas() {
      try {
        const response = await fetch('/api/user/subject-areas', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        })
        
        if (response.ok) {
          const data = await response.json()
          this.subjectAreas = data.subject_areas || []
        } else {
          this.showError('学習分野の取得に失敗しました')
        }
      } catch (error) {
        console.error('学習分野取得エラー:', error)
        this.showError('学習分野の取得に失敗しました')
      }
    },
    
    async checkCurrentSession() {
      try {
        console.log('現在の時間計測セッション確認開始...')
        const response = await fetch('/api/study-sessions/current', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        })
        
        if (response.ok) {
          const data = await response.json()
          console.log('API側の現在の時間計測セッション:', data)
          
          if (data.success && data.session) {
            // グローバルタイマーが動いていない場合、API側のセッションでタイマーを開始
            if (!this.globalStudyTimer.isActive) {
              console.log('API側にアクティブセッション発見、グローバルタイマーを開始')
              this.startGlobalStudyTimer(data.session)
            }
          } else {
            // API側にアクティブセッションがない場合、グローバルタイマーも停止
            if (this.globalStudyTimer.isActive) {
              console.log('API側にセッションなし、グローバルタイマーを停止')
              this.stopGlobalStudyTimer()
            }
          }
        } else if (response.status === 404) {
          console.log('アクティブな時間計測セッションなし')
          // グローバルタイマーも停止
          if (this.globalStudyTimer.isActive) {
            this.stopGlobalStudyTimer()
          }
        } else {
          const errorData = await response.json()
          console.error('時間計測セッション取得エラー:', errorData)
        }
      } catch (error) {
        console.error('現在のセッション取得エラー:', error)
      }
    },
    
    async startStudySession() {
      if (this.loading) return
      
      this.loading = true
      this.clearMessages()
      
      try {
        const response = await fetch('/api/study-sessions/start', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            subject_area_id: this.selectedSubjectAreaId,
            study_comment: this.studyComment
          })
        })
        
        const data = await response.json()
        
        if (response.ok && data.success) {
          // グローバルタイマーを開始
          this.startGlobalStudyTimer(data.session)
          this.showSuccess('学習セッションを開始しました！')
          this.resetForm()
        } else {
          this.showError(data.message || '学習セッションの開始に失敗しました')
        }
      } catch (error) {
        console.error('学習セッション開始エラー:', error)
        this.showError('学習セッションの開始に失敗しました')
      } finally {
        this.loading = false
      }
    },
    
    async endStudySession() {
      if (this.loading || !this.currentSession) return
      
      if (!confirm('学習セッションを終了しますか？')) return
      
      this.loading = true
      this.clearMessages()
      
      try {
        const response = await fetch('/api/study-sessions/end', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            study_comment: this.currentSession.study_comment
          })
        })
        
        const data = await response.json()
        
        if (response.ok && data.success) {
          const duration = this.formatElapsedTime(this.globalStudyTimer.elapsedMinutes)
          this.showSuccess(`学習セッションを終了しました！学習時間: ${duration}`)
          // グローバルタイマーを停止
          this.stopGlobalStudyTimer()
        } else {
          this.showError(data.message || '学習セッション終了に失敗しました')
        }
      } catch (error) {
        console.error('学習セッション終了エラー:', error)
        this.showError('学習セッション終了に失敗しました')
      } finally {
        this.loading = false
      }
    },
    
    formatElapsedTime(minutes) {
      // 入力値を整数に変換し、負の値を0にする
      const totalMinutes = Math.max(0, Math.floor(Number(minutes) || 0))
      const hours = Math.floor(totalMinutes / 60)
      const mins = totalMinutes % 60
      
      if (hours > 0) {
        return `${hours}時間${mins}分`
      } else {
        return `${mins}分`
      }
    },
    
    resetForm() {
      this.selectedSubjectAreaId = ''
      this.studyComment = ''
    },
    
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
    
    clearMessages() {
      this.errorMessage = ''
      this.successMessage = ''
    }
  }
}
</script>

<style scoped>
/* 学習中セッションのパルスアニメーション */
.bg-blue-50 {
  animation: pulse-subtle 2s infinite;
}

@keyframes pulse-subtle {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.01); }
}
</style>