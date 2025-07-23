<template>
  <div>
    <!-- 設定ページヘッダー -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 mb-2">⚙️ 設定</h2>
      <p class="text-gray-600">試験予定日、学習分野、学習目標の順番で設定することをお勧めします</p>
    </div>

    <!-- 試験予定日管理 -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-800">📅 試験予定日の管理</h3>
        <button 
          @click="showAddExamModal = true"
          class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg text-sm"
        >
          + 試験追加
        </button>
      </div>

      <div v-if="loadingExams" class="text-center py-8">
        <div class="text-gray-500">読み込み中...</div>
      </div>

      <div v-else-if="userExamTypes.length === 0" class="text-center py-8">
        <div class="text-gray-500 mb-4">まだ試験が登録されていません</div>
        <button 
          @click="showAddExamModal = true"
          class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg"
        >
          📅 最初の試験を追加
        </button>
      </div>

      <div v-else class="space-y-4">
        <div v-for="exam in userExamTypes" :key="exam.id" class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <div 
                  class="w-4 h-4 rounded-full"
                  :style="{ backgroundColor: exam.color }"
                ></div>
                <div class="font-medium text-lg">{{ exam.name }}</div>
                <div v-if="exam.exam_date" class="text-sm">
                  <span :class="getDaysUntilExam(exam.exam_date) <= 7 ? 'text-red-600 font-bold' : 'text-gray-600'">
                    {{ formatExamDate(exam.exam_date) }}
                  </span>
                </div>
              </div>
              <div class="text-sm text-gray-600 mb-1">{{ exam.description }}</div>
              <div v-if="exam.exam_notes" class="text-sm text-gray-500">📝 {{ exam.exam_notes }}</div>
            </div>
            <div class="flex gap-2">
              <button 
                @click="editExam(exam)"
                class="text-blue-600 hover:text-blue-800 text-sm"
              >
                ✏️ 編集
              </button>
              <button 
                @click="deleteExam(exam)"
                class="text-red-600 hover:text-red-800 text-sm"
              >
                🗑️ 削除
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 学習分野管理 -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-800">📚 学習分野の管理</h3>
        <button 
          @click="showAddSubjectModal = true"
          class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg text-sm"
        >
          + 新規追加
        </button>
      </div>

      <div v-if="loadingSubjects" class="text-center py-8">
        <div class="text-gray-500">読み込み中...</div>
      </div>

      <div v-else-if="userSubjects.length === 0" class="text-center py-8">
        <div class="text-gray-500 mb-4">まだ学習分野が登録されていません</div>
        <button 
          @click="showAddSubjectModal = true"
          class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg"
        >
          📚 最初の分野を追加
        </button>
      </div>

      <div v-else class="space-y-3">
        <div v-for="subject in userSubjects" :key="subject.id" class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex justify-between items-center">
            <div>
              <div class="font-medium">{{ subject.name }}</div>
              <div class="text-sm text-gray-600">{{ subject.exam_type_name }}</div>
            </div>
            <div class="flex gap-2">
              <button 
                @click="editSubject(subject)"
                class="text-blue-600 hover:text-blue-800 text-sm"
              >
                ✏️ 編集
              </button>
              <button 
                @click="deleteSubject(subject)"
                class="text-red-600 hover:text-red-800 text-sm"
              >
                🗑️ 削除
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 学習目標設定 -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">🎯 学習目標設定</h3>
      
      <!-- 現在のアクティブ目標表示 -->
      <div v-if="activeGoal && !editGoalMode" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <h4 class="font-medium text-blue-800 mb-2">現在の目標</h4>
        <div class="text-sm text-blue-700 space-y-1">
          <p><strong>日次目標:</strong> {{ activeGoal.daily_minutes_goal }}分 ({{ formatHours(activeGoal.daily_minutes_goal) }})</p>
          <p v-if="activeGoal.weekly_minutes_goal"><strong>週次目標:</strong> {{ activeGoal.weekly_minutes_goal }}分 ({{ formatHours(activeGoal.weekly_minutes_goal) }})</p>
          <p v-if="activeGoal.exam_type_name"><strong>対象試験:</strong> {{ activeGoal.exam_type_name }}</p>
          <p v-if="activeGoal.exam_date"><strong>試験日:</strong> {{ formatDate(activeGoal.exam_date) }}</p>
        </div>
        <button 
          @click="editGoalMode = true"
          class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium"
        >
          ✏️ 目標を編集
        </button>
      </div>

      <!-- 目標設定フォーム -->
      <div v-if="!activeGoal || editGoalMode" class="space-y-4">
        <form @submit.prevent="saveGoal">
          <!-- 日次目標時間 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              日次目標時間 <span class="text-red-500">*</span>
            </label>
            <div class="flex items-center space-x-2">
              <input
                v-model.number="goalForm.daily_minutes_goal"
                type="number"
                min="1"
                max="1440"
                required
                class="w-24 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <span class="text-sm text-gray-600">分/日</span>
              <span class="text-xs text-gray-500">
                ({{ formatHours(goalForm.daily_minutes_goal) }})
              </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
              推奨: 平日30-120分、休日60-240分
            </p>
          </div>

          <!-- 週次目標時間（オプション） -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              週次目標時間（オプション）
            </label>
            <div class="flex items-center space-x-2">
              <input
                v-model.number="goalForm.weekly_minutes_goal"
                type="number"
                min="1"
                max="10080"
                class="w-32 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <span class="text-sm text-gray-600">分/週</span>
              <span class="text-xs text-gray-500">
                ({{ formatHours(goalForm.weekly_minutes_goal) }})
              </span>
            </div>
          </div>

          <!-- 対象試験選択 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              対象試験（オプション）
            </label>
            <select
              v-model="goalForm.exam_type_id"
              class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">試験を選択してください</option>
              <option
                v-for="examType in userExamTypes"
                :key="examType.id"
                :value="examType.id"
              >
                {{ examType.name }}
              </option>
            </select>
          </div>

          <!-- 試験日 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              試験日（オプション）
            </label>
            <input
              v-model="goalForm.exam_date"
              type="date"
              :min="tomorrow"
              class="p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- ボタン -->
          <div class="flex gap-3 pt-4">
            <button
              type="submit"
              :disabled="loadingGoal || !goalForm.daily_minutes_goal"
              class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white font-medium py-2 px-6 rounded-lg transition-colors"
            >
              {{ loadingGoal ? '保存中...' : activeGoal ? '目標を更新' : '目標を設定' }}
            </button>
            <button
              v-if="editGoalMode"
              type="button"
              @click="cancelGoalEdit"
              class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
            >
              キャンセル
            </button>
            <button
              v-if="activeGoal && editGoalMode"
              type="button"
              @click="deleteGoal"
              class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition-colors"
            >
              目標を削除
            </button>
          </div>
        </form>
      </div>

      <!-- 目標設定の説明 -->
      <div class="mt-6 p-4 bg-gray-50 rounded-lg">
        <h4 class="font-medium text-gray-800 mb-2">💡 目標設定のコツ</h4>
        <ul class="text-sm text-gray-600 space-y-1">
          <li>• 毎日継続できる現実的な時間を設定しましょう</li>
          <li>• 学習セッションとポモドーロタイマーの両方の時間がカウントされます</li>
          <li>• 目標達成率はダッシュボードで確認できます</li>
          <li>• 試験日を設定すると残り日数が表示されます</li>
        </ul>
      </div>
    </div>

    <!-- 学習分野追加・編集モーダル -->
    <div v-if="showAddSubjectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-lg max-w-md w-full">
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-4">{{ editingSubject ? '📝 学習分野を編集' : '📚 新しい学習分野を追加' }}</h3>
          
          <form @submit.prevent="saveSubject">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">試験タイプ</label>
              <select 
                v-model="subjectForm.exam_type_id" 
                required
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">試験タイプを選択</option>
                <option v-for="exam in userExamTypes" :key="exam.id" :value="exam.id">
                  {{ exam.name }}
                </option>
              </select>
            </div>

            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">分野名</label>
              <input 
                type="text" 
                v-model="subjectForm.name"
                required
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="例：データベース設計、アルゴリズム など"
              />
            </div>

            <div class="flex gap-3">
              <button 
                type="submit" 
                :disabled="loading"
                class="flex-1 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded-lg"
              >
                {{ editingSubject ? '💾 更新' : '📚 追加' }}
              </button>
              <button 
                type="button"
                @click="cancelSubjectEdit"
                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg"
              >
                キャンセル
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- 試験追加・編集モーダル -->
    <div v-if="showAddExamModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-lg max-w-md w-full max-h-96 overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-4">{{ editingExam ? '📝 試験情報を編集' : '📅 新しい試験を追加' }}</h3>
          
          <form @submit.prevent="saveExam">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">試験名</label>
              <input 
                type="text" 
                v-model="examForm.name"
                required
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="例：基本情報技術者試験、JSTQB Foundation Level など"
              />
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">説明</label>
              <textarea 
                v-model="examForm.description"
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                rows="2"
                placeholder="試験の概要や目的など"
              ></textarea>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">試験予定日</label>
              <input 
                type="date" 
                v-model="examForm.exam_date"
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">テーマカラー</label>
              <div class="flex items-center gap-2">
                <input 
                  type="color" 
                  v-model="examForm.color"
                  class="w-12 h-10 border border-gray-300 rounded cursor-pointer"
                />
                <span class="text-sm text-gray-600">{{ examForm.color }}</span>
              </div>
            </div>

            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">メモ</label>
              <textarea 
                v-model="examForm.exam_notes"
                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                rows="3"
                placeholder="学習計画や注意事項など"
              ></textarea>
            </div>

            <div class="flex gap-3">
              <button 
                type="submit" 
                :disabled="loading"
                class="flex-1 bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded-lg"
              >
                {{ editingExam ? '💾 更新' : '📅 追加' }}
              </button>
              <button 
                type="button"
                @click="cancelExamEdit"
                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg"
              >
                キャンセル
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- エラーメッセージ -->
    <div v-if="errorMessage" class="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg z-50">
      {{ errorMessage }}
    </div>

    <!-- 成功メッセージ -->
    <div v-if="successMessage" class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg z-50">
      {{ successMessage }}
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'Settings',
  data() {
    return {
      // データ
      userExamTypes: [],
      userSubjects: [],
      activeGoal: null,
      
      // ローディング状態
      loading: false,
      loadingExams: false,
      loadingSubjects: false,
      loadingGoal: false,
      
      // モーダル表示状態
      showAddExamModal: false,
      showAddSubjectModal: false,
      editGoalMode: false,
      
      // 編集中のデータ
      editingExam: null,
      editingSubject: null,
      
      // フォームデータ
      examForm: {
        name: '',
        description: '',
        exam_date: '',
        exam_notes: '',
        color: '#3B82F6'
      },
      subjectForm: {
        exam_type_id: '',
        name: ''
      },
      goalForm: {
        exam_type_id: '',
        daily_minutes_goal: 60, // デフォルト1時間
        weekly_minutes_goal: null,
        exam_date: '',
        is_active: true
      },
      
      // メッセージ
      errorMessage: '',
      successMessage: ''
    }
  },
  
  computed: {
    tomorrow() {
      const date = new Date()
      date.setDate(date.getDate() + 1)
      return date.toISOString().split('T')[0]
    }
  },
  async mounted() {
    await this.loadUserExamTypes()
    await this.loadUserSubjects()
    await this.loadActiveGoal()
  },
  methods: {
    async loadUserExamTypes() {
      this.loadingExams = true
      try {
        const response = await axios.get('/api/user/exam-types')
        if (response.data.success) {
          this.userExamTypes = response.data.exam_types
        }
      } catch (error) {
        console.error('試験タイプ取得エラー:', error)
        this.showError('試験タイプの取得に失敗しました')
      } finally {
        this.loadingExams = false
      }
    },

    async loadUserSubjects() {
      this.loadingSubjects = true
      try {
        const response = await axios.get('/api/user/subject-areas')
        if (response.data.success) {
          this.userSubjects = response.data.subject_areas
        }
      } catch (error) {
        console.error('学習分野取得エラー:', error)
        this.showError('学習分野の取得に失敗しました')
      } finally {
        this.loadingSubjects = false
      }
    },

    // 試験関連
    editExam(exam) {
      this.editingExam = exam
      this.examForm = {
        name: exam.name,
        description: exam.description || '',
        exam_date: exam.exam_date || '',
        exam_notes: exam.exam_notes || '',
        color: exam.color || '#3B82F6'
      }
      this.showAddExamModal = true
    },

    async saveExam() {
      this.loading = true
      try {
        let response
        if (this.editingExam) {
          response = await axios.put(`/api/user/exam-types/${this.editingExam.id}`, this.examForm)
        } else {
          response = await axios.post('/api/user/exam-types', this.examForm)
        }

        if (response.data.success) {
          this.showSuccess(this.editingExam ? '試験情報を更新しました' : '新しい試験を追加しました')
          await this.loadUserExamTypes()
          this.cancelExamEdit()
        } else {
          this.showError(response.data.message || '保存に失敗しました')
        }
      } catch (error) {
        console.error('試験保存エラー:', error)
        this.showError('保存中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },

    async deleteExam(exam) {
      if (!confirm(`「${exam.name}」を削除しますか？関連する学習分野も削除されます。`)) {
        return
      }

      this.loading = true
      try {
        const response = await axios.delete(`/api/user/exam-types/${exam.id}`)
        if (response.data.success) {
          this.showSuccess('試験を削除しました')
          await this.loadUserExamTypes()
          await this.loadUserSubjects()
        } else {
          this.showError(response.data.message || '削除に失敗しました')
        }
      } catch (error) {
        console.error('試験削除エラー:', error)
        this.showError('削除中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },

    cancelExamEdit() {
      this.showAddExamModal = false
      this.editingExam = null
      this.examForm = {
        name: '',
        description: '',
        exam_date: '',
        exam_notes: '',
        color: '#3B82F6'
      }
    },

    // 学習分野関連
    editSubject(subject) {
      this.editingSubject = subject
      this.subjectForm = {
        exam_type_id: subject.exam_type_id,
        name: subject.name
      }
      this.showAddSubjectModal = true
    },

    async saveSubject() {
      this.loading = true
      try {
        let response
        if (this.editingSubject) {
          response = await axios.put(`/api/user/subject-areas/${this.editingSubject.id}`, this.subjectForm)
        } else {
          response = await axios.post('/api/user/subject-areas', this.subjectForm)
        }

        if (response.data.success) {
          this.showSuccess(this.editingSubject ? '学習分野を更新しました' : '新しい学習分野を追加しました')
          await this.loadUserSubjects()
          this.cancelSubjectEdit()
        } else {
          this.showError(response.data.message || '保存に失敗しました')
        }
      } catch (error) {
        console.error('学習分野保存エラー:', error)
        this.showError('保存中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },

    async deleteSubject(subject) {
      if (!confirm(`「${subject.name}」を削除しますか？関連する学習履歴は保持されます。`)) {
        return
      }

      this.loading = true
      try {
        const response = await axios.delete(`/api/user/subject-areas/${subject.id}`)
        if (response.data.success) {
          this.showSuccess('学習分野を削除しました')
          await this.loadUserSubjects()
        } else {
          this.showError(response.data.message || '削除に失敗しました')
        }
      } catch (error) {
        console.error('学習分野削除エラー:', error)
        this.showError('削除中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },

    cancelSubjectEdit() {
      this.showAddSubjectModal = false
      this.editingSubject = null
      this.subjectForm = {
        exam_type_id: '',
        name: ''
      }
    },

    // ユーティリティ
    formatExamDate(dateString) {
      if (!dateString) return '日程未定'
      
      const examDate = new Date(dateString)
      const today = new Date()
      const diffTime = examDate.getTime() - today.getTime()
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

      if (diffDays < 0) {
        return `${Math.abs(diffDays)}日前に実施済み`
      } else if (diffDays === 0) {
        return '今日が試験日！'
      } else if (diffDays === 1) {
        return '明日が試験日！'
      } else {
        return `あと${diffDays}日`
      }
    },

    getDaysUntilExam(dateString) {
      if (!dateString) return null
      
      const examDate = new Date(dateString)
      const today = new Date()
      const diffTime = examDate.getTime() - today.getTime()
      return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    },

    showError(message) {
      this.errorMessage = message
      setTimeout(() => {
        this.errorMessage = ''
      }, 5000)
    },

    showSuccess(message) {
      this.successMessage = message
      setTimeout(() => {
        this.successMessage = ''
      }, 3000)
    },

    // 学習目標関連
    async loadActiveGoal() {
      try {
        const response = await axios.get('/api/study-goals/active', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        
        if (response.data.success && response.data.goal) {
          this.activeGoal = response.data.goal
          // フォームにデータを設定
          this.goalForm = {
            exam_type_id: this.activeGoal.exam_type_id || '',
            daily_minutes_goal: this.activeGoal.daily_minutes_goal,
            weekly_minutes_goal: this.activeGoal.weekly_minutes_goal,
            exam_date: this.activeGoal.exam_date || '',
            is_active: true
          }
        }
      } catch (error) {
        console.error('アクティブ目標取得エラー:', error)
      }
    },
    
    async saveGoal() {
      this.loadingGoal = true
      this.clearMessages()
      
      try {
        let response
        if (this.activeGoal && this.editGoalMode) {
          // 更新
          response = await axios.put(`/api/study-goals/${this.activeGoal.id}`, this.goalForm, {
            headers: {
              'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
          })
        } else {
          // 新規作成
          response = await axios.post('/api/study-goals', this.goalForm, {
            headers: {
              'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
            }
          })
        }
        
        if (response.data.success) {
          this.showSuccess(response.data.message)
          this.activeGoal = response.data.goal
          this.editGoalMode = false
        } else {
          this.showError(response.data.message || '目標の保存に失敗しました')
        }
      } catch (error) {
        console.error('目標保存エラー:', error)
        if (error.response?.data?.errors) {
          const errors = Object.values(error.response.data.errors).flat()
          this.showError(errors.join(', '))
        } else {
          this.showError(error.response?.data?.message || '目標の保存中にエラーが発生しました')
        }
      } finally {
        this.loadingGoal = false
      }
    },
    
    async deleteGoal() {
      if (!confirm('本当に目標を削除しますか？')) return
      
      this.loadingGoal = true
      this.clearMessages()
      
      try {
        const response = await axios.delete(`/api/study-goals/${this.activeGoal.id}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          }
        })
        
        if (response.data.success) {
          this.showSuccess('目標を削除しました')
          this.activeGoal = null
          this.editGoalMode = false
          this.resetGoalForm()
        } else {
          this.showError(response.data.message || '目標の削除に失敗しました')
        }
      } catch (error) {
        console.error('目標削除エラー:', error)
        this.showError('目標の削除中にエラーが発生しました')
      } finally {
        this.loadingGoal = false
      }
    },
    
    cancelGoalEdit() {
      this.editGoalMode = false
      this.clearMessages()
      if (this.activeGoal) {
        // フォームを元に戻す
        this.goalForm = {
          exam_type_id: this.activeGoal.exam_type_id || '',
          daily_minutes_goal: this.activeGoal.daily_minutes_goal,
          weekly_minutes_goal: this.activeGoal.weekly_minutes_goal,
          exam_date: this.activeGoal.exam_date || '',
          is_active: true
        }
      }
    },
    
    resetGoalForm() {
      this.goalForm = {
        exam_type_id: '',
        daily_minutes_goal: 60,
        weekly_minutes_goal: null,
        exam_date: '',
        is_active: true
      }
    },
    
    formatDate(dateString) {
      if (!dateString) return ''
      return new Date(dateString).toLocaleDateString('ja-JP')
    },
    
    formatHours(minutes) {
      if (!minutes) return '0時間'
      const hours = Math.round(minutes / 60 * 10) / 10
      return `${hours}時間`
    },
    
    clearMessages() {
      this.errorMessage = ''
      this.successMessage = ''
    }
  }
}
</script>