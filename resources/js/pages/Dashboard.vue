<template>
  <div>
    <!-- モック環境お知らせ（本番環境のみ） -->
    <div v-if="showMockNotice" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <span class="text-yellow-600 text-xl">🎭</span>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-yellow-800">デモ環境で動作中</h3>
          <div class="mt-2 text-sm text-yellow-700">
            <p>現在はモックデータで動作しています。実際の学習データの保存・管理機能は開発中です。</p>
            <div class="mt-2">
              <button @click="dismissMockNotice" class="text-yellow-800 underline hover:text-yellow-900">
                このメッセージを非表示
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 現在のセッション状態 -->
    <section v-if="currentSession" class="bg-red-50 border border-red-200 rounded-lg shadow p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4 text-red-800">🔥 学習中</h2>
      <div class="bg-white rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
          <div>
            <div class="font-bold text-lg">{{ currentSession.subject_area_name }}</div>
            <div class="text-sm text-gray-600">{{ currentSession.exam_type_name }}</div>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold text-red-600">{{ formatElapsedTime(currentSession.elapsed_minutes) }}</div>
            <div class="text-sm text-gray-600">経過時間</div>
          </div>
        </div>
        <div class="flex gap-2">
          <button 
            @click="endStudySession" 
            :disabled="loading"
            class="flex-1 bg-red-500 hover:bg-red-600 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200"
          >
            ⏹️ 学習終了
          </button>
        </div>
      </div>
    </section>

    <!-- 今日の学習状況 -->
    <section class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4 text-gray-800">📊 今日の学習状況</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 bg-green-50 rounded-lg">
          <div class="text-2xl font-bold text-green-600">{{ continuousDays }}</div>
          <div class="text-sm text-gray-600">🔥 連続学習日数</div>
        </div>
        <div class="text-center p-4 bg-blue-50 rounded-lg">
          <div class="text-2xl font-bold text-blue-600">{{ todayStudyTime }}</div>
          <div class="text-sm text-gray-600">⏰ 今日の学習時間</div>
        </div>
        <div class="text-center p-4 bg-purple-50 rounded-lg">
          <div class="text-2xl font-bold text-purple-600">{{ todaySessionCount }}</div>
          <div class="text-sm text-gray-600">📝 今日のセッション数</div>
        </div>
        <div class="text-center p-4 bg-yellow-50 rounded-lg">
          <div class="text-2xl font-bold text-yellow-600">{{ achievementRate }}%</div>
          <div class="text-sm text-gray-600">🎯 目標達成率</div>
        </div>
      </div>
    </section>

    <!-- 学習開始セクション -->
    <section v-if="!currentSession" class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4 text-gray-800">🚀 学習を開始</h2>
      
      <!-- エラーメッセージ -->
      <div v-if="errorMessage" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        {{ errorMessage }}
      </div>
      
      <!-- 成功メッセージ -->
      <div v-if="successMessage" class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        {{ successMessage }}
      </div>
      
      <form @submit.prevent="startStudySession" class="space-y-4">
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
            rows="3"
            placeholder="今日学習する内容を簡単に記入してください"
          ></textarea>
        </div>

        <!-- 開始ボタン -->
        <button 
          type="submit" 
          :disabled="loading || !selectedSubjectAreaId || !studyComment.trim()"
          class="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition-colors duration-200"
        >
          {{ loading ? '開始中...' : '🎯 学習開始！' }}
        </button>
      </form>
    </section>

    <!-- 学習カレンダー -->
    <StudyCalendar />

    <!-- 最近の学習履歴 -->
    <section class="bg-white rounded-lg shadow p-6 mb-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">📚 最近の学習履歴</h2>
        <router-link 
          to="/history"
          class="text-blue-600 hover:text-blue-800 text-sm font-medium"
        >
          📋 すべて見る →
        </router-link>
      </div>
      
      <div v-if="loadingHistory" class="text-center py-8">
        <div class="text-gray-500">履歴を読み込み中...</div>
      </div>
      
      <div v-else-if="recentSessions.length === 0" class="text-center py-8">
        <div class="text-gray-500">まだ学習履歴がありません</div>
      </div>
      
      <div v-else class="space-y-3">
        <div v-for="session in recentSessions" :key="session.id" class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="font-medium">{{ session.subject_area_name }}</div>
              <div class="text-sm text-gray-600">{{ session.exam_type_name }}</div>
              <div class="text-xs text-gray-500 mt-1">{{ session.study_comment }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-blue-600">{{ session.duration_minutes }}分</div>
              <div class="text-xs text-gray-500">{{ formatDate(session.date) }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import { apiService } from '../services/apiService.js'
import StudyCalendar from '../components/StudyCalendar.vue'

export default {
  name: 'Dashboard',
  components: {
    StudyCalendar
  },
  data() {
    return {
      // 統計データ（APIから取得）
      continuousDays: 0,
      todayStudyTime: '0分',
      todaySessionCount: 0,
      achievementRate: 0,
      
      // API連携用のデータ
      examTypes: [],
      selectedSubjectAreaId: '',
      studyComment: '',
      currentSession: null,
      recentSessions: [],
      
      // ローディング・エラー管理
      loading: false,
      loadingHistory: false,
      loadingDashboard: false,
      errorMessage: '',
      successMessage: '',
      
      // タイマー
      sessionTimer: null,
      dashboardTimer: null,
      
      // モック環境通知
      showMockNotice: false,
    }
  },
  async mounted() {
    // モック環境通知を表示（初回のみ）
    if (apiService.mockMode && !localStorage.getItem('mockNoticeDismissed')) {
      this.showMockNotice = true
    }
    await this.loadInitialData()
  },
  beforeUnmount() {
    this.clearTimers()
  },
  methods: {
    async loadInitialData() {
      await this.loadExamTypes()
      await this.loadCurrentSession()
      await this.loadStudyHistory()
      await this.loadDashboardData()
      
      // 5秒ごとに現在のセッション状態を更新
      this.sessionTimer = setInterval(() => {
        if (this.currentSession) {
          this.updateCurrentSessionTimer()
        }
      }, 5000)
      
      // 30秒ごとにダッシュボードデータを更新
      this.dashboardTimer = setInterval(() => {
        this.loadDashboardData()
      }, 30000)
    },
    
    clearTimers() {
      if (this.sessionTimer) {
        clearInterval(this.sessionTimer)
        this.sessionTimer = null
      }
      if (this.dashboardTimer) {
        clearInterval(this.dashboardTimer)
        this.dashboardTimer = null
      }
    },

    // 試験タイプと学習分野を取得
    async loadExamTypes() {
      try {
        const response = await apiService.getExamTypes()
        this.examTypes = response.data
      } catch (error) {
        console.error('試験タイプ取得エラー:', error)
        this.showError('試験タイプの取得に失敗しました')
      }
    },
    
    // 現在のセッション状態を取得
    async loadCurrentSession() {
      try {
        const response = await apiService.getCurrentSession()
        if (response.data.success && response.data.session) {
          this.currentSession = response.data.session
        }
      } catch (error) {
        console.error('現在セッション取得エラー:', error)
      }
    },
    
    // 学習セッション開始
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
          this.showSuccess('学習セッションを開始しました！')
          this.currentSession = response.data.session
          this.selectedSubjectAreaId = ''
          this.studyComment = ''
          await this.loadDashboardData()
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
    
    // 学習セッション終了
    async endStudySession() {
      this.loading = true
      try {
        const response = await axios.post('/api/study-sessions/end')
        
        if (response.data.success) {
          this.showSuccess('学習セッションを終了しました！お疲れ様でした！')
          this.currentSession = null
          await this.loadStudyHistory()
          await this.loadDashboardData()
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
    
    // 学習履歴を取得
    async loadStudyHistory() {
      this.loadingHistory = true
      try {
        const response = await apiService.getStudyHistory({ limit: 5 })
        if (response.data.success) {
          this.recentSessions = response.data.history
        }
      } catch (error) {
        console.error('学習履歴取得エラー:', error)
      } finally {
        this.loadingHistory = false
      }
    },
    
    // ダッシュボード統計データを取得
    async loadDashboardData() {
      this.loadingDashboard = true
      try {
        const response = await apiService.getDashboardData()
        if (response.data.success) {
          const data = response.data.data
          this.continuousDays = data.continuous_days
          this.todayStudyTime = data.today_study_time
          this.todaySessionCount = data.today_session_count
          this.achievementRate = Math.round(data.achievement_rate)
        }
      } catch (error) {
        console.error('ダッシュボードデータ取得エラー:', error)
      } finally {
        this.loadingDashboard = false
      }
    },
    
    // 現在のセッションタイマーを更新
    updateCurrentSessionTimer() {
      if (this.currentSession) {
        this.currentSession.elapsed_minutes++
      }
    },
    
    // 時間フォーマット
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
    
    // 日付フォーマット
    formatDate(dateString) {
      const date = new Date(dateString)
      return `${date.getMonth() + 1}/${date.getDate()}`
    },
    
    // エラーメッセージ表示
    showError(message) {
      this.errorMessage = message
      this.successMessage = ''
      setTimeout(() => {
        this.errorMessage = ''
      }, 5000)
    },
    
    // 成功メッセージ表示
    showSuccess(message) {
      this.successMessage = message
      this.errorMessage = ''
      setTimeout(() => {
        this.successMessage = ''
      }, 5000)
    },
    
    // モック環境通知を非表示
    dismissMockNotice() {
      this.showMockNotice = false
      localStorage.setItem('mockNoticeDismissed', 'true')
    }
  }
}
</script>