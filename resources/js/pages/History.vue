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
        <div v-for="session in sessions" :key="`${session.type}-${session.id}`" class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex justify-between items-start mb-2">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-lg">{{ getSessionIcon(session) }}</span>
                <div class="font-medium">{{ session.subject_area_name || '休憩セッション' }}</div>
                <span class="text-xs px-2 py-1 rounded-full" :class="getSessionTypeClass(session.type)">
                  {{ getSessionTypeLabel(session.type) }}
                </span>
              </div>
              <div class="text-sm text-gray-600">{{ session.exam_type_name }}</div>
              <div v-if="session.type === 'pomodoro'" class="text-xs text-gray-500 mt-1">
                {{ session.session_details?.method }} - {{ session.session_details?.session_type }}
                <span v-if="session.was_interrupted" class="text-red-500 ml-1">（中断）</span>
              </div>
            </div>
            <div class="text-right">
              <div class="font-bold" :class="session.type === 'pomodoro' ? 'text-red-600' : 'text-blue-600'">
                {{ session.duration_minutes }}分
              </div>
              <div class="text-xs text-gray-500">{{ formatDate(session.started_at) }}</div>
            </div>
          </div>
          <div v-if="session.notes" class="text-sm text-gray-700 mb-2">
            📝 {{ session.notes }}
          </div>
          <div class="flex justify-between items-center">
            <div class="text-xs text-gray-500">
              {{ formatTime(session.started_at) }} - {{ formatTime(session.ended_at) }}
            </div>
            <div class="flex gap-2">
              <button 
                v-if="session.type === 'time_tracking'"
                @click="editSession(session)"
                class="text-blue-600 hover:text-blue-800 text-xs cursor-pointer"
              >
                ✏️ 編集
              </button>
              <button 
                v-if="session.type === 'pomodoro'"
                @click="editPomodoroNotes(session)"
                class="text-orange-600 hover:text-orange-800 text-xs cursor-pointer"
              >
                📝 メモ編集
              </button>
              <button 
                @click="deleteSession(session)"
                class="text-red-600 hover:text-red-800 text-xs cursor-pointer"
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
            <div class="text-sm text-gray-600">{{ formatDate(deletingSession.started_at) }} • {{ deletingSession.duration_minutes }}分</div>
            <div v-if="deletingSession.notes" class="text-xs text-gray-500 mt-1">{{ deletingSession.notes }}</div>
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

    <!-- ポモドーロメモ編集モーダル -->
    <div v-if="pomodoroNotesModal.isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closePomodoroNotesModal">
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4" @click.stop>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold">🍅 ポモドーロメモ編集</h3>
          <button @click="closePomodoroNotesModal" class="text-gray-500 hover:text-gray-700">
            ✕
          </button>
        </div>
        
        <div class="mb-4">
          <div class="text-sm text-gray-600 mb-2">
            {{ pomodoroNotesModal.session?.subject_area_name }} - {{ pomodoroNotesModal.session?.duration_minutes }}分
          </div>
          <div class="text-xs text-gray-500">
            {{ formatDate(pomodoroNotesModal.session?.started_at) }}
          </div>
        </div>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">メモ</label>
          <textarea
            v-model="pomodoroNotesModal.notes"
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            rows="4"
            placeholder="ポモドーロセッションでのメモを入力してください..."
          ></textarea>
        </div>
        
        <div class="flex gap-3">
          <button
            @click="closePomodoroNotesModal"
            class="flex-1 px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200"
          >
            キャンセル
          </button>
          <button
            @click="savePomodoroNotes"
            :disabled="pomodoroNotesModal.saving"
            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ pomodoroNotesModal.saving ? '保存中...' : '保存' }}
          </button>
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
      deletingSession: null,
      
      // ポモドーロメモ編集関連
      pomodoroNotesModal: {
        isOpen: false,
        session: null,
        notes: '',
        saving: false
      }
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
        // 統合分析APIを使用してポモドーロセッションも含めて取得
        const response = await axios.get(`/api/analytics/history?limit=20`)
        if (response.data.success) {
          this.sessions = response.data.data || []
          // 統合APIはページネーションが異なるため、シンプルな判定
          this.hasMore = this.sessions.length >= 20
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
        // 統合APIでオフセットベースの取得
        const offset = this.sessions.length
        const response = await axios.get(`/api/analytics/history?limit=20&offset=${offset}`)
        if (response.data.success) {
          const newSessions = response.data.data || []
          this.sessions.push(...newSessions)
          this.hasMore = newSessions.length >= 20
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
        // セッションタイプに応じて適切なAPIエンドポイントを使用
        const apiPath = this.deletingSession.type === 'pomodoro' 
          ? `/api/pomodoro/${this.deletingSession.id}`
          : `/api/study-sessions/${this.deletingSession.id}`
        
        const response = await axios.delete(apiPath)
        
        if (response.data.success || response.status === 200) {
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
      if (!dateString) return '不明な日付'
      const date = new Date(dateString)
      if (isNaN(date.getTime())) return '不明な日付'
      return `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`
    },
    
    formatTime(dateString) {
      if (!dateString) return ''
      const date = new Date(dateString)
      return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
    },
    
    getSessionIcon(session) {
      if (session.type === 'pomodoro') {
        const typeIcons = {
          focus: '🎯',
          short_break: '☕',
          long_break: '🛋️'
        }
        return typeIcons[session.session_details?.session_type] || '🍅'
      }
      return '⏱️'
    },
    
    getSessionTypeClass(type) {
      return type === 'pomodoro' 
        ? 'bg-red-100 text-red-800'
        : 'bg-blue-100 text-blue-800'
    },
    
    getSessionTypeLabel(type) {
      return type === 'pomodoro' ? 'ポモドーロ' : '時間計測'
    },
    
    // ポモドーロメモ編集関連
    editPomodoroNotes(session) {
      this.pomodoroNotesModal.session = session
      this.pomodoroNotesModal.notes = session.notes || ''
      this.pomodoroNotesModal.isOpen = true
    },
    
    closePomodoroNotesModal() {
      this.pomodoroNotesModal.isOpen = false
      this.pomodoroNotesModal.session = null
      this.pomodoroNotesModal.notes = ''
      this.pomodoroNotesModal.saving = false
    },
    
    async savePomodoroNotes() {
      if (!this.pomodoroNotesModal.session) return
      
      this.pomodoroNotesModal.saving = true
      
      try {
        const response = await axios.put(`/api/pomodoro/${this.pomodoroNotesModal.session.id}`, {
          notes: this.pomodoroNotesModal.notes
        })
        
        if (response.data.success) {
          // セッションリストを更新
          const sessionIndex = this.sessions.findIndex(s => 
            s.type === 'pomodoro' && s.id === this.pomodoroNotesModal.session.id
          )
          if (sessionIndex !== -1) {
            this.sessions[sessionIndex].notes = this.pomodoroNotesModal.notes
          }
          
          alert('メモを保存しました')
          this.closePomodoroNotesModal()
        }
      } catch (error) {
        console.error('メモ保存エラー:', error)
        alert('メモの保存に失敗しました')
      } finally {
        this.pomodoroNotesModal.saving = false
      }
    }
  }
}
</script>