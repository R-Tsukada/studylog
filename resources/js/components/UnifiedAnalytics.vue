<template>
  <div class="unified-analytics space-y-6">
    <!-- 期間選択 -->
    <div class="period-selector bg-white rounded-lg shadow p-4">
      <h3 class="text-lg font-semibold text-gray-800 mb-3">📊 学習分析</h3>
      <div class="flex flex-wrap gap-3 mb-4">
        <button
          v-for="preset in periodPresets"
          :key="preset.key"
          @click="setPeriod(preset)"
          :class="[
            'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
            selectedPeriod === preset.key
              ? 'bg-blue-500 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          ]"
        >
          {{ preset.label }}
        </button>
      </div>
      
      <!-- カスタム期間選択 -->
      <div class="flex gap-3 text-sm">
        <div>
          <label class="block text-gray-600 mb-1">開始日</label>
          <input
            v-model="customStartDate"
            type="date"
            @change="onCustomDateChange"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
        </div>
        <div>
          <label class="block text-gray-600 mb-1">終了日</label>
          <input
            v-model="customEndDate"
            type="date"
            @change="onCustomDateChange"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
        </div>
      </div>
    </div>

    <!-- ローディング -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-3 text-gray-600">データを分析中...</span>
    </div>

    <!-- エラー -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
      <div class="text-red-500 mb-2">❌</div>
      <p class="text-sm text-red-600 mb-3">{{ error }}</p>
      <button 
        @click="loadData"
        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm"
      >
        再読み込み
      </button>
    </div>

    <!-- 統計データ -->
    <div v-else-if="stats" class="analytics-content space-y-6">
      <!-- 概要統計 -->
      <div class="overview-stats bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">📈 学習概要</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="stat-card text-center p-4 bg-blue-50 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">
              {{ Math.floor(stats.overview.total_study_time / 60) }}h {{ stats.overview.total_study_time % 60 }}m
            </div>
            <div class="text-sm text-gray-600">総学習時間</div>
          </div>
          <div class="stat-card text-center p-4 bg-green-50 rounded-lg">
            <div class="text-2xl font-bold text-green-600">{{ stats.overview.total_sessions }}</div>
            <div class="text-sm text-gray-600">総セッション数</div>
          </div>
          <div class="stat-card text-center p-4 bg-purple-50 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">{{ stats.overview.average_session_length }}分</div>
            <div class="text-sm text-gray-600">平均セッション時間</div>
          </div>
          <div class="stat-card text-center p-4 bg-orange-50 rounded-lg">
            <div class="text-2xl font-bold text-orange-600">{{ stats.overview.study_days }}</div>
            <div class="text-sm text-gray-600">学習日数</div>
          </div>
        </div>
      </div>

      <!-- 手法別比較 -->
      <div class="method-comparison bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">⚖️ 手法別比較</h4>
        <div class="grid md:grid-cols-2 gap-6">
          <!-- 時間計測 -->
          <div class="method-stats p-4 border border-green-200 rounded-lg bg-green-50">
            <div class="flex items-center mb-3">
              <span class="text-2xl mr-3">⏰</span>
              <h5 class="text-lg font-medium text-green-800">自由時間計測</h5>
            </div>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span>セッション数:</span>
                <span class="font-medium">{{ stats.by_method.time_tracking.total_sessions }}</span>
              </div>
              <div class="flex justify-between">
                <span>総時間:</span>
                <span class="font-medium">{{ formatDuration(stats.by_method.time_tracking.total_duration) }}</span>
              </div>
              <div class="flex justify-between">
                <span>平均時間:</span>
                <span class="font-medium">{{ Math.round(stats.by_method.time_tracking.average_duration) }}分</span>
              </div>
              <div class="flex justify-between">
                <span>最長セッション:</span>
                <span class="font-medium">{{ Math.round(stats.by_method.time_tracking.longest_session) }}分</span>
              </div>
            </div>
          </div>

          <!-- ポモドーロ -->
          <div class="method-stats p-4 border border-red-200 rounded-lg bg-red-50">
            <div class="flex items-center mb-3">
              <span class="text-2xl mr-3">🍅</span>
              <h5 class="text-lg font-medium text-red-800">ポモドーロテクニック</h5>
            </div>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span>総セッション数:</span>
                <span class="font-medium">{{ stats.by_method.pomodoro.total_sessions }}</span>
              </div>
              <div class="flex justify-between">
                <span>集中セッション数:</span>
                <span class="font-medium">{{ stats.by_method.pomodoro.focus_sessions }}</span>
              </div>
              <div class="flex justify-between">
                <span>集中時間:</span>
                <span class="font-medium">{{ formatDuration(stats.by_method.pomodoro.total_focus_time) }}</span>
              </div>
              <div class="flex justify-between">
                <span>完了率:</span>
                <span class="font-medium">{{ stats.by_method.pomodoro.completion_rate }}%</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 学習分野別分析 -->
      <div v-if="stats.subject_breakdown && stats.subject_breakdown.length > 0" class="subject-breakdown bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">📚 学習分野別分析</h4>
        <div class="space-y-3">
          <div
            v-for="subject in stats.subject_breakdown.slice(0, 5)"
            :key="subject.subject_name"
            class="subject-item p-3 bg-gray-50 rounded-lg"
          >
            <div class="flex justify-between items-center mb-2">
              <span class="font-medium text-gray-800">{{ subject.subject_name }}</span>
              <span class="text-sm text-gray-600">{{ formatDuration(subject.total_duration) }}</span>
            </div>
            <div class="flex text-xs text-gray-500 gap-4">
              <span>計測: {{ formatDuration(subject.time_tracking_duration) }}</span>
              <span>ポモドーロ: {{ formatDuration(subject.pomodoro_duration) }}</span>
              <span>{{ subject.session_count }}セッション</span>
            </div>
            <!-- プログレスバー -->
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
              <div class="flex h-2 rounded-full overflow-hidden">
                <div 
                  class="bg-green-400"
                  :style="{ width: `${(subject.time_tracking_duration / subject.total_duration) * 100}%` }"
                ></div>
                <div 
                  class="bg-red-400"
                  :style="{ width: `${(subject.pomodoro_duration / subject.total_duration) * 100}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- インサイト -->
      <div v-if="stats.insights && stats.insights.length > 0" class="insights bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">💡 学習インサイト</h4>
        <div class="space-y-3">
          <div
            v-for="(insight, index) in stats.insights"
            :key="index"
            class="insight-item p-3 bg-blue-50 border border-blue-200 rounded-lg"
          >
            <p class="text-sm text-blue-800">{{ insight }}</p>
          </div>
        </div>
      </div>

      <!-- 日別推移グラフ（簡易版） -->
      <div v-if="stats.daily_breakdown && stats.daily_breakdown.length > 0" class="daily-chart bg-white rounded-lg shadow p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">📅 日別学習時間推移</h4>
        <div class="chart-container">
          <div class="flex items-end space-x-1 h-32">
            <div
              v-for="day in stats.daily_breakdown.slice(-14)"
              :key="day.date"
              class="flex-1 flex flex-col items-center"
            >
              <div class="flex flex-col w-full">
                <!-- ポモドーロ時間（上部・赤） -->
                <div
                  v-if="day.pomodoro_minutes > 0"
                  class="bg-red-400 rounded-t"
                  :style="{ height: `${Math.max(2, (day.pomodoro_minutes / getMaxDailyMinutes()) * 100)}px` }"
                ></div>
                <!-- 時間計測（下部・緑） -->
                <div
                  v-if="day.time_tracking_minutes > 0"
                  class="bg-green-400 rounded-b"
                  :style="{ height: `${Math.max(2, (day.time_tracking_minutes / getMaxDailyMinutes()) * 100)}px` }"
                ></div>
              </div>
              <div class="text-xs text-gray-500 mt-1 transform -rotate-45 origin-top-left">
                {{ formatDate(day.date) }}
              </div>
            </div>
          </div>
          <div class="flex items-center justify-center mt-4 space-x-4 text-xs">
            <div class="flex items-center">
              <div class="w-3 h-3 bg-green-400 rounded mr-1"></div>
              <span>時間計測</span>
            </div>
            <div class="flex items-center">
              <div class="w-3 h-3 bg-red-400 rounded mr-1"></div>
              <span>ポモドーロ</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UnifiedAnalytics',
  data() {
    return {
      loading: false,
      error: null,
      stats: null,
      selectedPeriod: 'week',
      customStartDate: '',
      customEndDate: '',
      periodPresets: [
        { key: 'week', label: '1週間', days: 7 },
        { key: 'month', label: '1ヶ月', days: 30 },
        { key: 'quarter', label: '3ヶ月', days: 90 },
        { key: 'custom', label: 'カスタム', days: null }
      ]
    }
  },
  mounted() {
    this.initializeDateRange()
    this.loadData()
  },
  methods: {
    initializeDateRange() {
      const now = new Date()
      const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
      
      this.customEndDate = now.toISOString().split('T')[0]
      this.customStartDate = weekAgo.toISOString().split('T')[0]
    },

    setPeriod(preset) {
      this.selectedPeriod = preset.key
      
      if (preset.days) {
        const now = new Date()
        const startDate = new Date(now.getTime() - preset.days * 24 * 60 * 60 * 1000)
        
        this.customEndDate = now.toISOString().split('T')[0]
        this.customStartDate = startDate.toISOString().split('T')[0]
        
        this.loadData()
      }
    },

    onCustomDateChange() {
      this.selectedPeriod = 'custom'
      this.loadData()
    },

    async loadData() {
      if (!this.customStartDate || !this.customEndDate) return
      
      this.loading = true
      this.error = null

      try {
        const params = new URLSearchParams({
          start_date: this.customStartDate,
          end_date: this.customEndDate
        })

        const response = await fetch(`/api/analytics/stats?${params}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        })

        if (!response.ok) {
          throw new Error('統計データの取得に失敗しました')
        }

        const data = await response.json()
        
        if (data.success) {
          this.stats = data.data
          console.log('統合分析データ:', this.stats)
        } else {
          throw new Error(data.message || '統計データの取得に失敗しました')
        }
      } catch (error) {
        console.error('統計データ取得エラー:', error)
        this.error = error.message || '統計データの取得中にエラーが発生しました'
      } finally {
        this.loading = false
      }
    },

    formatDuration(minutes) {
      if (minutes < 60) {
        return `${minutes}分`
      }
      const hours = Math.floor(minutes / 60)
      const remainingMinutes = minutes % 60
      return `${hours}h ${remainingMinutes}m`
    },

    formatDate(dateString) {
      const date = new Date(dateString)
      return `${date.getMonth() + 1}/${date.getDate()}`
    },

    getMaxDailyMinutes() {
      if (!this.stats?.daily_breakdown) return 60
      
      return Math.max(
        ...this.stats.daily_breakdown.map(day => day.total_minutes),
        60 // 最小値として60分を設定
      )
    }
  }
}
</script>

<style scoped>
.unified-analytics {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.chart-container {
  overflow-x: auto;
}
</style>