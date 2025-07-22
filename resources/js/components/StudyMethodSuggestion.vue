<template>
  <div class="study-method-suggestion bg-white rounded-lg shadow-md p-6 mb-6">
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <span class="ml-3 text-gray-600">学習方法を分析中...</span>
    </div>
    
    <div v-else-if="suggestion" class="space-y-4">
      <!-- メインの推奨 -->
      <div class="main-suggestion">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">
          📊 あなたにおすすめの学習方法
        </h3>
        
        <div 
          :class="[
            'p-4 rounded-lg border-2 cursor-pointer transition-all duration-200',
            'hover:shadow-md',
            suggestion.recommended.method === 'pomodoro' 
              ? 'border-red-200 bg-red-50 hover:border-red-300' 
              : 'border-green-200 bg-green-50 hover:border-green-300'
          ]"
          @click="selectMethod(suggestion.recommended.method)"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center mb-2">
                <span class="text-2xl mr-3">
                  {{ suggestion.recommended.method === 'pomodoro' ? '🍅' : '⏰' }}
                </span>
                <h4 class="text-lg font-medium">
                  {{ suggestion.recommended.method === 'pomodoro' ? 'ポモドーロテクニック' : '自由時間計測' }}
                </h4>
                <div class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                  推奨度 {{ Math.round(suggestion.recommended.confidence * 100) }}%
                </div>
              </div>
              <p class="text-sm text-gray-600 mb-3">
                {{ suggestion.recommended.reason }}
              </p>
              <div class="text-xs text-gray-500">
                {{ getMethodDescription(suggestion.recommended.method) }}
              </div>
            </div>
            <button 
              class="ml-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-sm font-medium"
              @click.stop="selectMethod(suggestion.recommended.method)"
            >
              この方法で開始
            </button>
          </div>
        </div>
      </div>

      <!-- 代替案 -->
      <div v-if="suggestion.alternatives && suggestion.alternatives.length > 0" class="alternatives">
        <h4 class="text-sm font-medium text-gray-700 mb-2">他の選択肢</h4>
        <div class="space-y-2">
          <div 
            v-for="alternative in suggestion.alternatives" 
            :key="alternative.method"
            :class="[
              'p-3 rounded-lg border cursor-pointer transition-all duration-200',
              'hover:shadow-sm border-gray-200 bg-gray-50 hover:border-gray-300'
            ]"
            @click="selectMethod(alternative.method)"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <span class="text-lg mr-2">
                  {{ alternative.method === 'pomodoro' ? '🍅' : '⏰' }}
                </span>
                <div>
                  <span class="text-sm font-medium">
                    {{ alternative.method === 'pomodoro' ? 'ポモドーロ' : '時間計測' }}
                  </span>
                  <div class="text-xs text-gray-500">
                    {{ alternative.reason }}
                  </div>
                </div>
              </div>
              <button 
                class="px-3 py-1 bg-gray-500 text-white rounded text-xs hover:bg-gray-600 transition-colors"
                @click.stop="selectMethod(alternative.method)"
              >
                選択
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- コンテキスト情報 -->
      <div class="context-info bg-gray-50 p-3 rounded-lg">
        <h5 class="text-xs font-medium text-gray-700 mb-2">分析情報</h5>
        <div class="grid grid-cols-2 gap-4 text-xs text-gray-600">
          <div>
            <span class="font-medium">時刻:</span> 
            {{ formatTime(suggestion.context.time_of_day) }}
          </div>
          <div>
            <span class="font-medium">最近の平均時間:</span> 
            {{ suggestion.context.recent_avg_duration }}分
          </div>
          <div v-if="suggestion.context.recent_method">
            <span class="font-medium">前回の方法:</span> 
            {{ suggestion.context.recent_method === 'pomodoro' ? 'ポモドーロ' : '時間計測' }}
          </div>
        </div>
      </div>

      <!-- 手動選択オプション -->
      <div class="manual-selection border-t pt-4">
        <button
          @click="showManualSelection = !showManualSelection"
          class="text-sm text-gray-600 hover:text-gray-800 transition-colors"
        >
          {{ showManualSelection ? '▼' : '▶' }} 手動で選択する
        </button>
        
        <div v-if="showManualSelection" class="mt-3 grid grid-cols-2 gap-3">
          <button
            @click="selectMethod('time_tracking')"
            class="p-3 border border-green-200 rounded-lg hover:bg-green-50 transition-colors text-center"
          >
            <div class="text-2xl mb-1">⏰</div>
            <div class="text-sm font-medium">自由時間計測</div>
            <div class="text-xs text-gray-500">制限なし</div>
          </button>
          <button
            @click="selectMethod('pomodoro')"
            class="p-3 border border-red-200 rounded-lg hover:bg-red-50 transition-colors text-center"
          >
            <div class="text-2xl mb-1">🍅</div>
            <div class="text-sm font-medium">ポモドーロ</div>
            <div class="text-xs text-gray-500">25分集中</div>
          </button>
        </div>
      </div>
    </div>

    <div v-else-if="error" class="error text-center py-8">
      <div class="text-red-500 mb-2">❌</div>
      <p class="text-sm text-red-600">{{ error }}</p>
      <button 
        @click="loadSuggestion"
        class="mt-3 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm"
      >
        再試行
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StudyMethodSuggestion',
  props: {
    subjectAreaId: {
      type: [String, Number],
      default: null
    },
    autoLoad: {
      type: Boolean,
      default: true
    }
  },
  emits: ['method-selected'],
  data() {
    return {
      loading: false,
      suggestion: null,
      error: null,
      showManualSelection: false
    }
  },
  mounted() {
    if (this.autoLoad) {
      this.loadSuggestion()
    }
  },
  watch: {
    subjectAreaId() {
      if (this.autoLoad) {
        this.loadSuggestion()
      }
    }
  },
  methods: {
    async loadSuggestion() {
      this.loading = true
      this.error = null
      
      try {
        const params = new URLSearchParams()
        if (this.subjectAreaId) {
          params.append('subject_area_id', this.subjectAreaId)
        }
        
        const response = await fetch(`/api/analytics/suggest?${params}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        })

        if (!response.ok) {
          throw new Error('推奨情報の取得に失敗しました')
        }

        const data = await response.json()
        
        if (data.success) {
          this.suggestion = data.data
          console.log('学習方法推奨データ:', this.suggestion)
        } else {
          throw new Error(data.message || '推奨情報の取得に失敗しました')
        }
      } catch (error) {
        console.error('推奨取得エラー:', error)
        this.error = error.message || '推奨情報の取得中にエラーが発生しました'
      } finally {
        this.loading = false
      }
    },

    selectMethod(method) {
      this.$emit('method-selected', {
        method,
        subjectAreaId: this.subjectAreaId,
        suggestion: this.suggestion
      })
    },

    getMethodDescription(method) {
      const descriptions = {
        pomodoro: '25分の集中と5分の休憩を繰り返すテクニック。集中力向上に効果的。',
        time_tracking: '時間制限なしで自由に学習時間を計測。長時間の読書や研究に最適。'
      }
      return descriptions[method] || ''
    },

    formatTime(hour) {
      if (hour >= 6 && hour < 12) return `${hour}時 (朝)`
      if (hour >= 12 && hour < 18) return `${hour}時 (午後)`
      if (hour >= 18 && hour < 24) return `${hour}時 (夜)`
      return `${hour}時 (深夜)`
    }
  }
}
</script>

<style scoped>
.study-method-suggestion {
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
</style>