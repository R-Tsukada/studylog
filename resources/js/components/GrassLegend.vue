<template>
  <div class="grass-legend" :class="legendClasses">
    <!-- タイトル -->
    <div v-if="showTitle" class="legend-title">
      {{ title }}
    </div>

    <!-- メイン凡例 -->
    <div class="legend-main">
      <!-- ラベル（左） -->
      <span v-if="showLabels" class="legend-label legend-label-left">
        {{ leftLabel }}
      </span>

      <!-- レベルスケール -->
      <div class="legend-scale">
        <div
          v-for="level in levels"
          :key="level"
          :class="getLevelCellClass(level)"
          :style="getLevelCellStyle(level)"
          :title="getLevelTooltip(level)"
          class="legend-cell"
          @click="handleLevelClick(level)"
        >
          <!-- レベル数値表示（オプション） -->
          <span v-if="showLevelNumbers" class="level-number">
            {{ level }}
          </span>
        </div>
      </div>

      <!-- ラベル（右） -->
      <span v-if="showLabels" class="legend-label legend-label-right">
        {{ rightLabel }}
      </span>
    </div>

    <!-- 詳細説明 -->
    <div v-if="showDetails" class="legend-details">
      <div
        v-for="level in levels"
        :key="`detail-${level}`"
        class="detail-item"
        :class="{ 'detail-item-active': selectedLevel === level }"
        @click="handleLevelClick(level)"
      >
        <div
          :class="getLevelCellClass(level)"
          :style="getLevelCellStyle(level)"
          class="detail-color"
        ></div>
        <div class="detail-content">
          <div class="detail-label">{{ getLevelLabel(level) }}</div>
          <div class="detail-description">{{ getLevelDescription(level) }}</div>
          <div v-if="showStats && stats" class="detail-stats">
            {{ getLevelStats(level) }}
          </div>
        </div>
      </div>
    </div>

    <!-- カスタム凡例項目 -->
    <div v-if="customItems && customItems.length > 0" class="legend-custom">
      <div
        v-for="item in customItems"
        :key="item.key"
        class="custom-item"
        :title="item.tooltip"
        @click="handleCustomClick(item)"
      >
        <div
          class="custom-color"
          :style="{ backgroundColor: item.color }"
        ></div>
        <span class="custom-label">{{ item.label }}</span>
      </div>
    </div>

    <!-- コンパクトモード切り替え -->
    <div v-if="allowToggle" class="legend-toggle">
      <button
        @click="toggleCompact"
        class="toggle-button"
        :title="isCompact ? '詳細表示' : 'コンパクト表示'"
      >
        {{ isCompact ? '詳細' : '簡潔' }}
      </button>
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue'
import GrassCalendarUtils from '../utils/GrassCalendarUtils.js'

export default {
  name: 'GrassLegend',
  props: {
    // 基本設定
    title: {
      type: String,
      default: '学習強度'
    },
    leftLabel: {
      type: String,
      default: '少ない'
    },
    rightLabel: {
      type: String,
      default: '多い'
    },
    
    // 表示制御
    showTitle: {
      type: Boolean,
      default: false
    },
    showLabels: {
      type: Boolean,
      default: true
    },
    showDetails: {
      type: Boolean,
      default: false
    },
    showLevelNumbers: {
      type: Boolean,
      default: false
    },
    showStats: {
      type: Boolean,
      default: false
    },
    allowToggle: {
      type: Boolean,
      default: false
    },
    
    // データ
    stats: {
      type: Object,
      default: null
    },
    customItems: {
      type: Array,
      default: () => []
    },
    
    // スタイル
    variant: {
      type: String,
      default: 'default', // 'default' | 'compact' | 'detailed'
      validator: (value) => ['default', 'compact', 'detailed'].includes(value)
    },
    size: {
      type: String,
      default: 'medium', // 'small' | 'medium' | 'large'
      validator: (value) => ['small', 'medium', 'large'].includes(value)
    },
    orientation: {
      type: String,
      default: 'horizontal', // 'horizontal' | 'vertical'
      validator: (value) => ['horizontal', 'vertical'].includes(value)
    }
  },
  emits: ['levelClick', 'customClick', 'toggleView'],
  setup(props, { emit }) {
    const isCompact = ref(props.variant === 'compact')
    const selectedLevel = ref(null)

    // 表示するレベル
    const levels = computed(() => [0, 1, 2, 3])

    // 凡例のクラス
    const legendClasses = computed(() => {
      const classes = ['grass-legend']
      
      classes.push(`legend-${props.variant}`)
      classes.push(`legend-size-${props.size}`)
      classes.push(`legend-${props.orientation}`)
      
      if (isCompact.value) {
        classes.push('legend-compact')
      }
      
      return classes
    })

    // レベルセルのクラス取得
    const getLevelCellClass = (level) => {
      const classes = ['legend-cell']
      classes.push(GrassCalendarUtils.getGrassLevelClass(level))
      
      if (selectedLevel.value === level) {
        classes.push('cell-selected')
      }
      
      return classes
    }

    // レベルセルのスタイル取得
    const getLevelCellStyle = (level) => {
      return {
        backgroundColor: GrassCalendarUtils.getGrassColors()[level]
      }
    }

    // レベルのツールチップ
    const getLevelTooltip = (level) => {
      const description = GrassCalendarUtils.getLevelDescription(level)
      if (props.stats && props.stats.levelDistribution) {
        const count = props.stats.levelDistribution[level] || 0
        return `${description} (${count}日)`
      }
      return description
    }

    // レベルのラベル
    const getLevelLabel = (level) => {
      const labels = {
        0: 'なし',
        1: '軽い学習',
        2: '中程度の学習', 
        3: '集中学習'
      }
      return labels[level] || 'なし'
    }

    // レベルの説明
    const getLevelDescription = (level) => {
      return GrassCalendarUtils.getLevelDescription(level)
    }

    // レベルの統計
    const getLevelStats = (level) => {
      if (!props.stats || !props.stats.levelDistribution) {
        return ''
      }
      
      const count = props.stats.levelDistribution[level] || 0
      const total = props.stats.totalDays || 0
      const percentage = total > 0 ? Math.round((count / total) * 100) : 0
      
      return `${count}日 (${percentage}%)`
    }

    // イベントハンドラ
    const handleLevelClick = (level) => {
      selectedLevel.value = selectedLevel.value === level ? null : level
      emit('levelClick', level)
    }

    const handleCustomClick = (item) => {
      emit('customClick', item)
    }

    const toggleCompact = () => {
      isCompact.value = !isCompact.value
      emit('toggleView', isCompact.value)
    }

    return {
      isCompact,
      selectedLevel,
      levels,
      legendClasses,
      getLevelCellClass,
      getLevelCellStyle,
      getLevelTooltip,
      getLevelLabel,
      getLevelDescription,
      getLevelStats,
      handleLevelClick,
      handleCustomClick,
      toggleCompact
    }
  }
}
</script>

<style scoped>
.grass-legend {
  @apply text-sm;
}

.legend-title {
  @apply font-medium text-gray-700 mb-2;
}

.legend-main {
  @apply flex items-center gap-2;
}

.legend-horizontal .legend-main {
  @apply flex-row;
}

.legend-vertical .legend-main {
  @apply flex-col items-start;
}

.legend-label {
  @apply text-xs text-gray-500 whitespace-nowrap;
}

.legend-scale {
  @apply flex gap-1;
}

.legend-vertical .legend-scale {
  @apply flex-col;
}

.legend-cell {
  @apply border border-gray-200 cursor-pointer transition-all duration-200;
}

.legend-cell:hover {
  @apply ring-2 ring-blue-300 ring-opacity-50 scale-110;
}

.cell-selected {
  @apply ring-2 ring-blue-500;
}

/* サイズ設定 */
.legend-size-small .legend-cell {
  @apply w-2 h-2 rounded-sm;
}

.legend-size-medium .legend-cell {
  @apply w-3 h-3 rounded-sm;
}

.legend-size-large .legend-cell {
  @apply w-4 h-4 rounded;
}

.level-number {
  @apply text-xs font-bold text-gray-600;
}

.legend-details {
  @apply mt-4 space-y-2;
}

.detail-item {
  @apply flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-colors;
}

.detail-item:hover {
  @apply bg-gray-50;
}

.detail-item-active {
  @apply bg-blue-50 border border-blue-200;
}

.detail-color {
  @apply w-4 h-4 rounded border border-gray-300 flex-shrink-0;
}

.detail-content {
  @apply flex-1;
}

.detail-label {
  @apply font-medium text-gray-900;
}

.detail-description {
  @apply text-sm text-gray-600;
}

.detail-stats {
  @apply text-xs text-gray-500 mt-1;
}

.legend-custom {
  @apply mt-3 flex flex-wrap gap-3;
}

.custom-item {
  @apply flex items-center gap-2 cursor-pointer;
}

.custom-item:hover {
  @apply opacity-80;
}

.custom-color {
  @apply w-3 h-3 rounded border border-gray-300;
}

.custom-label {
  @apply text-xs text-gray-600;
}

.legend-toggle {
  @apply mt-3 text-right;
}

.toggle-button {
  @apply text-xs text-blue-600 hover:text-blue-800 font-medium;
}

/* コンパクトモード */
.legend-compact .legend-main {
  @apply gap-1;
}

.legend-compact .legend-label {
  @apply hidden;
}

.legend-compact .legend-cell {
  @apply w-2 h-2;
}

/* 詳細モード */
.legend-detailed .legend-details {
  @apply block;
}

/* 草レベルの色 */
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
</style>