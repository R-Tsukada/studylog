<template>
  <div>
    <!-- ステップタイトル -->
    <div class="text-center mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">
        主要機能のご紹介
      </h2>
      <p class="text-gray-600">
        すたログの便利な機能を確認しましょう
      </p>
    </div>

    <!-- 機能カード -->
    <div class="space-y-6">
      <!-- 学習記録機能 -->
      <div 
        class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer"
        :class="{ 'ring-2 ring-blue-500 bg-blue-50': activeFeature === 'study-tracking' }"
        @click="setActiveFeature('study-tracking')"
        tabindex="0"
        @keydown.enter="setActiveFeature('study-tracking')"
        @keydown.space.prevent="setActiveFeature('study-tracking')"
        role="button"
        :aria-pressed="activeFeature === 'study-tracking'"
      >
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <span class="text-2xl" aria-hidden="true">📊</span>
            </div>
          </div>
          <div class="ml-4 flex-1">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              学習記録・時間管理
            </h3>
            <p class="text-gray-600 mb-3">
              学習セッションを記録し、時間を可視化できます。ポモドーロタイマーも搭載。
            </p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                学習記録
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                時間管理
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                ポモドーロ
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 目標設定機能 -->
      <div 
        class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer"
        :class="{ 'ring-2 ring-blue-500 bg-blue-50': activeFeature === 'goal-setting' }"
        @click="setActiveFeature('goal-setting')"
        tabindex="0"
        @keydown.enter="setActiveFeature('goal-setting')"
        @keydown.space.prevent="setActiveFeature('goal-setting')"
        role="button"
        :aria-pressed="activeFeature === 'goal-setting'"
      >
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <span class="text-2xl" aria-hidden="true">🎯</span>
            </div>
          </div>
          <div class="ml-4 flex-1">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              目標設定・進捗管理
            </h3>
            <p class="text-gray-600 mb-3">
              学習目標を設定し、進捗を可視化。試験日までの計画的な学習をサポート。
            </p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                目標設定
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                進捗管理
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                計画立案
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 統計・分析機能 -->
      <div 
        class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer"
        :class="{ 'ring-2 ring-blue-500 bg-blue-50': activeFeature === 'analytics' }"
        @click="setActiveFeature('analytics')"
        tabindex="0"
        @keydown.enter="setActiveFeature('analytics')"
        @keydown.space.prevent="setActiveFeature('analytics')"
        role="button"
        :aria-pressed="activeFeature === 'analytics'"
      >
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <span class="text-2xl" aria-hidden="true">📈</span>
            </div>
          </div>
          <div class="ml-4 flex-1">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              学習統計・分析
            </h3>
            <p class="text-gray-600 mb-3">
              学習パターンを分析し、効率的な学習方法を提案。草表示で継続学習を促進。
            </p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                統計表示
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                学習分析
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                草表示
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- モバイル対応 -->
      <div 
        class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer"
        :class="{ 'ring-2 ring-blue-500 bg-blue-50': activeFeature === 'mobile' }"
        @click="setActiveFeature('mobile')"
        tabindex="0"
        @keydown.enter="setActiveFeature('mobile')"
        @keydown.space.prevent="setActiveFeature('mobile')"
        role="button"
        :aria-pressed="activeFeature === 'mobile'"
      >
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
              <span class="text-2xl" aria-hidden="true">📱</span>
            </div>
          </div>
          <div class="ml-4 flex-1">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">
              モバイル対応・どこでも学習
            </h3>
            <p class="text-gray-600 mb-3">
              スマートフォンでも快適に利用可能。移動中でも学習記録ができます。
            </p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                レスポンシブ
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                タッチ操作
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                オフライン対応
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 詳細情報表示 -->
    <div v-if="activeFeature" class="mt-8 p-6 bg-gray-50 rounded-lg">
      <div class="text-center">
        <div class="text-4xl mb-3" v-html="featureDetails[activeFeature].icon"></div>
        <h4 class="text-lg font-semibold text-gray-900 mb-2">
          {{ featureDetails[activeFeature].title }}
        </h4>
        <p class="text-gray-600 mb-4">
          {{ featureDetails[activeFeature].description }}
        </p>
        <div class="text-sm text-blue-600">
          {{ featureDetails[activeFeature].tip }}
        </div>
      </div>
    </div>

    <!-- フィードバック -->
    <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
      <div class="flex">
        <div class="flex-shrink-0">
          <span class="text-blue-500 text-xl" aria-hidden="true">💡</span>
        </div>
        <div class="ml-3">
          <h4 class="text-sm font-medium text-blue-900">
            ヒント
          </h4>
          <p class="mt-1 text-sm text-blue-800">
            各機能カードをクリックすると詳細情報が表示されます。すべての機能はセットアップ完了後すぐに利用できます。
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue'

export default {
  name: 'FeatureStep',
  setup() {
    const activeFeature = ref(null)

    const featureDetails = {
      'study-tracking': {
        icon: '📊',
        title: '学習記録・時間管理',
        description: '学習セッションを開始/終了ボタンで簡単記録。ポモドーロタイマーで集中力を維持し、学習分野別に時間を管理できます。',
        tip: '💡 ポモドーロタイマーは25分集中 + 5分休憩のサイクルで効率的な学習をサポートします'
      },
      'goal-setting': {
        icon: '🎯',
        title: '目標設定・進捗管理',
        description: '日次・週次・月次の学習目標を設定し、進捗をリアルタイムで確認。試験日が設定されている場合は、残り日数も表示されます。',
        tip: '💡 SMARTな目標設定（具体的、測定可能、達成可能、関連性、期限付き）をおすすめします'
      },
      'analytics': {
        icon: '📈',
        title: '学習統計・分析',
        description: '学習時間の推移、分野別の学習バランス、継続日数などを可視化。GitHubのような草表示で学習の継続を促進します。',
        tip: '💡 週ごとの学習パターンを分析して、最も集中できる時間帯を見つけましょう'
      },
      'mobile': {
        icon: '📱',
        title: 'モバイル対応・どこでも学習',
        description: 'スマートフォンやタブレットでの操作に最適化。移動中の電車内や休憩時間でも、手軽に学習記録を付けられます。',
        tip: '💡 PWAに対応予定で、アプリのようにホーム画面に追加して利用できます'
      }
    }

    const setActiveFeature = (featureKey) => {
      activeFeature.value = activeFeature.value === featureKey ? null : featureKey
    }

    return {
      activeFeature,
      featureDetails,
      setActiveFeature
    }
  }
}
</script>

<style scoped>
/* カード選択時のアニメーション */
.cursor-pointer {
  transition: all 0.2s ease-in-out;
}

.cursor-pointer:hover {
  transform: translateY(-1px);
}

.cursor-pointer:focus {
  outline: none;
}

/* リング効果のスムーズな適用 */
.ring-2 {
  transition: box-shadow 0.2s ease-in-out;
}

/* タグのスタイル調整 */
.inline-flex {
  transition: all 0.15s ease-in-out;
}
</style>