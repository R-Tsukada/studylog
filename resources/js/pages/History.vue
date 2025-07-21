<template>
  <div>
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold mb-4 text-gray-800">📚 学習履歴</h2>
      
      <div v-if="loadingHistory" class="text-center py-8">
        <div class="text-gray-500">履歴を読み込み中...</div>
      </div>
      
      <div v-else-if="sessions.length === 0" class="text-center py-8">
        <div class="text-gray-500">まだ学習履歴がありません</div>
        <router-link 
          to="/dashboard"
          class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg"
        >
          🎯 学習を開始する
        </router-link>
      </div>
      
      <div v-else class="space-y-4">
        <div v-for="session in sessions" :key="session.id" class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex justify-between items-start mb-2">
            <div class="flex-1">
              <div class="font-medium">{{ session.subject_area_name }}</div>
              <div class="text-sm text-gray-600">{{ session.exam_type_name }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-blue-600">{{ session.duration_minutes }}分</div>
              <div class="text-xs text-gray-500">{{ formatDate(session.date) }}</div>
            </div>
          </div>
          <div class="text-sm text-gray-700 mb-2">{{ session.study_comment }}</div>
          <div class="flex justify-between items-center">
            <div class="text-xs text-gray-500">
              {{ session.started_at }} - {{ session.ended_at }}
            </div>
            <div class="flex gap-2">
              <button 
                @click="editSession(session)"
                class="text-blue-600 hover:text-blue-800 text-xs"
              >
                ✏️ 編集
              </button>
              <button 
                @click="deleteSession(session)"
                class="text-red-600 hover:text-red-800 text-xs"
              >
                🗑️ 削除
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="hasMore" class="text-center">
          <button 
            @click="loadMoreHistory" 
            :disabled="loadingMore"
            class="text-blue-600 hover:text-blue-800 text-sm"
          >
            {{ loadingMore ? '読み込み中...' : '📋 もっと見る →' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 編集モーダル -->
    <div v-if="editingSession" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-lg max-w-md w-full max-h-96 overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-4">📝 学習記録を編集</h3>
          
          <!-- 学習分野選択 -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">学習分野</label>
            <select 
              v-model="editForm.subject_area_id" 
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
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
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">学習内容</label>
            <textarea 
              v-model="editForm.study_comment"
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              rows="3"
            ></textarea>
          </div>

          <!-- 学習時間 -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">学習時間（分）</label>
            <input 
              type="number" 
              v-model.number="editForm.duration_minutes"
              min="1"
              max="1440"
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <div class="flex gap-3">
            <button 
              @click="saveEdit" 
              :disabled="loading"
              class="flex-1 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded-lg"
            >
              💾 保存
            </button>
            <button 
              @click="cancelEdit"
              class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg"
            >
              キャンセル
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 削除確認モーダル -->
    <div v-if="deletingSession" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-lg max-w-md w-full">
        <div class="p-6">
          <h3 class="text-lg font-semibold mb-4 text-red-600">🗑️ 学習記録を削除</h3>
          <p class="text-gray-700 mb-2">以下の学習記録を削除しますか？</p>
          <div class="bg-gray-50 p-3 rounded-lg mb-6">
            <div class="font-medium">{{ deletingSession.subject_area_name }}</div>
            <div class="text-sm text-gray-600">{{ deletingSession.exam_type_name }}</div>
            <div class="text-sm text-gray-600">{{ formatDate(deletingSession.date) }} • {{ deletingSession.duration_minutes }}分</div>
            <div class="text-xs text-gray-500 mt-1">{{ deletingSession.study_comment }}</div>
          </div>
          <p class="text-sm text-red-600 mb-6">⚠️ この操作は取り消せません</p>
          
          <div class="flex gap-3">
            <button 
              @click="executeDelete" 
              :disabled="loading"
              class="flex-1 bg-red-500 hover:bg-red-600 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded-lg"
            >
              🗑️ 削除
            </button>
            <button 
              @click="cancelDelete"
              class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg"
            >
              キャンセル
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
  name: 'History',
  data() {
    return {
      sessions: [],
      examTypes: [],
      loadingHistory: false,
      loadingMore: false,
      loading: false,
      hasMore: true,
      currentPage: 1,
      
      // 編集関連
      editingSession: null,
      editForm: {
        subject_area_id: '',
        study_comment: '',
        duration_minutes: 0
      },
      
      // 削除関連
      deletingSession: null
    }
  },
  async mounted() {
    await this.loadExamTypes()
    await this.loadStudyHistory()
  },
  methods: {
    async loadExamTypes() {
      try {
        const response = await axios.get('/api/exam-types')
        this.examTypes = response.data
      } catch (error) {
        console.error('試験タイプ取得エラー:', error)
      }
    },
    
    async loadStudyHistory() {
      this.loadingHistory = true
      try {
        const response = await axios.get(`/api/study-sessions/history?page=1&limit=20`)
        if (response.data.success) {
          this.sessions = response.data.history
          this.hasMore = response.data.pagination.current_page < response.data.pagination.last_page
          this.currentPage = 1
        }
      } catch (error) {
        console.error('学習履歴取得エラー:', error)
      } finally {
        this.loadingHistory = false
      }
    },
    
    async loadMoreHistory() {
      this.loadingMore = true
      try {
        const nextPage = this.currentPage + 1
        const response = await axios.get(`/api/study-sessions/history?page=${nextPage}&limit=20`)
        if (response.data.success) {
          this.sessions.push(...response.data.history)
          this.hasMore = response.data.pagination.current_page < response.data.pagination.last_page
          this.currentPage = nextPage
        }
      } catch (error) {
        console.error('追加履歴取得エラー:', error)
      } finally {
        this.loadingMore = false
      }
    },
    
    editSession(session) {
      this.editingSession = session
      this.editForm = {
        subject_area_id: session.subject_area_id,
        study_comment: session.study_comment,
        duration_minutes: session.duration_minutes
      }
    },
    
    cancelEdit() {
      this.editingSession = null
      this.editForm = {
        subject_area_id: '',
        study_comment: '',
        duration_minutes: 0
      }
    },
    
    async saveEdit() {
      if (!this.editForm.subject_area_id || !this.editForm.study_comment.trim() || this.editForm.duration_minutes <= 0) {
        alert('すべての項目を正しく入力してください')
        return
      }
      
      this.loading = true
      try {
        const response = await axios.put(`/api/study-sessions/${this.editingSession.id}`, {
          subject_area_id: this.editForm.subject_area_id,
          study_comment: this.editForm.study_comment,
          duration_minutes: this.editForm.duration_minutes
        })
        
        if (response.data.success) {
          await this.loadStudyHistory()
          this.cancelEdit()
        } else {
          alert(response.data.message || '更新に失敗しました')
        }
      } catch (error) {
        console.error('編集エラー:', error)
        alert('編集中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },
    
    deleteSession(session) {
      this.deletingSession = session
    },
    
    cancelDelete() {
      this.deletingSession = null
    },
    
    async executeDelete() {
      this.loading = true
      try {
        const response = await axios.delete(`/api/study-sessions/${this.deletingSession.id}`)
        
        if (response.data.success) {
          await this.loadStudyHistory()
          this.cancelDelete()
        } else {
          alert(response.data.message || '削除に失敗しました')
        }
      } catch (error) {
        console.error('削除エラー:', error)
        alert('削除中にエラーが発生しました')
      } finally {
        this.loading = false
      }
    },
    
    formatDate(dateString) {
      const date = new Date(dateString)
      return `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`
    }
  }
}
</script>