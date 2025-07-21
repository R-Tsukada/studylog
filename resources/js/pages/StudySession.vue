<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4 text-gray-800">🚀 学習セッション</h2>
    
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
            <div class="text-3xl font-bold text-blue-600">{{ formatElapsedTime(currentSession.elapsed_minutes) }}</div>
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
            <option value="">分野を選択してください</option>
            <optgroup v-for="examType in examTypes" :key="examType.id" :label="examType.name">
              <option 
                v-for="subject in examType.subject_areas" 
                :key="subject.id" 
                :value="subject.id"
              >
                {{ subject.name }}
              </option>
            </optgroup>
          </select>
        </div>

        <!-- 学習コメント -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">今日の学習内容</label>
          <textarea 
            v-model="studyComment"
            required
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            rows="4"
            placeholder="今日学習する内容を詳しく記入してください&#10;例：&#10;- 第3章「データベース設計」の復習&#10;- 過去問題集の演習（問題1-10）&#10;- 苦手分野の正規化について理解を深める"
          ></textarea>
        </div>

        <!-- 学習目標時間 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">目標学習時間（分）</label>
          <input 
            type="number" 
            v-model.number="targetMinutes"
            min="5"
            max="480"
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="60"
          />
          <p class="text-xs text-gray-500 mt-1">目標時間は任意です。集中して取り組める時間を設定してください。</p>
        </div>

        <!-- 開始ボタン -->
        <button 
          type="submit" 
          :disabled="loading || !selectedSubjectAreaId || !studyComment.trim()"
          class="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white font-bold py-4 px-4 rounded-lg transition-colors duration-200 text-lg"
        >
          {{ loading ? '開始中...' : '🎯 学習開始！' }}
        </button>
      </form>
    </div>

    <!-- 学習のコツ -->
    <div v-if="!currentSession" class="mt-8 bg-gray-50 rounded-lg p-6">
      <h3 class="text-md font-semibold mb-3 text-gray-800">💡 効果的な学習のコツ</h3>
      <ul class="text-sm text-gray-600 space-y-2">
        <li>• 集中できる環境を整える</li>
        <li>• 25-30分の集中と5分の休憩を繰り返す（ポモドーロテクニック）</li>
        <li>• 学習内容を具体的に記録する</li>
        <li>• 分からない部分は後で調べるためにメモしておく</li>
        <li>• 定期的に振り返りを行う</li>
      </ul>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'StudySession',
  data() {
    return {
      examTypes: [],
      selectedSubjectAreaId: '',
      studyComment: '',
      targetMinutes: 60,
      currentSession: null,
      loading: false,
      errorMessage: '',
      successMessage: '',
      sessionTimer: null
    }
  },
  async mounted() {
    await this.loadExamTypes()
    await this.loadCurrentSession()
    
    // 5秒ごとに現在のセッション状態を更新
    this.sessionTimer = setInterval(() => {
      if (this.currentSession) {
        this.updateCurrentSessionTimer()
      }
    }, 5000)
  },
  beforeUnmount() {
    if (this.sessionTimer) {
      clearInterval(this.sessionTimer)
    }
  },
  methods: {
    async loadExamTypes() {
      try {
        const response = await axios.get('/api/exam-types')
        this.examTypes = response.data
      } catch (error) {
        console.error('試験タイプ取得エラー:', error)
        this.showError('試験タイプの取得に失敗しました')
      }
    },
    
    async loadCurrentSession() {
      try {
        const response = await axios.get('/api/study-sessions/current')
        if (response.data.success && response.data.session) {
          this.currentSession = response.data.session
        }
      } catch (error) {
        console.error('現在セッション取得エラー:', error)
      }
    },
    
    async startStudySession() {
      if (!this.selectedSubjectAreaId || !this.studyComment.trim()) {
        this.showError('学習分野とコメントを入力してください')
        return
      }
      
      this.loading = true
      try {
        const response = await axios.post('/api/study-sessions/start', {
          subject_area_id: this.selectedSubjectAreaId,
          study_comment: this.studyComment
        })
        
        if (response.data.success) {
          this.showSuccess('学習セッションを開始しました！頑張って！')
          this.currentSession = response.data.session
          this.selectedSubjectAreaId = ''
          this.studyComment = ''
          this.targetMinutes = 60
        } else {
          this.showError(response.data.message || '学習開始に失敗しました')
        }
      } catch (error) {
        console.error('学習開始エラー:', error)
        if (error.response?.data?.message) {
          this.showError(error.response.data.message)
        } else {
          this.showError('学習開始中にエラーが発生しました')
        }
      } finally {
        this.loading = false
      }
    },
    
    async endStudySession() {
      this.loading = true
      try {
        const response = await axios.post('/api/study-sessions/end')
        
        if (response.data.success) {
          this.showSuccess('学習セッションを終了しました！お疲れ様でした！')
          this.currentSession = null
          // ダッシュボードに移動
          setTimeout(() => {
            this.$router.push('/dashboard')
          }, 2000)
        } else {
          this.showError(response.data.message || '学習終了に失敗しました')
        }
      } catch (error) {
        console.error('学習終了エラー:', error)
        if (error.response?.data?.message) {
          this.showError(error.response.data.message)
        } else {
          this.showError('学習終了中にエラーが発生しました')
        }
      } finally {
        this.loading = false
      }
    },
    
    updateCurrentSessionTimer() {
      if (this.currentSession) {
        this.currentSession.elapsed_minutes++
        
        // 目標時間に達した場合の通知（ブラウザ通知は実装していないのでコンソールログ）
        if (this.targetMinutes && this.currentSession.elapsed_minutes === this.targetMinutes) {
          console.log('目標時間に達しました！')
        }
      }
    },
    
    formatElapsedTime(minutes) {
      if (!minutes) return '0分'
      
      const hours = Math.floor(minutes / 60)
      const mins = minutes % 60
      
      if (hours > 0) {
        return `${hours}時間${mins}分`
      } else {
        return `${mins}分`
      }
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
    }
  }
}
</script>