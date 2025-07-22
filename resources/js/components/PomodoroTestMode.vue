<template>
  <div class="test-pomodoro max-w-md mx-auto p-6 bg-yellow-50 border border-yellow-300 rounded-xl">
    <h3 class="text-xl font-bold text-yellow-800 mb-4 text-center">🧪 ポモドーロテストモード</h3>
    <div class="text-sm text-yellow-700 mb-4 text-center">
      テスト用に短縮された時間設定で自動切り替え機能を確認できます
    </div>
    
    <!-- テスト用設定 -->
    <div v-if="!isActive && !currentSession" class="mb-4">
      <div class="mb-3">
        <label class="block text-sm font-medium text-yellow-800 mb-2">テスト用時間設定</label>
        <div class="grid grid-cols-3 gap-2 text-xs">
          <div class="text-center p-2 bg-yellow-100 rounded">
            <div>集中</div>
            <div class="font-bold">{{ testDurations.focus }}秒</div>
          </div>
          <div class="text-center p-2 bg-yellow-100 rounded">
            <div>短休憩</div>
            <div class="font-bold">{{ testDurations.short_break }}秒</div>
          </div>
          <div class="text-center p-2 bg-yellow-100 rounded">
            <div>長休憩</div>
            <div class="font-bold">{{ testDurations.long_break }}秒</div>
          </div>
        </div>
      </div>
      
      <!-- 学習分野選択 -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-yellow-800 mb-2">学習分野</label>
        <select
          v-model="selectedSubjectArea"
          required
          class="w-full px-3 py-2 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500"
        >
          <option value="">学習分野を選択してください</option>
          <option
            v-for="area in availableSubjectAreas"
            :key="area.id"
            :value="area.id"
          >
            {{ area.exam_type?.name }} - {{ area.name }}
          </option>
        </select>
      </div>
      
      <!-- テスト開始ボタン -->
      <button
        @click="startTestSession"
        :disabled="!selectedSubjectArea"
        class="w-full py-3 px-4 bg-yellow-500 text-white font-bold rounded-lg hover:bg-yellow-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
      >
        🧪 テストセッション開始
      </button>
    </div>
    
    <!-- アクティブセッション表示 -->
    <div v-else-if="isActive || currentSession" class="text-center">
      <div class="mb-4">
        <h4 class="text-lg font-bold text-yellow-800">
          {{ currentSessionTypeLabel }}
        </h4>
        <div class="text-sm text-yellow-600">
          テスト実行中...
        </div>
      </div>
      
      <!-- タイマー表示 -->
      <div class="timer-display mb-4">
        <div class="text-4xl font-mono font-bold text-yellow-800 mb-2">
          {{ formatTime(globalPomodoroTimer.timeRemaining) }}
        </div>
        
        <!-- プログレスバー -->
        <div class="w-full bg-yellow-200 rounded-full h-2 mb-2">
          <div
            class="h-2 rounded-full transition-all duration-1000 bg-yellow-500"
            :style="{ width: `${progressPercentage}%` }"
          ></div>
        </div>
        
        <div class="text-sm text-yellow-700">
          残り: {{ Math.floor(timeRemaining / 60) }}分{{ timeRemaining % 60 }}秒
        </div>
      </div>
      
      <!-- コントロールボタン -->
      <div class="mb-4">
        <button
          @click="stopTestSession"
          class="w-full py-2 px-4 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors"
        >
          テスト中止
        </button>
      </div>
      
      <!-- テストログ -->
      <div v-if="testLog.length > 0" class="mt-4 p-3 bg-yellow-100 rounded-lg">
        <h5 class="font-semibold text-yellow-800 mb-2">テストログ</h5>
        <div class="text-xs text-yellow-700 space-y-1 max-h-32 overflow-y-auto">
          <div v-for="(log, index) in testLog" :key="index">
            {{ log }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PomodoroTestMode',
  inject: ['globalPomodoroTimer', 'startGlobalPomodoroTimer', 'stopGlobalPomodoroTimer'],
  data() {
    return {
      selectedSubjectArea: '',
      availableSubjectAreas: [],
      testLog: [],
      
      // テスト用の短縮時間設定（秒）
      testDurations: {
        focus: 10,          // 10秒（通常は25分）
        short_break: 5,     // 5秒（通常は5分）
        long_break: 8       // 8秒（通常は15分）
      }
    }
  },
  
  computed: {
    isActive() {
      return this.globalPomodoroTimer.isActive
    },
    
    currentSession() {
      return this.globalPomodoroTimer.currentSession
    },
    
    timeRemaining() {
      return this.globalPomodoroTimer.timeRemaining
    },
    
    currentSessionTypeLabel() {
      const labels = {
        focus: '🎯 集中セッション（テスト）',
        short_break: '☕ 短い休憩（テスト）',
        long_break: '🛋️ 長い休憩（テスト）'
      }
      return labels[this.currentSession?.session_type] || ''
    },
    
    progressPercentage() {
      if (!this.currentSession) return 0
      const totalTime = this.currentSession.planned_duration * 60
      const elapsed = totalTime - this.globalPomodoroTimer.timeRemaining
      return Math.min(100, (elapsed / totalTime) * 100)
    }
  },
  
  async mounted() {
    await this.loadAvailableSubjectAreas()
  },
  
  methods: {
    async loadAvailableSubjectAreas() {
      try {
        const token = localStorage.getItem('auth_token')
        const response = await fetch('/api/user/subject-areas', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        })
        
        if (response.ok) {
          const data = await response.json()
          this.availableSubjectAreas = data.subject_areas || []
        }
      } catch (error) {
        console.error('学習分野取得エラー:', error)
      }
    },
    
    async startTestSession() {
      try {
        this.addTestLog('🧪 テストセッション開始...')
        
        const sessionData = {
          session_type: 'focus',
          planned_duration: this.testDurations.focus / 60, // 秒を分に変換
          study_session_id: null,
          subject_area_id: this.selectedSubjectArea,
          settings: {
            focus_duration: this.testDurations.focus / 60,
            short_break_duration: this.testDurations.short_break / 60,
            long_break_duration: this.testDurations.long_break / 60,
            auto_start_break: true,
            auto_start_focus: true,
            sound_enabled: true,
          }
        }
        
        const response = await fetch('/api/pomodoro', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(sessionData)
        })
        
        if (response.status === 201 || response.status === 200) {
          const sessionData = await response.json()
          
          // グローバルタイマーでセッションを開始
          this.startGlobalPomodoroTimer(sessionData)
          
          // テスト用に実際の残り時間を秒単位で設定
          this.globalPomodoroTimer.timeRemaining = this.testDurations.focus
          
          this.addTestLog('✅ 集中セッション開始 (10秒)')
        } else {
          const errorData = await response.json()
          this.addTestLog('❌ セッション開始エラー: ' + (errorData.message || 'Unknown error'))
        }
      } catch (error) {
        console.error('テストセッション開始エラー:', error)
        this.addTestLog('❌ テストセッション開始エラー: ' + error.message)
      }
    },
    
    async stopTestSession() {
      if (!confirm('テストセッションを中止しますか？')) return
      
      this.addTestLog('🛑 テストセッション中止')
      this.stopGlobalPomodoroTimer()
      this.clearTestLog()
    },
    
    addTestLog(message) {
      const timestamp = new Date().toLocaleTimeString('ja-JP')
      this.testLog.push(`[${timestamp}] ${message}`)
      
      // ログが10件を超えたら古いものを削除
      if (this.testLog.length > 10) {
        this.testLog.shift()
      }
    },
    
    clearTestLog() {
      this.testLog = []
    },
    
    formatTime(seconds) {
      const mins = Math.floor(seconds / 60)
      const secs = seconds % 60
      return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }
  }
}
</script>

<style scoped>
.test-pomodoro {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>