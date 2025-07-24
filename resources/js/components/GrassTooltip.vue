<template>
  <div
    v-if="dayData && !dayData.isEmpty"
    :style="tooltipStyle"
    class="grass-tooltip"
    role="tooltip"
    aria-live="polite"
  >
    <div class="tooltip-content">
      <!-- 日付 -->
      <div class="tooltip-date">
        {{ formatDate(dayData.date) }}
      </div>
      
      <!-- 学習時間情報 -->
      <div v-if="dayData.total_minutes > 0" class="tooltip-study-info">
        <div class="total-time">
          <span class="time-label">合計学習時間:</span>
          <span class="time-value">{{ formatTime(dayData.total_minutes) }}</span>
        </div>
        
        <!-- 詳細内訳 -->
        <div v-if="hasDetails" class="time-breakdown">
          <div v-if="dayData.study_session_minutes > 0" class="breakdown-item">
            <span class="method-icon">⏱️</span>
            <span class="method-label">自由時間計測:</span>
            <span class="method-value">{{ formatTime(dayData.study_session_minutes) }}</span>
          </div>
          
          <div v-if="dayData.pomodoro_minutes > 0" class="breakdown-item">
            <span class="method-icon">🍅</span>
            <span class="method-label">ポモドーロ:</span>
            <span class="method-value">{{ formatTime(dayData.pomodoro_minutes) }}</span>
          </div>
        </div>
        
        <!-- セッション情報 -->
        <div v-if="hasSessionInfo" class="session-info">
          <div v-if="dayData.session_count > 0" class="session-item">
            <span class="session-label">学習セッション:</span>
            <span class="session-value">{{ dayData.session_count }}回</span>
          </div>
          
          <div v-if="dayData.focus_sessions > 0" class="session-item">
            <span class="session-label">フォーカスセッション:</span>
            <span class="session-value">{{ dayData.focus_sessions }}回</span>
          </div>
        </div>
        
        <!-- 学習レベル -->
        <div class="study-level">
          <span class="level-indicator" :class="levelClass"></span>
          <span class="level-text">{{ levelDescription }}</span>
        </div>
      </div>
      
      <!-- 学習なしの場合 -->
      <div v-else class="no-study">
        <span class="no-study-text">この日は学習記録がありません</span>
      </div>
    </div>
    
    <!-- 三角形の矢印 -->
    <div class="tooltip-arrow" :class="arrowPosition"></div>
  </div>
</template>

<script>
import { computed } from 'vue'
import GrassCalendarUtils from '../utils/GrassCalendarUtils.js'

export default {
  name: 'GrassTooltip',
  props: {
    dayData: {
      type: Object,
      required: true
    },
    position: {
      type: Object,
      required: true,
      validator: (value) => {
        return typeof value.x === 'number' && typeof value.y === 'number'
      }
    }
  },
  emits: ['close'],
  setup(props) {
    // 詳細があるかどうか
    const hasDetails = computed(() => {
      return props.dayData.study_session_minutes > 0 || props.dayData.pomodoro_minutes > 0
    })

    // セッション情報があるかどうか
    const hasSessionInfo = computed(() => {
      return props.dayData.session_count > 0 || props.dayData.focus_sessions > 0
    })

    // 学習レベルのクラス
    const levelClass = computed(() => {
      return GrassCalendarUtils.getGrassLevelClass(props.dayData.level)
    })

    // 学習レベルの説明
    const levelDescription = computed(() => {
      return GrassCalendarUtils.getLevelDescription(props.dayData.level)
    })

    // ツールチップの位置スタイル
    const tooltipStyle = computed(() => {
      const { x, y } = props.position
      const tooltipWidth = 280
      const tooltipHeight = 150
      const margin = 10
      
      // 画面端での調整
      const windowWidth = window.innerWidth
      const windowHeight = window.innerHeight
      
      let left = x + margin
      let top = y - tooltipHeight - margin
      
      // 右端チェック
      if (left + tooltipWidth > windowWidth) {
        left = x - tooltipWidth - margin
      }
      
      // 上端チェック
      if (top < margin) {
        top = y + margin
      }
      
      // 下端チェック
      if (top + tooltipHeight > windowHeight) {
        top = windowHeight - tooltipHeight - margin
      }
      
      return {
        position: 'fixed',
        left: `${left}px`,
        top: `${top}px`,
        zIndex: 9999
      }
    })

    // 矢印の位置
    const arrowPosition = computed(() => {
      const { x } = props.position
      const style = tooltipStyle.value
      const tooltipLeft = parseInt(style.left)
      const tooltipTop = parseInt(style.top)
      
      // ツールチップが元の位置より上にある場合は下矢印
      if (tooltipTop < props.position.y) {
        return 'arrow-bottom'
      }
      
      // ツールチップが元の位置より下にある場合は上矢印
      return 'arrow-top'
    })

    // 日付のフォーマット
    const formatDate = (dateString) => {
      const date = new Date(dateString)
      return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
      })
    }

    // 時間のフォーマット
    const formatTime = (minutes) => {
      if (minutes < 60) {
        return `${minutes}分`
      }
      
      const hours = Math.floor(minutes / 60)
      const remainingMinutes = minutes % 60
      
      if (remainingMinutes === 0) {
        return `${hours}時間`
      }
      
      return `${hours}時間${remainingMinutes}分`
    }

    return {
      hasDetails,
      hasSessionInfo,
      levelClass,
      levelDescription,
      tooltipStyle,
      arrowPosition,
      formatDate,
      formatTime
    }
  }
}
</script>

<style scoped>
.grass-tooltip {
  background: #111827;
  color: white;
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  max-width: 320px;
  animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-5px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.tooltip-content {
  padding: 12px;
}

.tooltip-date {
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 8px;
  border-bottom: 1px solid #374151;
  padding-bottom: 8px;
}

.tooltip-study-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.total-time {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.time-label {
  color: #d1d5db;
  font-size: 0.75rem;
}

.time-value {
  font-weight: 600;
  color: #4ade80;
}

.time-breakdown {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.75rem;
}

.breakdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.method-icon {
  font-size: 0.75rem;
}

.method-label {
  color: #d1d5db;
  flex: 1;
}

.method-value {
  color: white;
  font-weight: 500;
}

.session-info {
  display: flex;
  gap: 16px;
  font-size: 0.75rem;
}

.session-item {
  display: flex;
  flex-direction: column;
}

.session-label {
  color: #9ca3af;
}

.session-value {
  color: white;
  font-weight: 500;
}

.study-level {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 8px;
  border-top: 1px solid #374151;
}

.level-indicator {
  width: 12px;
  height: 12px;
  border-radius: 2px;
  border: 1px solid #4b5563;
}

.level-indicator.grass-level-0 {
  background: #f3f4f6;
}

.level-indicator.grass-level-1 {
  background: #bbf7d0;
}

.level-indicator.grass-level-2 {
  background: #4ade80;
}

.level-indicator.grass-level-3 {
  background: #16a34a;
}

.level-text {
  font-size: 0.75rem;
  color: #d1d5db;
}

.no-study {
  text-align: center;
  padding: 8px 0;
}

.no-study-text {
  color: #9ca3af;
  font-size: 0.875rem;
}

.tooltip-arrow {
  position: absolute;
  width: 0;
  height: 0;
}

.arrow-top {
  top: -8px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 8px solid transparent;
  border-right: 8px solid transparent;
  border-bottom: 8px solid #111827;
}

.arrow-bottom {
  bottom: -8px;
  left: 50%;
  transform: translateX(-50%);
  border-left: 8px solid transparent;
  border-right: 8px solid transparent;
  border-top: 8px solid #111827;
}
</style>