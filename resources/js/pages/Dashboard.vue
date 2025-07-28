<template>
  <div>
    <!-- GitHub風草表示 -->
    <section class="rounded-lg shadow p-6 mb-6" style="background-color: white; border: 1px solid var(--color-muted-gray);">
      <StudyGrassChart
        :auto-load="true"
        @dayClick="handleGrassDayClick"
        @dataLoaded="handleGrassDataLoaded"
        @error="handleGrassError"
        class="w-full"
      />
    </section>

    <!-- 現在のセッション状態 -->
    <section v-if="currentSession" class="rounded-lg shadow p-6 mb-6" style="background-color: var(--color-muted-pink-light); border: 1px solid var(--color-muted-pink);">
      <h2 class="text-lg font-semibold mb-4" style="color: var(--color-muted-pink-dark);">🔥 学習中</h2>
      <div class="bg-white rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
          <div>
            <div class="font-bold text-lg">{{ currentSession.subject_area_name }}</div>
            <div class="text-sm text-gray-600">{{ currentSession.exam_type_name }}</div>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold text-red-600">{{ formatElapsedTime(globalStudyTimer.elapsedMinutes) }}</div>
            <div class="text-sm text-gray-600">経過時間</div>
          </div>
        </div>
        <div class="flex gap-2">
          <button 
            @click="endStudySession" 
            :disabled="loading"
            class="flex-1 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200"
            style="background-color: var(--color-muted-pink-dark);"
            onmouseover="this.style.backgroundColor='var(--color-muted-pink)'"
            onmouseout="this.style.backgroundColor='var(--color-muted-pink-dark)'"
          >
            ⏹️ 学習終了
          </button>
        </div>
      </div>
    </section>

    <!-- 試験日カウントダウン -->
    <section v-if="upcomingExams.length > 0" class="rounded-lg shadow p-6 mb-6" style="background-color: white; border: 1px solid var(--color-muted-gray);">
      <h2 class="text-lg font-semibold mb-4" style="color: var(--color-muted-blue-dark);">🎯 試験予定日まで</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="exam in upcomingExams" :key="exam.exam_type_name" class="bg-white rounded-lg p-4">
          <div class="flex justify-between items-center">
            <div>
              <div class="font-bold text-lg" style="color: var(--color-muted-blue-dark);">{{ exam.exam_type_name }}</div>
              <div class="text-sm text-gray-600">{{ formatExamDate(exam.exam_date) }}</div>
            </div>
            <div class="text-right">
              <div class="text-3xl font-bold" :style="{ color: getCountdownColor(exam.days_until_exam) }">
                {{ exam.days_until_exam }}
              </div>
              <div class="text-sm text-gray-600">日</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 今日の学習状況 -->
    <section class="rounded-lg shadow p-6 mb-6" style="background-color: white; border: 1px solid var(--color-muted-gray);">
      <h2 class="text-lg font-semibold mb-4" style="color: var(--color-muted-blue-dark);">📊 今日の学習状況</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 rounded-lg" style="background-color: var(--color-muted-green-light);">
          <div class="text-2xl font-bold" style="color: var(--color-muted-green-dark);">{{ continuousDays }}</div>
          <div class="text-sm text-gray-600">🔥 連続学習日数</div>
        </div>
        <div class="text-center p-4 rounded-lg" style="background-color: var(--color-muted-blue-light);">
          <div class="text-2xl font-bold" style="color: var(--color-muted-blue-dark);">{{ todayStudyTime }}</div>
          <div class="text-sm text-gray-600">⏰ 今日の学習時間</div>
        </div>
        <div class="text-center p-4 rounded-lg" style="background-color: var(--color-muted-purple-light);">
          <div class="text-2xl font-bold" style="color: var(--color-muted-purple-dark);">{{ todaySessionCount }}</div>
          <div class="text-sm text-gray-600">📝 今日のセッション数</div>
        </div>
        <div class="text-center p-4 rounded-lg" style="background-color: var(--color-muted-yellow-light);">
          <div class="text-2xl font-bold" style="color: var(--color-muted-yellow-dark);">{{ achievementRate }}%</div>
          <div class="text-sm text-gray-600">🎯 目標達成率</div>
        </div>
      </div>
    </section>

    <!-- 学習開始セクション & ポモドーロタイマー -->
    <div v-if="!currentSession" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- 学習開始セクション -->
      <section class="bg-white rounded-lg shadow p-6" style="border: 1px solid var(--color-muted-gray);">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--color-muted-blue-dark);">🚀 学習を開始</h2>
      
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
          <label class="block text-sm font-medium mb-2" style="color: var(--color-muted-blue-dark);">学習分野を選択</label>
          <select 
            v-model="selectedSubjectAreaId" 
            required
            class="w-full p-3 rounded-lg"
            style="border: 1px solid var(--color-muted-gray); background-color: white;"
            onfocus="this.style.borderColor='var(--color-muted-blue)'; this.style.boxShadow='0 0 0 2px var(--color-muted-blue-alpha)'"
            onblur="this.style.borderColor='var(--color-muted-gray)'; this.style.boxShadow='none'"
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
          <label class="block text-sm font-medium mb-2" style="color: var(--color-muted-blue-dark);">今日の学習内容</label>
          <textarea 
            v-model="studyComment"
            required
            class="w-full p-3 rounded-lg"
            style="border: 1px solid var(--color-muted-gray); background-color: white;"
            onfocus="this.style.borderColor='var(--color-muted-blue)'; this.style.boxShadow='0 0 0 2px var(--color-muted-blue-alpha)'"
            onblur="this.style.borderColor='var(--color-muted-gray)'; this.style.boxShadow='none'"
            rows="3"
            placeholder="今日学習する内容を簡単に記入してください"
          ></textarea>
        </div>

        <!-- 開始ボタン -->
        <button 
          type="submit" 
          :disabled="loading || !selectedSubjectAreaId || !studyComment.trim()"
          class="w-full text-white font-bold py-3 px-4 rounded-lg transition-colors duration-200"
          :style="{
            backgroundColor: (loading || !selectedSubjectAreaId || !studyComment.trim()) ? 'var(--color-muted-gray)' : 'var(--color-muted-blue)',
            cursor: (loading || !selectedSubjectAreaId || !studyComment.trim()) ? 'not-allowed' : 'pointer'
          }"
          onmouseover="if (!this.disabled) this.style.backgroundColor='var(--color-muted-blue-dark)'"
          onmouseout="if (!this.disabled) this.style.backgroundColor='var(--color-muted-blue)'"
        >
          {{ loading ? '開始中...' : '🎯 学習開始！' }}
        </button>
      </form>
      </section>

      <!-- ポモドーロタイマー -->
      <section class="bg-white rounded-lg shadow p-6" style="border: 1px solid var(--color-muted-gray);">
        <PomodoroTimer />
      </section>
    </div>

    <!-- 最近の学習履歴 -->
    <section class="bg-white rounded-lg shadow p-6 mb-6" style="border: 1px solid var(--color-muted-gray);">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold" style="color: var(--color-muted-blue-dark);">📚 最近の学習履歴</h2>
        <router-link 
          to="/history"
          class="text-sm font-medium transition-colors"
          style="color: var(--color-muted-blue);"
          onmouseover="this.style.color='var(--color-muted-blue-dark)'"
          onmouseout="this.style.color='var(--color-muted-blue)'"
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
        <div v-for="(session, index) in recentSessions" :key="index" class="border rounded-lg p-4 transition-colors" style="border-color: var(--color-muted-gray);" onmouseover="this.style.backgroundColor='var(--color-muted-white)'" onmouseout="this.style.backgroundColor='white'">
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <div class="font-medium">{{ session.subject_area_name }}</div>
                <span v-if="session.type === 'pomodoro_session'" class="px-2 py-1 text-xs rounded-full" style="background-color: var(--color-muted-pink); color: white;">
                  🍅 ポモドーロ
                </span>
                <span v-else class="px-2 py-1 text-xs rounded-full" style="background-color: var(--color-muted-blue-light); color: var(--color-muted-blue-dark);">
                  📚 学習
                </span>
              </div>
              <div v-if="session.exam_type_name" class="text-sm text-gray-600">{{ session.exam_type_name }}</div>
              <div v-if="session.notes" class="text-xs text-gray-500 mt-1 italic">💭 {{ session.notes }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold" style="color: var(--color-muted-blue-dark);">{{ session.duration_minutes }}分</div>
              <div class="text-xs text-gray-500">{{ session.last_studied_at }}</div>
              <button 
                v-if="session.type === 'pomodoro_session'"
                @click="openEditNotesModal(session)"
                class="mt-1 text-xs transition-colors"
                style="color: var(--color-muted-blue);"
                onmouseover="this.style.color='var(--color-muted-blue-dark)'"
                onmouseout="this.style.color='var(--color-muted-blue)'"
                title="メモ編集"
              >
                ✏️ 編集
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ポモドーロメモ編集モーダル -->
    <div v-if="editNotesModal.isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeEditNotesModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4" @click.stop>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold">🍅 ポモドーロメモ編集</h3>
          <button @click="closeEditNotesModal" class="text-gray-500 hover:text-gray-700">
            ✕
          </button>
        </div>
        
        <div class="mb-4">
          <div class="text-sm text-gray-600 mb-2">
            {{ editNotesModal.session?.subject_area_name }} - {{ editNotesModal.session?.duration_minutes }}分
          </div>
          <div class="text-xs text-gray-500">
            {{ editNotesModal.session?.last_studied_at }}
          </div>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium mb-2" style="color: var(--color-muted-blue-dark);">メモ</label>
          <textarea
            v-model="editNotesModal.notes"
            class="w-full p-3 rounded-lg"
            style="border: 1px solid var(--color-muted-gray); background-color: white;"
            onfocus="this.style.borderColor='var(--color-muted-blue)'; this.style.boxShadow='0 0 0 2px var(--color-muted-blue-alpha)'"
            onblur="this.style.borderColor='var(--color-muted-gray)'; this.style.boxShadow='none'"
            rows="4"
            placeholder="ポモドーロセッションでのメモを入力してください..."
          ></textarea>
        </div>
        
        <div class="flex gap-3">
          <button
            @click="closeEditNotesModal"
            class="flex-1 px-4 py-2 rounded-lg transition-colors"
            style="color: var(--color-muted-gray-dark); background-color: var(--color-muted-gray);"
            onmouseover="this.style.backgroundColor='var(--color-muted-gray-dark)'"
            onmouseout="this.style.backgroundColor='var(--color-muted-gray)'"
          >
            キャンセル
          </button>
          <button
            @click="saveNotes"
            :disabled="editNotesModal.saving"
            class="flex-1 px-4 py-2 text-white rounded-lg transition-colors"
            :style="{
              backgroundColor: editNotesModal.saving ? 'var(--color-muted-gray)' : 'var(--color-muted-blue)',
              cursor: editNotesModal.saving ? 'not-allowed' : 'pointer',
              opacity: editNotesModal.saving ? '0.5' : '1'
            }"
            onmouseover="if (!this.disabled) this.style.backgroundColor='var(--color-muted-blue-dark)'"
            onmouseout="if (!this.disabled) this.style.backgroundColor='var(--color-muted-blue)'"
          >
            {{ editNotesModal.saving ? '保存中...' : '保存' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import PomodoroTimer from '../components/PomodoroTimer.vue'
import StudyGrassChart from '../components/StudyGrassChart.vue'

export default {
  name: 'Dashboard',
  inject: ['globalStudyTimer', 'startGlobalStudyTimer', 'stopGlobalStudyTimer'],
  components: {
    PomodoroTimer,
    StudyGrassChart,
  },
  data() {
    return {
      // 統計データ（APIから取得）
      continuousDays: 0,
      todayStudyTime: '0分',
      todaySessionCount: 0,
      achievementRate: 0,
      activeGoals: [],
      
      // API連携用のデータ
      examTypes: [],
      selectedSubjectAreaId: '',
      studyComment: '',
      recentSessions: [],
      
      // ローディング・エラー管理
      loading: false,
      loadingHistory: false,
      loadingDashboard: false,
      errorMessage: '',
      successMessage: '',
      
      // タイマー
      dashboardTimer: null,
      
      // メモ編集モーダル
      editNotesModal: {
        isOpen: false,
        session: null,
        notes: '',
        saving: false
      },
    }
  },
  
  computed: {
    // グローバルタイマーの状態を参照
    currentSession() {
      return this.globalStudyTimer.currentSession
    },
    
    // 試験日が設定されているアクティブな目標を取得
    upcomingExams() {
      return this.activeGoals.filter(goal => goal.days_until_exam !== null && goal.days_until_exam >= 0)
    },
    
    isActive() {
      return this.globalStudyTimer.isActive
    }
  },
  
  async mounted() {
    await this.loadInitialData()
  },
  beforeUnmount() {
    this.clearTimers()
  },
  methods: {
    async loadInitialData() {
      await this.loadExamTypes()
      await this.checkGlobalStudyTimerSync()
      await this.loadDashboardData() // ここで recent_subjects も取得される
      
      // 30秒ごとにダッシュボードデータを更新
      this.dashboardTimer = setInterval(() => {
        this.loadDashboardData()
      }, 30000)
    },
    
    clearTimers() {
      if (this.dashboardTimer) {
        clearInterval(this.dashboardTimer)
        this.dashboardTimer = null
      }
    },

    // 試験タイプと学習分野を取得
    async loadExamTypes() {
      try {
        const response = await axios.get('/api/user/exam-types', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        this.examTypes = response.data.exam_types || []
      } catch (error) {
        console.error('試験タイプ取得エラー:', error)
        this.showError('試験タイプの取得に失敗しました')
      }
    },
    
    // グローバルタイマーとの同期チェック
    async checkGlobalStudyTimerSync() {
      try {
        console.log('ダッシュボード: グローバルタイマー同期チェック')
        const response = await axios.get('/api/study-sessions/current', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        
        if (response.data.success && response.data.session) {
          // API側にアクティブセッションがあり、グローバルタイマーが動いていない場合
          if (!this.globalStudyTimer.isActive) {
            console.log('ダッシュボード: API側セッション発見、グローバルタイマー開始')
            this.startGlobalStudyTimer(response.data.session)
          }
        } else {
          // API側にアクティブセッションがない場合、グローバルタイマーも停止
          if (this.globalStudyTimer.isActive) {
            console.log('ダッシュボード: API側セッションなし、グローバルタイマー停止')
            this.stopGlobalStudyTimer()
          }
        }
      } catch (error) {
        console.error('グローバルタイマー同期チェックエラー:', error)
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
        }, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        
        if (response.data.success) {
          this.showSuccess('学習セッションを開始しました！')
          // グローバルタイマーを開始
          this.startGlobalStudyTimer(response.data.session)
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
        const response = await axios.post('/api/study-sessions/end', {}, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        
        if (response.data.success) {
          this.showSuccess('学習セッションを終了しました！お疲れ様でした！')
          // グローバルタイマーを停止
          this.stopGlobalStudyTimer()
          await this.loadDashboardData() // 履歴も含めて更新
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
    
    // ダッシュボード統計データを取得
    async loadDashboardData() {
      this.loadingDashboard = true
      try {
        const response = await axios.get('/api/dashboard', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        if (response.data.success) {
          const data = response.data.data
          this.continuousDays = data.continuous_days
          this.todayStudyTime = data.today_study_time
          this.todaySessionCount = data.today_session_count
          this.achievementRate = Math.round(data.achievement_rate)
          this.activeGoals = data.active_goals || []
          
          // 最近の学習履歴もダッシュボードAPIから取得するように変更
          this.recentSessions = data.recent_subjects || []
        }
      } catch (error) {
        console.error('ダッシュボードデータ取得エラー:', error)
      } finally {
        this.loadingDashboard = false
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
    
    // 試験日フォーマット（年/月/日）
    formatExamDate(dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      const year = date.getFullYear()
      const month = date.getMonth() + 1
      const day = date.getDate()
      return `${year}年${month}月${day}日`
    },
    
    // カウントダウンの色を決める
    getCountdownColor(daysUntilExam) {
      if (daysUntilExam <= 7) {
        return 'var(--color-muted-pink-dark)' // 1週間以内は赤
      } else if (daysUntilExam <= 30) {
        return 'var(--color-muted-yellow-dark)' // 1ヶ月以内は黄
      } else {
        return 'var(--color-muted-green-dark)' // それ以外は緑
      }
    },
    
    // メモ編集モーダル関連
    openEditNotesModal(session) {
      this.editNotesModal.session = session
      this.editNotesModal.notes = session.notes || ''
      this.editNotesModal.isOpen = true
    },
    
    closeEditNotesModal() {
      this.editNotesModal.isOpen = false
      this.editNotesModal.session = null
      this.editNotesModal.notes = ''
      this.editNotesModal.saving = false
    },
    
    async saveNotes() {
      if (!this.editNotesModal.session) return
      
      this.editNotesModal.saving = true
      
      try {
        const response = await axios.put(`/api/pomodoro/${this.editNotesModal.session.id}`, {
          notes: this.editNotesModal.notes
        }, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        
        if (response.data.success) {
          // リストのデータを更新
          const sessionIndex = this.recentSessions.findIndex(s => 
            s.type === 'pomodoro_session' && s.id === this.editNotesModal.session.id
          )
          if (sessionIndex !== -1) {
            this.recentSessions[sessionIndex].notes = this.editNotesModal.notes
          }
          
          this.showSuccess('メモを保存しました')
          this.closeEditNotesModal()
        }
      } catch (error) {
        console.error('メモ保存エラー:', error)
        this.showError('メモの保存に失敗しました')
      } finally {
        this.editNotesModal.saving = false
      }
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

    // 草表示関連のイベントハンドラー
    handleGrassDayClick(day) {
      console.log('草表示の日付がクリックされました:', day)
      // 特定の日の詳細表示や学習履歴画面への遷移など
      // 今後の機能として実装可能
    },

    handleGrassDataLoaded(grassData) {
      console.log('草表示データが読み込まれました:', grassData)
      // 必要に応じて他の統計データと連携
    },

    handleGrassError(error) {
      console.error('草表示でエラーが発生しました:', error)
      // エラーメッセージの表示は StudyGrassChart コンポーネント内で処理されるため、
      // こちらでは特別な処理は不要
    },

  }
}
</script>