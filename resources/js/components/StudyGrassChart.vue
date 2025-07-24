<template>
  <div class="study-grass-chart">
    <!-- ヘッダー -->
    <div class="grass-header">
      <h3 class="grass-title">学習活動</h3>
      <div class="grass-controls">
        <select v-model="selectedYear" @change="loadYearData" class="year-selector">
          <option v-for="year in availableYears" :key="year" :value="year">
            {{ year }}年
          </option>
        </select>
        <button @click="refreshData" :disabled="loading" class="refresh-btn">
          <span v-if="loading">更新中...</span>
          <span v-else>更新</span>
        </button>
      </div>
    </div>

    <!-- エラー表示 -->
    <div v-if="error" class="error-message">
      {{ error }}
      <button @click="loadYearData" class="retry-btn">再試行</button>
    </div>

    <!-- ローディング表示 -->
    <div v-if="loading" class="loading-container">
      <div class="loading-spinner"></div>
      <p>データを読み込み中...</p>
    </div>

    <!-- 草表示メイン -->
    <div v-else-if="grassData" class="grass-container">
      <!-- 統計サマリー -->
      <div class="grass-stats-summary">
        <span class="stat-item">
          {{ grassData.stats.studyDays }}日間学習
        </span>
        <span class="stat-item">
          合計{{ grassData.stats.totalHours }}時間
        </span>
        <span class="stat-item">
          最長{{ grassData.stats.longestStreak }}日連続
        </span>
        <span class="stat-item">
          現在{{ grassData.stats.currentStreak }}日連続
        </span>
      </div>

      <!-- カレンダーグリッド -->
      <div class="grass-calendar">
        <!-- 月ラベル -->
        <div class="month-labels-container">
          <div class="month-labels-spacer"></div>
          <div class="month-labels-scroll">
            <div class="month-labels">
              <span v-for="(month, index) in monthLabels" :key="index" class="month-label">
                {{ month }}
              </span>
            </div>
          </div>
        </div>
        
        <!-- カレンダー本体 -->
        <div class="calendar-container">
          <!-- 曜日ラベル -->
          <div class="day-labels">
            <span v-for="(day, index) in dayLabels" :key="index" class="day-label">
              {{ day }}
            </span>
          </div>

          <!-- 草グリッド -->
          <div class="grass-grid">
            <div v-for="(week, weekIndex) in grassData.weeks" :key="weekIndex" class="grass-week">
              <div
                v-for="(day, dayIndex) in week"
                :key="`${weekIndex}-${dayIndex}`"
                :class="getGrassCellClass(day)"
                :style="getGrassCellStyle(day)"
                @click="handleDayClick(day)"
                @mouseenter="showTooltip(day, $event)"
                @mouseleave="hideTooltip"
                class="grass-cell"
              >
                <span v-if="day.isEmpty" class="sr-only">空のセル</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 凡例 -->
      <div class="grass-legend">
        <span class="legend-label">少ない</span>
        <div class="legend-items">
          <div
            v-for="level in [0, 1, 2, 3]"
            :key="level"
            :class="getGrassLevelClass(level)"
            :style="{ backgroundColor: getGrassColor(level) }"
            class="legend-cell"
          ></div>
        </div>
        <span class="legend-label">多い</span>
      </div>
    </div>

    <!-- ツールチップ -->
    <GrassTooltip
      v-if="tooltip.visible"
      :day-data="tooltip.data"
      :position="tooltip.position"
      @close="hideTooltip"
    />

    <!-- 詳細統計（折りたたみ可能） -->
    <div v-if="grassData" class="grass-detailed-stats">
      <button @click="showDetailedStats = !showDetailedStats" class="stats-toggle">
        詳細統計 {{ showDetailedStats ? '▼' : '▶' }}
      </button>
      
      <div v-if="showDetailedStats" class="detailed-stats-content">
        <div class="stats-placeholder">
          詳細統計機能（今後実装予定）
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue'
import ApiClient from '../utils/ApiClient.js'
import ApiRetry from '../utils/ApiRetry.js'
import ErrorHandler from '../utils/ErrorHandler.js'
import GrassCalendarUtils from '../utils/GrassCalendarUtils.js'
import GrassTooltip from './GrassTooltip.vue'

export default {
  name: 'StudyGrassChart',
  components: {
    GrassTooltip
  },
  props: {
    initialYear: {
      type: Number,
      default: () => new Date().getFullYear()
    },
    autoLoad: {
      type: Boolean,
      default: true
    }
  },
  emits: ['dayClick', 'dataLoaded', 'error'],
  setup(props, { emit }) {
    // リアクティブデータ
    const selectedYear = ref(props.initialYear)
    const grassData = ref(null)
    const loading = ref(false)
    const error = ref(null)
    const showDetailedStats = ref(false)
    
    const tooltip = reactive({
      visible: false,
      data: null,
      position: { x: 0, y: 0 }
    })

    // API クライアント
    const apiRetry = new ApiRetry()

    // 利用可能な年のリスト
    const availableYears = computed(() => {
      const currentYear = new Date().getFullYear()
      const years = []
      for (let year = currentYear - 2; year <= currentYear; year++) {
        years.push(year)
      }
      return years
    })

    // 月ラベル（GitHub風 - 適度に間引いて表示）
    const monthLabels = computed(() => {
      if (!grassData.value || !grassData.value.weeks) {
        return []
      }
      
      const weeks = grassData.value.weeks
      const labels = Array(weeks.length).fill('')
      
      // 表示する月を選択（3ヶ月おき: 1月、4月、7月、10月）
      const displayMonths = [1, 4, 7, 10] 
      
      for (let month of displayMonths) {
        // その月の1日が含まれる週を見つける
        for (let weekIndex = 0; weekIndex < weeks.length; weekIndex++) {
          const week = weeks[weekIndex]
          const hasFirstDayOfMonth = week.some(day => {
            if (!day || day.isEmpty || !day.isCurrentYear) return false
            const date = new Date(day.date)
            return date.getMonth() === month - 1 && date.getDate() <= 7
          })
          
          if (hasFirstDayOfMonth && !labels[weekIndex]) {
            labels[weekIndex] = GrassCalendarUtils.getMonthName(month)
            break
          }
        }
      }
      
      return labels
    })

    // 曜日ラベル
    const dayLabels = computed(() => {
      const days = GrassCalendarUtils.getDayNames()
      return ['', days[1], '', days[3], '', days[5], ''] // 月、水、金のみ表示、適切な位置に配置
    })

    // データ読み込み
    const loadYearData = async () => {
      if (loading.value) return

      loading.value = true
      error.value = null

      try {
        const startDate = `${selectedYear.value}-01-01`
        const endDate = `${selectedYear.value}-12-31`
        
        const response = await apiRetry.getGrassDataWithRetry(
          ApiClient,
          startDate,
          endDate
        )

        if (response.success) {
          grassData.value = GrassCalendarUtils.generateYearData(
            response.data,
            selectedYear.value
          )
          emit('dataLoaded', grassData.value)
        } else {
          throw new Error(response.message || 'データの取得に失敗しました')
        }
      } catch (err) {
        error.value = ErrorHandler.handleGrassError(err, 'データ読み込み').message
        emit('error', err)
      } finally {
        loading.value = false
      }
    }

    // データ更新
    const refreshData = async () => {
      try {
        // キャッシュクリア
        await ApiClient.clearGrassCache()
        await loadYearData()
      } catch (err) {
        error.value = ErrorHandler.handleGrassError(err, 'データ更新').message
      }
    }

    // 草セルのCSSクラス取得
    const getGrassCellClass = (day) => {
      const classes = ['grass-cell']
      
      if (day.isEmpty) {
        classes.push('grass-empty')
      } else {
        classes.push(GrassCalendarUtils.getGrassLevelClass(day.level))
        if (!day.isCurrentYear) {
          classes.push('grass-other-year')
        }
      }
      
      return classes
    }

    // 草セルのスタイル取得
    const getGrassCellStyle = (day) => {
      if (day.isEmpty || !day.isCurrentYear) {
        return { backgroundColor: 'var(--color-muted-gray)' }
      }
      
      return {
        backgroundColor: getGrassColor(day.level)
      }
    }

    // 草レベルのクラス取得
    const getGrassLevelClass = (level) => {
      return GrassCalendarUtils.getGrassLevelClass(level)
    }

    // 草の色取得
    const getGrassColor = (level) => {
      const colors = GrassCalendarUtils.getGrassColors()
      return colors[level] || colors[0]
    }

    // 日付クリックハンドラ
    const handleDayClick = (day) => {
      if (day.isEmpty || !day.isCurrentYear) return
      emit('dayClick', day)
    }

    // ツールチップ表示
    const showTooltip = (day, event) => {
      if (day.isEmpty || !day.isCurrentYear) return
      
      tooltip.data = day
      tooltip.position = {
        x: event.clientX,
        y: event.clientY
      }
      tooltip.visible = true
    }

    // ツールチップ非表示
    const hideTooltip = () => {
      tooltip.visible = false
      tooltip.data = null
    }

    // マウント時の処理
    onMounted(() => {
      if (props.autoLoad) {
        loadYearData()
      }
    })

    return {
      selectedYear,
      grassData,
      loading,
      error,
      showDetailedStats,
      tooltip,
      availableYears,
      monthLabels,
      dayLabels,
      loadYearData,
      refreshData,
      getGrassCellClass,
      getGrassCellStyle,
      getGrassLevelClass,
      getGrassColor,
      handleDayClick,
      showTooltip,
      hideTooltip
    }
  }
}
</script>

<style scoped>
.study-grass-chart {
  width: 100%;
  max-width: 100%;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--color-muted-gray);
  padding: 24px;
  overflow: hidden;
}

.grass-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.grass-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-muted-blue-dark);
}

.grass-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.year-selector {
  padding: 4px 12px;
  border: 1px solid var(--color-muted-gray);
  border-radius: 6px;
  font-size: 0.875rem;
  background-color: white;
}

.year-selector:focus {
  outline: none;
  box-shadow: 0 0 0 2px var(--color-muted-blue);
}

.refresh-btn {
  padding: 4px 12px;
  background: var(--color-muted-blue);
  color: white;
  font-size: 0.875rem;
  border-radius: 6px;
  border: none;
  cursor: pointer;
}

.refresh-btn:hover {
  background: var(--color-muted-blue-dark);
}

.refresh-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-message {
  background: var(--color-muted-pink-light);
  border: 1px solid var(--color-muted-pink);
  color: var(--color-muted-pink-dark);
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 16px;
}

.retry-btn {
  margin-left: 8px;
  padding: 4px 8px;
  background: var(--color-muted-pink-light);
  color: var(--color-muted-pink-dark);
  font-size: 0.875rem;
  border-radius: 4px;
  border: none;
  cursor: pointer;
}

.retry-btn:hover {
  background: var(--color-muted-pink);
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 0;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  border: 4px solid var(--color-muted-blue-light);
  border-top: 4px solid var(--color-muted-blue);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 8px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.grass-stats-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 24px;
  font-size: 0.875rem;
  color: var(--color-muted-gray-dark);
}

.stat-item {
  background: var(--color-muted-white);
  padding: 4px 12px;
  border-radius: 9999px;
  border: 1px solid var(--color-muted-gray);
}

.grass-calendar {
  margin-bottom: 16px;
  width: 100%;
  overflow: hidden;
}

.month-labels-container {
  margin-bottom: 8px;
  display: flex;
  width: 100%;
}

.month-labels-spacer {
  flex-shrink: 0;
  width: 24px;
}

.month-labels-scroll {
  flex: 1;
  overflow: hidden;
}

.month-labels {
  display: flex;
  gap: 2px;
  width: 100%;
}

.month-label {
  font-size: 0.75rem;
  color: var(--color-muted-gray-dark);
  text-align: left;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
  height: 16px;
  line-height: 16px;
}

.calendar-container {
  display: flex;
  width: 100%;
}

.day-labels {
  display: flex;
  flex-direction: column;
  margin-right: 8px;
  width: 16px;
  flex-shrink: 0;
}

.day-label {
  font-size: 0.75rem;
  color: var(--color-muted-gray-dark);
  display: flex;
  align-items: center;
  margin-bottom: 2px;
  flex: 1;
}

.grass-grid {
  display: flex;
  gap: 2px;
  flex: 1;
  width: 100%;
}

.grass-week {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.grass-cell {
  width: 100%;
  aspect-ratio: 1;
  min-width: 10px;
  max-width: 16px;
  border-radius: 2px;
  cursor: pointer;
  border: 1px solid var(--color-muted-gray);
  transition: all 0.2s;
}

.grass-cell:hover {
  box-shadow: 0 0 0 2px var(--color-muted-blue), 0 0 0 4px rgba(123, 167, 188, 0.3);
}

.grass-empty {
  cursor: default;
}

.grass-empty:hover {
  box-shadow: none;
}

.grass-other-year {
  opacity: 0.3;
}

.grass-level-0 {
  background: var(--color-grass-0);
}

.grass-level-1 {
  background: var(--color-grass-1);
}

.grass-level-2 {
  background: var(--color-grass-2);
}

.grass-level-3 {
  background: var(--color-grass-3);
}

.grass-legend {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  font-size: 0.75rem;
  color: var(--color-muted-gray-dark);
}

.legend-label {
  color: var(--color-muted-gray-dark);
}

.legend-items {
  display: flex;
  gap: 4px;
}

.legend-cell {
  width: 12px;
  height: 12px;
  border-radius: 2px;
  border: 1px solid var(--color-muted-gray);
}

.grass-detailed-stats {
  margin-top: 24px;
  border-top: 1px solid var(--color-muted-gray);
  padding-top: 16px;
}

.stats-toggle {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-muted-blue-dark);
  cursor: pointer;
  border: none;
  background: none;
}

.stats-toggle:hover {
  color: var(--color-muted-blue);
}

.stats-toggle:focus {
  outline: none;
}

.detailed-stats-content {
  margin-top: 16px;
}

.stats-placeholder {
  padding: 16px;
  text-align: center;
  color: var(--color-muted-gray-dark);
  background: var(--color-muted-white);
  border-radius: 8px;
  border: 1px solid var(--color-muted-gray);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>