<template>
  <div class="grass-calendar">
    <!-- ヘッダー -->
    <div class="calendar-header">
      <div class="calendar-title">
        <h3>{{ title }}</h3>
        <p v-if="subtitle" class="calendar-subtitle">{{ subtitle }}</p>
      </div>
      
      <div class="calendar-controls">
        <button
          v-if="showPrevButton"
          @click="$emit('previous')"
          :disabled="!canGoPrevious"
          class="nav-button"
        >
          ‹ 前
        </button>
        
        <button
          v-if="showNextButton"
          @click="$emit('next')"
          :disabled="!canGoNext"
          class="nav-button"
        >
          次 ›
        </button>
        
        <button
          v-if="showTodayButton"
          @click="$emit('today')"
          class="today-button"
        >
          今日
        </button>
      </div>
    </div>

    <!-- エラー表示 -->
    <div v-if="error" class="error-display">
      {{ error }}
    </div>

    <!-- ローディング表示 -->
    <div v-if="loading" class="loading-display">
      <div class="loading-spinner"></div>
      <span>読み込み中...</span>
    </div>

    <!-- カレンダー本体 -->
    <div v-else-if="calendarData" class="calendar-body">
      <!-- 曜日ヘッダー -->
      <div v-if="showDayHeaders" class="day-headers">
        <div
          v-for="dayName in dayNames"
          :key="dayName"
          class="day-header"
        >
          {{ dayName }}
        </div>
      </div>

      <!-- カレンダーグリッド -->
      <div class="calendar-grid" :class="gridClasses">
        <div
          v-for="(week, weekIndex) in calendarData.weeks"
          :key="weekIndex"
          class="calendar-week"
        >
          <div
            v-for="(day, dayIndex) in week"
            :key="`${weekIndex}-${dayIndex}`"
            :class="getDayCellClass(day)"
            :style="getDayCellStyle(day)"
            @click="handleDayClick(day)"
            @mouseenter="handleDayHover(day, $event)"
            @mouseleave="handleDayLeave(day)"
            class="calendar-day"
            :title="getDayTitle(day)"
            :aria-label="getDayAriaLabel(day)"
          >
            <!-- 日付表示（月表示の場合） -->
            <span v-if="showDayNumbers && day.day" class="day-number">
              {{ day.day }}
            </span>
            
            <!-- 学習時間表示（オプション） -->
            <span v-if="showTimeInCell && day.total_minutes > 0" class="day-time">
              {{ formatTimeShort(day.total_minutes) }}
            </span>
          </div>
        </div>
      </div>

      <!-- 統計サマリー -->
      <div v-if="showStats && calendarData.stats" class="calendar-stats">
        <div class="stats-row">
          <div class="stat-item">
            <span class="stat-label">学習日数</span>
            <span class="stat-value">{{ calendarData.stats.studyDays }}日</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">学習時間</span>
            <span class="stat-value">{{ calendarData.stats.totalHours }}h</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">連続記録</span>
            <span class="stat-value">{{ calendarData.stats.currentStreak }}日</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 凡例 -->
    <div v-if="showLegend" class="calendar-legend">
      <span class="legend-label">少ない</span>
      <div class="legend-scale">
        <div
          v-for="level in [0, 1, 2, 3]"
          :key="level"
          :class="getGrassLevelClass(level)"
          :style="{ backgroundColor: getGrassColor(level) }"
          class="legend-cell"
          :title="getLevelDescription(level)"
        ></div>
      </div>
      <span class="legend-label">多い</span>
    </div>

    <!-- ツールチップ -->
    <GrassTooltip
      v-if="tooltip.visible && tooltip.data"
      :day-data="tooltip.data"
      :position="tooltip.position"
      @close="hideTooltip"
    />
  </div>
</template>

<script>
import { ref, reactive, computed } from 'vue'
import GrassCalendarUtils from '../utils/GrassCalendarUtils.js'
import GrassTooltip from './GrassTooltip.vue'

export default {
  name: 'GrassCalendar',
  components: {
    GrassTooltip
  },
  props: {
    // データ
    calendarData: {
      type: Object,
      default: null
    },
    loading: {
      type: Boolean,
      default: false
    },
    error: {
      type: String,
      default: null
    },
    
    // 表示設定
    title: {
      type: String,
      default: '学習カレンダー'
    },
    subtitle: {
      type: String,
      default: null
    },
    viewMode: {
      type: String,
      default: 'year', // 'year' | 'month'
      validator: (value) => ['year', 'month'].includes(value)
    },
    
    // UI制御
    showDayHeaders: {
      type: Boolean,
      default: true
    },
    showDayNumbers: {
      type: Boolean,
      default: false
    },
    showTimeInCell: {
      type: Boolean,
      default: false
    },
    showStats: {
      type: Boolean,
      default: true
    },
    showLegend: {
      type: Boolean,
      default: true
    },
    showPrevButton: {
      type: Boolean,
      default: true
    },
    showNextButton: {
      type: Boolean,
      default: true
    },
    showTodayButton: {
      type: Boolean,
      default: true
    },
    
    // 制限
    canGoPrevious: {
      type: Boolean,
      default: true
    },
    canGoNext: {
      type: Boolean,
      default: true
    },
    
    // カスタマイズ
    cellSize: {
      type: String,
      default: 'medium', // 'small' | 'medium' | 'large'
      validator: (value) => ['small', 'medium', 'large'].includes(value)
    }
  },
  emits: ['dayClick', 'dayHover', 'dayLeave', 'previous', 'next', 'today'],
  setup(props, { emit }) {
    const tooltip = reactive({
      visible: false,
      data: null,
      position: { x: 0, y: 0 }
    })

    // 曜日名
    const dayNames = computed(() => {
      if (props.viewMode === 'year') {
        // 年表示の場合は省略形
        return ['月', '', '水', '', '金', '']
      } else {
        // 月表示の場合は全て表示
        return GrassCalendarUtils.getDayNames()
      }
    })

    // グリッドのクラス
    const gridClasses = computed(() => {
      const classes = [`calendar-${props.viewMode}`]
      
      if (props.cellSize) {
        classes.push(`cell-size-${props.cellSize}`)
      }
      
      return classes
    })

    // 日付セルのクラス取得
    const getDayCellClass = (day) => {
      const classes = ['calendar-day']
      
      if (day.isEmpty) {
        classes.push('day-empty')
        return classes
      }
      
      // 草レベル
      classes.push(getGrassLevelClass(day.level))
      
      // 現在年以外
      if (day.isCurrentYear === false) {
        classes.push('day-other-year')
      }
      
      // 今日
      const today = new Date().toISOString().split('T')[0]
      if (day.date === today) {
        classes.push('day-today')
      }
      
      // 週末
      if (day.dayOfWeek === 0 || day.dayOfWeek === 6) {
        classes.push('day-weekend')
      }
      
      return classes
    }

    // 日付セルのスタイル取得
    const getDayCellStyle = (day) => {
      if (day.isEmpty || day.isCurrentYear === false) {
        return { backgroundColor: '#ebedf0' }
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

    // レベルの説明取得
    const getLevelDescription = (level) => {
      return GrassCalendarUtils.getLevelDescription(level)
    }

    // 日付タイトル取得
    const getDayTitle = (day) => {
      if (day.isEmpty) return ''
      return GrassCalendarUtils.generateTooltipText(day)
    }

    // アクセシビリティ用ラベル
    const getDayAriaLabel = (day) => {
      if (day.isEmpty) return '空のセル'
      
      const date = new Date(day.date).toLocaleDateString('ja-JP', {
        month: 'long',
        day: 'numeric'
      })
      
      if (day.total_minutes === 0) {
        return `${date} 学習記録なし`
      }
      
      return `${date} 学習時間${day.total_minutes}分`
    }

    // 短い時間フォーマット
    const formatTimeShort = (minutes) => {
      if (minutes < 60) {
        return `${minutes}m`
      }
      
      const hours = Math.floor(minutes / 60)
      if (minutes % 60 === 0) {
        return `${hours}h`
      }
      
      return `${hours}h${minutes % 60}m`
    }

    // イベントハンドラ
    const handleDayClick = (day) => {
      if (day.isEmpty) return
      emit('dayClick', day)
    }

    const handleDayHover = (day, event) => {
      if (day.isEmpty) return
      
      tooltip.data = day
      tooltip.position = {
        x: event.clientX,
        y: event.clientY
      }
      tooltip.visible = true
      
      emit('dayHover', day, event)
    }

    const handleDayLeave = (day) => {
      if (day.isEmpty) return
      
      tooltip.visible = false
      tooltip.data = null
      
      emit('dayLeave', day)
    }

    const hideTooltip = () => {
      tooltip.visible = false
      tooltip.data = null
    }

    return {
      tooltip,
      dayNames,
      gridClasses,
      getDayCellClass,
      getDayCellStyle,
      getGrassLevelClass,
      getGrassColor,
      getLevelDescription,
      getDayTitle,
      getDayAriaLabel,
      formatTimeShort,
      handleDayClick,
      handleDayHover,
      handleDayLeave,
      hideTooltip
    }
  }
}
</script>

<style scoped>
.grass-calendar {
  @apply w-full;
}

.calendar-header {
  @apply flex justify-between items-start mb-4;
}

.calendar-title h3 {
  @apply text-lg font-semibold text-gray-900;
}

.calendar-subtitle {
  @apply text-sm text-gray-600 mt-1;
}

.calendar-controls {
  @apply flex items-center gap-2;
}

.nav-button {
  @apply px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed;
}

.today-button {
  @apply px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700;
}

.error-display {
  @apply bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4;
}

.loading-display {
  @apply flex items-center justify-center gap-2 py-12 text-gray-500;
}

.loading-spinner {
  @apply w-5 h-5 border-2 border-gray-300 border-t-blue-600 rounded-full animate-spin;
}

.calendar-body {
  @apply space-y-4;
}

.day-headers {
  @apply grid grid-cols-7 gap-1 mb-2;
}

.day-header {
  @apply text-xs text-gray-500 text-center py-1;
}

.calendar-grid.calendar-year {
  @apply flex gap-1;
}

.calendar-grid.calendar-month {
  @apply grid gap-1;
}

.calendar-week {
  @apply flex flex-col gap-1;
}

.calendar-month .calendar-week {
  @apply grid grid-cols-7 gap-1;
}

.calendar-day {
  @apply relative rounded-sm border border-gray-200 cursor-pointer transition-all duration-200 flex items-center justify-center;
}

.calendar-day:hover:not(.day-empty) {
  @apply ring-2 ring-blue-300 ring-opacity-50 scale-110 z-10;
}

.day-empty {
  @apply cursor-default opacity-30;
}

.day-empty:hover {
  @apply ring-0 scale-100;
}

.day-other-year {
  @apply opacity-30;
}

.day-today {
  @apply ring-2 ring-blue-500;
}

.day-weekend {
  @apply opacity-80;
}

/* セルサイズ */
.cell-size-small .calendar-day {
  @apply w-2 h-2;
}

.cell-size-medium .calendar-day {
  @apply w-3 h-3;
}

.cell-size-large .calendar-day {
  @apply w-4 h-4;
}

/* 月表示の場合のセルサイズ */
.calendar-month.cell-size-small .calendar-day {
  @apply w-8 h-8;
}

.calendar-month.cell-size-medium .calendar-day {
  @apply w-10 h-10;
}

.calendar-month.cell-size-large .calendar-day {
  @apply w-12 h-12;
}

.day-number {
  @apply text-xs font-medium;
}

.day-time {
  @apply text-xs absolute bottom-0 right-0 bg-black bg-opacity-75 text-white px-1 rounded-tl;
}

.grass-level-0 {
  @apply bg-gray-100;
}

.grass-level-1 {
  @apply bg-green-200;
}

.grass-level-2 {
  @apply bg-green-400;
}

.grass-level-3 {
  @apply bg-green-600;
}

.calendar-stats {
  @apply bg-gray-50 rounded-lg p-3;
}

.stats-row {
  @apply flex justify-around;
}

.stat-item {
  @apply text-center;
}

.stat-label {
  @apply block text-xs text-gray-500;
}

.stat-value {
  @apply block text-sm font-semibold text-gray-900;
}

.calendar-legend {
  @apply flex items-center justify-end gap-2 text-xs text-gray-500 mt-4;
}

.legend-label {
  @apply text-gray-500;
}

.legend-scale {
  @apply flex gap-1;
}

.legend-cell {
  @apply w-3 h-3 rounded-sm border border-gray-200;
}
</style>