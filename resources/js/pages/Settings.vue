<template>
  <div>
    <!-- 設定ページヘッダー -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 mb-2">⚙️ 設定</h2>
      <p class="text-gray-600">学習分野や試験予定日を管理できます</p>
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
      
      // ローディング状態
      loading: false,
      loadingExams: false,
      loadingSubjects: false,
      
      // モーダル表示状態
      showAddExamModal: false,
      showAddSubjectModal: false,
      
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
      
      // メッセージ
      errorMessage: '',
      successMessage: ''
    }
  },
  async mounted() {
    await this.loadUserExamTypes()
    await this.loadUserSubjects()
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
    }
  }
}
</script>