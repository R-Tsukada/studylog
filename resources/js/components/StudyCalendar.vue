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
      <!-- 月ラベル（正確な位置配置） -->
      <div class="month-labels-container">
        <div class="month-labels-spacer"></div>
        <div class="month-labels-wrapper">
          <div class="month-labels-positioned">
            <div 
              v-for="month in compactMonthLabels" 
              :key="month.month"
              class="month-label-positioned"
              :style="{ left: month.leftPosition + 'px' }"
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
        <div class="calendar-grid-wrapper" @scroll="syncScroll">
          <div class="calendar-grid">
            <div 
              v-for="(day, index) in calendarGrid" 
              :key="day ? day.date : `empty-${index}`"
              class="calendar-day"
              :class="day ? getDayColorClass(day.level) : 'level-empty'"
              :title="day ? getTooltip(day) : ''"
              @mouseenter="day && showTooltip($event, day)"
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
    // 365日のデータを53週×7日のグリッドに変換
    calendarGrid() {
      if (!this.calendarData) return []
      
      const grid = []
      const data = this.calendarData.calendar_data
      
      // 53週分の配列を初期化
      for (let week = 0; week < 53; week++) {
        grid[week] = new Array(7).fill(null)
      }
      
      // 各日付を正しい位置に配置
      data.forEach((day) => {
        const dayIndex = data.indexOf(day)
        const startDayOfWeek = data[0].day_of_week // 最初の日の曜日
        
        // 月曜日を0として調整（GitHubスタイル）
        const adjustedDayOfWeek = (day.day_of_week + 6) % 7 // 日曜日0 → 月曜日0に変換
        const adjustedStartDayOfWeek = (startDayOfWeek + 6) % 7
        
        // 週番号を計算
        const weekIndex = Math.floor((dayIndex + adjustedStartDayOfWeek) / 7)
        
        if (weekIndex < 53) {
          grid[weekIndex][adjustedDayOfWeek] = day
        }
      })
      
      // グリッド表示用に1次元配列に変換（週ごとに並ぶ）
      const flatGrid = []
      for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) {
        for (let week = 0; week < 53; week++) {
          flatGrid.push(grid[week][dayOfWeek])
        }
      }
      
      return flatGrid
    },
    
    compactMonthLabels() {
      if (!this.calendarData) return []
      
      const labels = []
      const data = this.calendarData.calendar_data
      const startDayOfWeek = data[0].day_of_week
      const adjustedStartDayOfWeek = (startDayOfWeek + 6) % 7
      
      // 各月の最初の日を見つけて、その週番号を計算
      let currentMonthYear = null
      data.forEach((day, index) => {
        // 年月の組み合わせで比較（同じ月でも年が違えば別扱い）
        const monthYear = `${new Date(day.date).getFullYear()}-${day.month}`
        
        if (monthYear !== currentMonthYear) {
          const weekIndex = Math.floor((index + adjustedStartDayOfWeek) / 7)
          
          // レスポンシブ対応: モバイルかどうかを判定
          const isMobile = window.innerWidth <= 768
          const weekWidth = isMobile ? 11 : 14 // モバイル: 10px + 1px gap, デスクトップ: 12px + 2px gap
          
          labels.push({
            month: day.month,
            year: new Date(day.date).getFullYear(),
            name: this.getMonthName(day.month),
            weekIndex: weekIndex,
            leftPosition: weekIndex * weekWidth
          })
          currentMonthYear = monthYear
        }
      })
      
      return labels
    }
  },
  async mounted() {
    await this.loadCalendarData()
    
    // ウィンドウリサイズ時に月ラベル位置を再計算
    window.addEventListener('resize', this.handleResize)
    
    // 初期表示時に最新部分（右端）にスクロール
    this.$nextTick(() => {
      this.scrollToLatest()
    })
  },
  
  beforeUnmount() {
    window.removeEventListener('resize', this.handleResize)
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
    },
    
    handleResize() {
      // リサイズ時にcomputed propertiesを再計算させるため、
      // 強制的にリアクティブな更新をトリガー
      this.$forceUpdate()
    },
    
    scrollToLatest() {
      // カレンダーグリッドと月ラベルを最新部分（右端）にスクロール
      const calendarWrapper = this.$el.querySelector('.calendar-grid-wrapper')
      const monthWrapper = this.$el.querySelector('.month-labels-wrapper')
      
      if (calendarWrapper) {
        calendarWrapper.scrollLeft = calendarWrapper.scrollWidth - calendarWrapper.clientWidth
      }
      
      if (monthWrapper) {
        monthWrapper.scrollLeft = monthWrapper.scrollWidth - monthWrapper.clientWidth
      }
    },
    
    syncScroll(event) {
      // カレンダーグリッドのスクロールに合わせて月ラベルもスクロール
      const monthWrapper = this.$el.querySelector('.month-labels-wrapper')
      if (monthWrapper) {
        monthWrapper.scrollLeft = event.target.scrollLeft
      }
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
  overflow-x: auto;
  overflow-y: hidden;
  max-width: calc(100vw - 120px);
}

.month-labels-positioned {
  position: relative;
  height: 20px;
  width: calc(53 * 14px); /* 53週 × (12px + 2px gap) */
  min-width: 100%;
}

.month-label-positioned {
  position: absolute;
  top: 0;
  font-size: 0.75rem;
  color: #6b7280;
  white-space: nowrap;
  padding: 2px 6px;
  background: #f3f4f6;
  border-radius: 4px;
  z-index: 1;
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
  grid-template-rows: repeat(7, 12px); /* 7曜日（月-日） */
  grid-template-columns: repeat(53, 12px); /* 53週 */
  grid-auto-flow: column; /* 列方向に優先して配置 */
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
.level-empty {
  background-color: transparent;
  cursor: default;
}

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
    grid-template-rows: repeat(7, 10px);
    grid-template-columns: repeat(53, 10px);
    grid-auto-flow: column;
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
  
  .month-labels-positioned {
    width: calc(53 * 12px); /* モバイルでは12px間隔 */
  }
  
  .month-label-positioned {
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