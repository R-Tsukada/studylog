<template>
  <div class="study-calendar">
    <!-- ヘッダー -->
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-gray-800">🌱 学習カレンダー</h3>
      <div class="text-sm text-gray-600" v-if="!loading && calendarData">
        過去1年間で {{ calendarData.total_study_days }}日学習しました
      </div>
    </div>

    <!-- ローディング -->
    <div v-if="loading" class="text-center py-8">
      <div class="text-gray-500">カレンダーを読み込み中...</div>
    </div>

    <!-- カレンダー本体 -->
    <div v-else-if="calendarData" class="calendar-container">
      <!-- 月ラベル（シンプルな横スクロール対応） -->
      <div class="month-labels-container">
        <div class="month-labels-spacer"></div>
        <div class="month-labels-wrapper">
          <div class="month-labels-scroll">
            <div 
              v-for="month in compactMonthLabels" 
              :key="month.month"
              class="month-label"
            >
              {{ month.name }}
            </div>
          </div>
        </div>
      </div>

      <!-- カレンダー本体（曜日ラベルとグリッド） -->
      <div class="calendar-main">
        <!-- 曜日ラベル -->
        <div class="weekday-labels">
          <div class="text-xs text-gray-600">Mon</div>
          <div class="text-xs text-gray-600"></div>
          <div class="text-xs text-gray-600">Wed</div>
          <div class="text-xs text-gray-600"></div>
          <div class="text-xs text-gray-600">Fri</div>
          <div class="text-xs text-gray-600"></div>
          <div class="text-xs text-gray-600"></div>
        </div>

        <!-- カレンダーグリッド（横スクロール対応） -->
        <div class="calendar-grid-wrapper">
          <div class="calendar-grid">
            <div 
              v-for="(day, index) in calendarData.calendar_data" 
              :key="day.date"
              class="calendar-day"
              :class="getDayColorClass(day.level)"
              :title="getTooltip(day)"
              @mouseenter="showTooltip($event, day)"
              @mouseleave="hideTooltip"
            >
            </div>
          </div>
        </div>
      </div>

      <!-- レベル説明 -->
      <div class="level-legend flex items-center justify-between mt-4">
        <div class="text-xs text-gray-600">少ない</div>
        <div class="flex items-center gap-1">
          <div class="legend-square level-0"></div>
          <div class="legend-square level-1"></div>
          <div class="legend-square level-2"></div>
          <div class="legend-square level-3"></div>
          <div class="legend-square level-4"></div>
        </div>
        <div class="text-xs text-gray-600">多い</div>
      </div>
    </div>

    <!-- カスタムツールチップ -->
    <div 
      v-if="tooltip.show" 
      class="custom-tooltip"
      :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }"
    >
      <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg">
        <div class="font-medium">{{ tooltip.date }}</div>
        <div v-if="tooltip.minutes > 0">
          {{ tooltip.formattedTime }} ({{ tooltip.sessionCount }}セッション)
        </div>
        <div v-else class="text-gray-400">学習記録なし</div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'StudyCalendar',
  data() {
    return {
      loading: false,
      calendarData: null,
      tooltip: {
        show: false,
        x: 0,
        y: 0,
        date: '',
        minutes: 0,
        sessionCount: 0,
        formattedTime: ''
      }
    }
  },
  computed: {
    compactMonthLabels() {
      if (!this.calendarData) return []
      
      const labels = []
      let currentMonth = null
      
      this.calendarData.calendar_data.forEach((day, index) => {
        if (day.month !== currentMonth) {
          const weekIndex = Math.floor(index / 7)
          labels.push({
            month: day.month,
            name: this.getMonthName(day.month),
            weekIndex: weekIndex
          })
          currentMonth = day.month
        }
      })
      
      return labels
    }
  },
  async mounted() {
    await this.loadCalendarData()
  },
  methods: {
    async loadCalendarData() {
      this.loading = true
      try {
        const response = await axios.get('/api/dashboard/study-calendar')
        if (response.data.success) {
          this.calendarData = response.data.data
        }
      } catch (error) {
        console.error('学習カレンダー取得エラー:', error)
      } finally {
        this.loading = false
      }
    },

    getDayColorClass(level) {
      return `level-${level}`
    },

    getTooltip(day) {
      const date = new Date(day.date).toLocaleDateString('ja-JP')
      if (day.minutes > 0) {
        return `${date}: ${day.formatted_time} (${day.session_count}セッション)`
      }
      return `${date}: 学習記録なし`
    },

    showTooltip(event, day) {
      this.tooltip = {
        show: true,
        x: event.pageX + 10,
        y: event.pageY - 10,
        date: new Date(day.date).toLocaleDateString('ja-JP'),
        minutes: day.minutes,
        sessionCount: day.session_count,
        formattedTime: day.formatted_time
      }
    },

    hideTooltip() {
      this.tooltip.show = false
    },

    getMonthName(month) {
      const months = [
        '', '1月', '2月', '3月', '4月', '5月', '6月',
        '7月', '8月', '9月', '10月', '11月', '12月'
      ]
      return months[month]
    }
  }
}
</script>

<style scoped>
.study-calendar {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.calendar-container {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
  max-width: 100%;
  overflow: hidden;
}

.month-labels-container {
  margin-bottom: 8px;
  display: flex;
  align-items: center;
}

.month-labels-spacer {
  width: 32px;
  height: 18px;
  flex-shrink: 0;
}

.month-labels-wrapper {
  flex: 1;
  overflow: hidden;
}

.month-labels-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
  scrollbar-width: thin;
}

.month-label {
  font-size: 0.75rem;
  color: #6b7280;
  white-space: nowrap;
  flex-shrink: 0;
  padding: 2px 6px;
  background: #f3f4f6;
  border-radius: 4px;
}

.calendar-main {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  position: relative;
  max-width: 100%;
}

.weekday-labels {
  display: grid;
  grid-template-rows: repeat(7, 12px);
  gap: 2px;
  width: 24px;
  text-align: right;
  flex-shrink: 0;
  z-index: 10;
  background: white;
  padding-right: 4px;
}

.calendar-grid-wrapper {
  flex: 1;
  overflow-x: auto;
  overflow-y: hidden;
  max-width: calc(100vw - 120px);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(53, 12px); /* 53週 */
  grid-template-rows: repeat(7, 12px); /* 7曜日 */
  gap: 2px;
  width: max-content;
  min-width: 100%;
}

.calendar-day {
  width: 12px;
  height: 12px;
  border-radius: 2px;
  cursor: pointer;
  transition: all 0.1s ease;
}

.calendar-day:hover {
  transform: scale(1.1);
  border: 1px solid rgba(27, 31, 35, 0.15);
  border-radius: 3px;
}

/* レベル別の色 */
.level-0 {
  background-color: #ebedf0;
}

.level-1 {
  background-color: #9be9a8;
}

.level-2 {
  background-color: #40c463;
}

.level-3 {
  background-color: #30a14e;
}

.level-4 {
  background-color: #216e39;
}

.level-legend {
  margin-top: 8px;
}

.legend-square {
  width: 10px;
  height: 10px;
  border-radius: 2px;
}

.custom-tooltip {
  position: fixed;
  z-index: 1000;
  pointer-events: none;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
  .study-calendar {
    padding: 1rem;
  }
  
  .calendar-grid {
    grid-template-columns: repeat(53, 10px);
    grid-template-rows: repeat(7, 10px);
    gap: 1px;
  }
  
  .calendar-day {
    width: 10px;
    height: 10px;
  }
  
  .weekday-labels {
    width: 20px;
    grid-template-rows: repeat(7, 10px);
    gap: 1px;
  }
  
  .month-labels-spacer {
    width: 24px;
  }
  
  .calendar-grid-wrapper {
    max-width: calc(100vw - 80px);
  }
  
  .month-label {
    font-size: 0.625rem;
    padding: 1px 4px;
  }
}

/* スクロールバーのスタイリング */
.calendar-grid-wrapper::-webkit-scrollbar,
.month-labels-scroll::-webkit-scrollbar {
  height: 6px;
}

.calendar-grid-wrapper::-webkit-scrollbar-track,
.month-labels-scroll::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.calendar-grid-wrapper::-webkit-scrollbar-thumb,
.month-labels-scroll::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.calendar-grid-wrapper::-webkit-scrollbar-thumb:hover,
.month-labels-scroll::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>