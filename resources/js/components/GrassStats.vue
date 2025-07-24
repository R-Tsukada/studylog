<template>
  <div class="grass-stats">
    <!-- 基本統計 -->
    <div class="stats-section">
      <h4 class="stats-section-title">{{ year }}年の学習統計</h4>
      
      <div class="stats-grid">
        <!-- 学習日数 -->
        <div class="stat-card">
          <div class="stat-icon">📅</div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.studyDays }}</div>
            <div class="stat-label">学習日数</div>
            <div class="stat-detail">{{ stats.totalDays }}日中</div>
          </div>
        </div>

        <!-- 学習率 -->
        <div class="stat-card">
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.studyRate }}%</div>
            <div class="stat-label">学習率</div>
            <div class="stat-detail">継続度の指標</div>
          </div>
        </div>

        <!-- 総学習時間 -->
        <div class="stat-card">
          <div class="stat-icon">⏰</div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.totalHours }}</div>
            <div class="stat-label">総学習時間</div>
            <div class="stat-detail">{{ stats.totalMinutes }}分</div>
          </div>
        </div>

        <!-- 平均学習時間 -->
        <div class="stat-card">
          <div class="stat-icon">📈</div>
          <div class="stat-content">
            <div class="stat-value">{{ stats.averageDailyMinutes }}</div>
            <div class="stat-label">平均学習時間</div>
            <div class="stat-detail">分/日</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 連続学習 -->
    <div class="stats-section">
      <h4 class="stats-section-title">学習ストリーク</h4>
      
      <div class="streak-stats">
        <div class="streak-item">
          <div class="streak-icon">🔥</div>
          <div class="streak-content">
            <div class="streak-value">{{ stats.currentStreak }}</div>
            <div class="streak-label">現在の連続学習日数</div>
          </div>
        </div>
        
        <div class="streak-item">
          <div class="streak-icon">🏆</div>
          <div class="streak-content">
            <div class="streak-value">{{ stats.longestStreak }}</div>
            <div class="streak-label">最長連続学習日数</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 学習セッション統計 -->
    <div v-if="hasSessionStats" class="stats-section">
      <h4 class="stats-section-title">セッション統計</h4>
      
      <div class="session-stats">
        <div class="session-item">
          <div class="session-icon">⏱️</div>
          <div class="session-content">
            <div class="session-value">{{ stats.totalStudySessions }}</div>
            <div class="session-label">学習セッション数</div>
          </div>
        </div>
        
        <div class="session-item">
          <div class="session-icon">🍅</div>
          <div class="session-content">
            <div class="session-value">{{ stats.totalPomodoroSessions }}</div>
            <div class="session-label">ポモドーロセッション数</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 学習レベル分布 -->
    <div class="stats-section">
      <h4 class="stats-section-title">学習強度分布</h4>
      
      <div class="level-distribution">
        <div
          v-for="(count, level) in stats.levelDistribution"
          :key="level"
          class="level-item"
        >
          <div class="level-indicator">
            <div
              :class="getGrassLevelClass(level)"
              :style="{ backgroundColor: getGrassColor(level) }"
              class="level-color"
            ></div>
            <span class="level-name">{{ getLevelName(level) }}</span>
          </div>
          <div class="level-stats">
            <div class="level-count">{{ count }}日</div>
            <div class="level-percentage">{{ getLevelPercentage(level, count) }}%</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 月別傾向（簡易版） -->
    <div v-if="showMonthlyTrend" class="stats-section">
      <h4 class="stats-section-title">
        月別傾向
        <button @click="loadMonthlyTrend" class="trend-load-btn">
          詳細を読み込み
        </button>
      </h4>
      
      <div v-if="monthlyTrendData" class="monthly-trend">
        <!-- ここに月別のミニチャートを実装 -->
        <div class="trend-placeholder">
          月別データの可視化（今後実装予定）
        </div>
      </div>
    </div>

    <!-- 学習パフォーマンス指標 -->
    <div class="stats-section">
      <h4 class="stats-section-title">パフォーマンス指標</h4>
      
      <div class="performance-indicators">
        <div class="indicator-item">
          <div class="indicator-label">継続性スコア</div>
          <div class="indicator-value">
            <div class="score-bar">
              <div
                class="score-fill"
                :style="{ width: `${consistencyScore}%` }"
              ></div>
            </div>
            <span class="score-text">{{ consistencyScore }}/100</span>
          </div>
        </div>
        
        <div class="indicator-item">
          <div class="indicator-label">集中度スコア</div>
          <div class="indicator-value">
            <div class="score-bar">
              <div
                class="score-fill intensity"
                :style="{ width: `${intensityScore}%` }"
              ></div>
            </div>
            <span class="score-text">{{ intensityScore }}/100</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 推奨・アドバイス -->
    <div class="stats-section">
      <h4 class="stats-section-title">学習アドバイス</h4>
      
      <div class="advice-list">
        <div
          v-for="advice in generatedAdvice"
          :key="advice.type"
          :class="['advice-item', `advice-${advice.type}`]"
        >
          <div class="advice-icon">{{ advice.icon }}</div>
          <div class="advice-content">
            <div class="advice-title">{{ advice.title }}</div>
            <div class="advice-message">{{ advice.message }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import GrassCalendarUtils from '../utils/GrassCalendarUtils.js'

export default {
  name: 'GrassStats',
  props: {
    stats: {
      type: Object,
      required: true
    },
    year: {
      type: Number,
      required: true
    },
    showMonthlyTrend: {
      type: Boolean,
      default: false
    }
  },
  setup(props) {
    const monthlyTrendData = ref(null)

    // セッション統計があるかどうか
    const hasSessionStats = computed(() => {
      return props.stats.totalStudySessions > 0 || props.stats.totalPomodoroSessions > 0
    })

    // 継続性スコア（学習率ベース）
    const consistencyScore = computed(() => {
      return Math.min(100, Math.round(props.stats.studyRate * 1.2))
    })

    // 集中度スコア（平均学習時間ベース）
    const intensityScore = computed(() => {
      const baseScore = Math.min(100, Math.round(props.stats.averageDailyMinutes / 2))
      return Math.max(10, baseScore) // 最低10点
    })

    // 生成されたアドバイス
    const generatedAdvice = computed(() => {
      const advice = []

      // 学習率に基づくアドバイス
      if (props.stats.studyRate < 30) {
        advice.push({
          type: 'consistency',
          icon: '📈',
          title: '継続性を向上させましょう',
          message: '学習習慣を身につけるため、短時間でも毎日続けることから始めてみませんか？'
        })
      } else if (props.stats.studyRate > 80) {
        advice.push({
          type: 'excellent',
          icon: '🎉',
          title: '素晴らしい継続力です！',
          message: 'この調子で学習を続けていけば、必ず目標達成できます。'
        })
      }

      // 学習時間に基づくアドバイス
      if (props.stats.averageDailyMinutes < 30) {
        advice.push({
          type: 'time',
          icon: '⏰',
          title: '学習時間を増やしてみましょう',
          message: 'ポモドーロテクニックを使って、集中した25分間の学習から始めてみませんか？'
        })
      }

      // ストリークに基づくアドバイス
      if (props.stats.currentStreak === 0 && props.stats.longestStreak > 5) {
        advice.push({
          type: 'streak',
          icon: '🔥',
          title: '学習習慣を再開しましょう',
          message: `過去に${props.stats.longestStreak}日連続で学習されていました。再び始めてみませんか？`
        })
      } else if (props.stats.currentStreak >= 7) {
        advice.push({
          type: 'streak',
          icon: '🔥',
          title: '連続学習を継続中！',
          message: `${props.stats.currentStreak}日連続で学習されています。この調子で頑張りましょう！`
        })
      }

      // デフォルトアドバイス
      if (advice.length === 0) {
        advice.push({
          type: 'general',
          icon: '💪',
          title: '学習を続けましょう',
          message: '継続は力なり。小さな積み重ねが大きな成果につながります。'
        })
      }

      return advice
    })

    // 草レベルのクラス取得
    const getGrassLevelClass = (level) => {
      return GrassCalendarUtils.getGrassLevelClass(level)
    }

    // 草の色取得
    const getGrassColor = (level) => {
      const colors = GrassCalendarUtils.getGrassColors()
      return colors[level] || colors[0]
    }

    // レベル名取得
    const getLevelName = (level) => {
      const names = {
        0: 'なし',
        1: '軽い',
        2: '中程度',
        3: '集中'
      }
      return names[level] || 'なし'
    }

    // レベル割合計算
    const getLevelPercentage = (level, count) => {
      if (props.stats.totalDays === 0) return 0
      return Math.round((count / props.stats.totalDays) * 100)
    }

    // 月別傾向読み込み
    const loadMonthlyTrend = async () => {
      // ここで月別データを読み込み
      // 実装は今後のフェーズで
      monthlyTrendData.value = { placeholder: true }
    }

    return {
      monthlyTrendData,
      hasSessionStats,
      consistencyScore,
      intensityScore,
      generatedAdvice,
      getGrassLevelClass,
      getGrassColor,
      getLevelName,
      getLevelPercentage,
      loadMonthlyTrend
    }
  }
}
</script>

<style scoped>
.grass-stats {
  @apply space-y-6;
}

.stats-section {
  @apply bg-gray-50 rounded-lg p-4;
}

.stats-section-title {
  @apply text-base font-semibold text-gray-900 mb-3 flex items-center justify-between;
}

.stats-grid {
  @apply grid grid-cols-2 lg:grid-cols-4 gap-4;
}

.stat-card {
  @apply bg-white rounded-lg p-3 flex items-center gap-3 shadow-sm;
}

.stat-icon {
  @apply text-2xl;
}

.stat-content {
  @apply flex-1;
}

.stat-value {
  @apply text-lg font-bold text-gray-900;
}

.stat-label {
  @apply text-sm font-medium text-gray-700;
}

.stat-detail {
  @apply text-xs text-gray-500;
}

.streak-stats {
  @apply grid grid-cols-1 md:grid-cols-2 gap-4;
}

.streak-item {
  @apply bg-white rounded-lg p-4 flex items-center gap-4;
}

.streak-icon {
  @apply text-3xl;
}

.streak-content {
  @apply flex-1;
}

.streak-value {
  @apply text-2xl font-bold text-orange-600;
}

.streak-label {
  @apply text-sm font-medium text-gray-700;
}

.session-stats {
  @apply grid grid-cols-1 md:grid-cols-2 gap-4;
}

.session-item {
  @apply bg-white rounded-lg p-3 flex items-center gap-3;
}

.session-icon {
  @apply text-xl;
}

.session-content {
  @apply flex-1;
}

.session-value {
  @apply text-lg font-bold text-blue-600;
}

.session-label {
  @apply text-sm font-medium text-gray-700;
}

.level-distribution {
  @apply space-y-3;
}

.level-item {
  @apply bg-white rounded-lg p-3 flex items-center justify-between;
}

.level-indicator {
  @apply flex items-center gap-2;
}

.level-color {
  @apply w-4 h-4 rounded-sm border border-gray-300;
}

.level-name {
  @apply text-sm font-medium text-gray-700;
}

.level-stats {
  @apply flex items-center gap-2 text-sm;
}

.level-count {
  @apply font-semibold text-gray-900;
}

.level-percentage {
  @apply text-gray-500;
}

.trend-load-btn {
  @apply text-sm text-blue-600 hover:text-blue-800 font-medium;
}

.monthly-trend {
  @apply bg-white rounded-lg p-4;
}

.trend-placeholder {
  @apply text-center text-gray-500 py-8;
}

.performance-indicators {
  @apply space-y-4;
}

.indicator-item {
  @apply bg-white rounded-lg p-4;
}

.indicator-label {
  @apply text-sm font-medium text-gray-700 mb-2;
}

.indicator-value {
  @apply flex items-center gap-3;
}

.score-bar {
  @apply flex-1 bg-gray-200 rounded-full h-2 overflow-hidden;
}

.score-fill {
  @apply bg-green-500 h-full transition-all duration-300 ease-out;
}

.score-fill.intensity {
  @apply bg-blue-500;
}

.score-text {
  @apply text-sm font-semibold text-gray-700;
}

.advice-list {
  @apply space-y-3;
}

.advice-item {
  @apply bg-white rounded-lg p-4 flex items-start gap-3 border-l-4;
}

.advice-consistency {
  @apply border-l-yellow-500;
}

.advice-excellent {
  @apply border-l-green-500;
}

.advice-time {
  @apply border-l-blue-500;
}

.advice-streak {
  @apply border-l-orange-500;
}

.advice-general {
  @apply border-l-gray-400;
}

.advice-icon {
  @apply text-xl flex-shrink-0;
}

.advice-content {
  @apply flex-1;
}

.advice-title {
  @apply text-sm font-semibold text-gray-900 mb-1;
}

.advice-message {
  @apply text-sm text-gray-600;
}
</style>