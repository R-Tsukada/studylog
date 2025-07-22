<template>
  <div class="pomodoro-page">
    <div class="max-w-6xl mx-auto">
      <!-- ページヘッダー -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">🍅 ポモドーロテクニック</h1>
        <p class="text-gray-600">集中力を高めて効率的に学習しましょう</p>
      </div>

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- ポモドーロタイマー -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <PomodoroTimer />
        </div>

        <!-- 統計とログ -->
        <div class="space-y-6">
          <!-- 今日の統計 -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">📈 今日の統計</h2>
            <div v-if="todayStats" class="grid grid-cols-2 gap-4">
              <div class="bg-red-50 p-4 rounded-lg">
                <div class="text-sm text-red-600 font-medium">完了セッション</div>
                <div class="text-2xl font-bold text-red-700">{{ todayStats.total_sessions }}</div>
                <div class="text-xs text-red-500">集中: {{ todayStats.focus_sessions }}回</div>
              </div>
              <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-sm text-green-600 font-medium">集中時間</div>
                <div class="text-2xl font-bold text-green-700">{{ formatDuration(todayStats.total_focus_time) }}</div>
                <div class="text-xs text-green-500">平均: {{ Math.round(todayStats.average_focus_duration || 0) }}分</div>
              </div>
              <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-sm text-blue-600 font-medium">完了率</div>
                <div class="text-2xl font-bold text-blue-700">{{ todayStats.completion_rate }}%</div>
                <div class="text-xs text-blue-500">中断: {{ todayStats.interrupted_sessions }}回</div>
              </div>
              <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-sm text-purple-600 font-medium">休憩時間</div>
                <div class="text-2xl font-bold text-purple-700">{{ formatDuration(todayStats.total_break_time) }}</div>
                <div class="text-xs text-purple-500">短期+長期休憩</div>
              </div>
            </div>
            <div v-else class="text-center text-gray-500 py-8">
              今日のセッションはまだありません
            </div>
          </div>

          <!-- 最近のセッション -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">📋 最近のセッション</h2>
            <div v-if="recentSessions.length > 0" class="space-y-3">
              <div
                v-for="session in recentSessions"
                :key="session.id"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg group hover:bg-gray-100 transition-colors"
              >
                <div class="flex items-center gap-3">
                  <span class="text-lg">{{ getSessionIcon(session.session_type) }}</span>
                  <div>
                    <div class="font-medium">{{ getSessionLabel(session.session_type) }}</div>
                    <div class="text-sm text-gray-500">{{ formatDateTime(session.started_at) }}</div>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <div class="font-medium">{{ session.actual_duration || session.planned_duration }}分</div>
                    <div class="text-xs" :class="session.was_interrupted ? 'text-red-500' : 'text-green-500'">
                      {{ session.was_interrupted ? '中断' : '完了' }}
                    </div>
                  </div>
                  <!-- 削除ボタン（実行中以外のセッションのみ） -->
                  <button
                    v-if="session.is_completed"
                    @click="deleteSession(session)"
                    class="opacity-0 group-hover:opacity-100 p-1 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-all"
                    title="このセッションを削除"
                  >
                    🗑️
                  </button>
                  <div v-else class="w-6 h-6"></div> <!-- 実行中セッションのスペーサー -->
                </div>
              </div>
            </div>
            <div v-else class="text-center text-gray-500 py-8">
              セッション履歴がありません
            </div>
            
            <router-link
              to="/history?tab=pomodoro"
              class="block mt-4 text-center text-blue-600 hover:text-blue-700 text-sm font-medium"
            >
              すべてのポモドーロ履歴を見る →
            </router-link>
          </div>
        </div>
      </div>

      <!-- 月間統計 -->
      <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">📊 月間統計</h2>
        <div v-if="monthlyStats" class="grid md:grid-cols-4 gap-4">
          <div class="text-center p-4 bg-gradient-to-br from-red-50 to-red-100 rounded-lg">
            <div class="text-3xl font-bold text-red-600">{{ monthlyStats.total_sessions }}</div>
            <div class="text-sm text-red-700 font-medium">総セッション数</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-lg">
            <div class="text-3xl font-bold text-green-600">{{ formatDuration(monthlyStats.total_focus_time) }}</div>
            <div class="text-sm text-green-700 font-medium">総集中時間</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg">
            <div class="text-3xl font-bold text-blue-600">{{ monthlyStats.completion_rate }}%</div>
            <div class="text-sm text-blue-700 font-medium">完了率</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg">
            <div class="text-3xl font-bold text-purple-600">{{ Math.round(monthlyStats.average_focus_duration || 0) }}</div>
            <div class="text-sm text-purple-700 font-medium">平均集中時間（分）</div>
          </div>
        </div>
        
        <!-- デイリー統計グラフ（簡易版） -->
        <div v-if="dailyStats.length > 0" class="mt-6">
          <h3 class="font-semibold text-gray-800 mb-3">今月の日別セッション数</h3>
          <div class="flex items-end justify-between gap-1 h-32 p-4 bg-gray-50 rounded-lg overflow-x-auto">
            <div
              v-for="day in dailyStats"
              :key="day.date"
              class="flex flex-col items-center min-w-[20px]"
            >
              <div
                class="bg-red-400 rounded-t-sm min-h-[2px] w-4 mb-1"
                :style="{ height: `${Math.max(2, (day.total_sessions / maxDailySessions) * 80)}px` }"
                :title="`${day.date}: ${day.total_sessions}セッション`"
              ></div>
              <div class="text-xs text-gray-500 transform rotate-45 origin-bottom-left whitespace-nowrap">
                {{ formatDate(day.date) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- テストモード（開発用） -->
      <div class="mt-8">
        <PomodoroTestMode />
      </div>

      <!-- ポモドーロテクニックの説明 -->
      <div class="mt-8 bg-gradient-to-r from-red-50 to-pink-50 rounded-xl p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">💡 ポモドーロテクニックとは？</h2>
        <div class="grid md:grid-cols-2 gap-6">
          <div>
            <h3 class="font-semibold text-gray-800 mb-2">基本的な流れ</h3>
            <ol class="space-y-2 text-sm text-gray-600">
              <li class="flex items-start gap-2">
                <span class="flex-shrink-0 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs font-bold">1</span>
                25分間集中して作業する
              </li>
              <li class="flex items-start gap-2">
                <span class="flex-shrink-0 w-5 h-5 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-bold">2</span>
                5分間の短い休憩を取る
              </li>
              <li class="flex items-start gap-2">
                <span class="flex-shrink-0 w-5 h-5 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">3</span>
                4回繰り返したら15-30分の長い休憩
              </li>
            </ol>
          </div>
          <div>
            <h3 class="font-semibold text-gray-800 mb-2">効果</h3>
            <ul class="space-y-1 text-sm text-gray-600">
              <li>• 集中力の向上</li>
              <li>• 疲労の軽減</li>
              <li>• 時間の見積もり精度向上</li>
              <li>• 達成感の獲得</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import PomodoroTimer from '../components/PomodoroTimer.vue'
import PomodoroTestMode from '../components/PomodoroTestMode.vue'

export default {
  name: 'Pomodoro',
  components: {
    PomodoroTimer,
    PomodoroTestMode
  },
  data() {
    return {
      todayStats: null,
      monthlyStats: null,
      dailyStats: [],
      recentSessions: [],
      loading: false
    }
  },
  
  computed: {
    maxDailySessions() {
      return Math.max(...this.dailyStats.map(day => day.total_sessions), 1);
    }
  },
  
  async mounted() {
    await this.loadStats();
    await this.loadRecentSessions();
    
    // 通知権限をリクエスト
    if (Notification.permission === 'default') {
      await Notification.requestPermission();
    }
  },
  
  methods: {
    async loadStats() {
      this.loading = true;
      try {
        // 今日の統計
        const today = new Date().toISOString().split('T')[0];
        const todayResponse = await fetch(`/api/pomodoro/stats?start_date=${today}&end_date=${today}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        });
        
        if (todayResponse.ok) {
          const todayData = await todayResponse.json();
          this.todayStats = todayData.stats;
        }
        
        // 月間統計
        const monthStart = new Date();
        monthStart.setDate(1);
        const monthEnd = new Date();
        monthEnd.setMonth(monthEnd.getMonth() + 1);
        monthEnd.setDate(0);
        
        const monthlyResponse = await fetch(`/api/pomodoro/stats?start_date=${monthStart.toISOString().split('T')[0]}&end_date=${monthEnd.toISOString().split('T')[0]}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        });
        
        if (monthlyResponse.ok) {
          const monthlyData = await monthlyResponse.json();
          this.monthlyStats = monthlyData.stats;
          this.dailyStats = monthlyData.daily_stats;
        }
        
      } catch (error) {
        console.error('統計読み込みエラー:', error);
      } finally {
        this.loading = false;
      }
    },
    
    async loadRecentSessions() {
      try {
        const response = await fetch('/api/pomodoro?per_page=5', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          this.recentSessions = data.data || [];
        }
      } catch (error) {
        console.error('最近のセッション取得エラー:', error);
      }
    },
    
    getSessionIcon(sessionType) {
      const icons = {
        focus: '🎯',
        short_break: '☕',
        long_break: '🛋️'
      };
      return icons[sessionType] || '⏱️';
    },
    
    getSessionLabel(sessionType) {
      const labels = {
        focus: '集中セッション',
        short_break: '短い休憩',
        long_break: '長い休憩'
      };
      return labels[sessionType] || 'セッション';
    },
    
    formatDuration(minutes) {
      if (!minutes) return '0分';
      const hours = Math.floor(minutes / 60);
      const mins = minutes % 60;
      if (hours > 0) {
        return `${hours}h ${mins}m`;
      }
      return `${mins}分`;
    },
    
    formatDateTime(datetime) {
      if (!datetime) return '';
      const date = new Date(datetime);
      const now = new Date();
      const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
      
      if (diffDays === 0) {
        return `今日 ${date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' })}`;
      } else if (diffDays === 1) {
        return `昨日 ${date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' })}`;
      } else {
        return date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
      }
    },
    
    formatDate(dateString) {
      const date = new Date(dateString);
      return `${date.getMonth() + 1}/${date.getDate()}`;
    },
    
    async deleteSession(session) {
      // 確認ダイアログ
      const confirmMessage = `このセッションを削除しますか？\n\n${this.getSessionLabel(session.session_type)} - ${session.actual_duration || session.planned_duration}分\n${this.formatDateTime(session.started_at)}`;
      
      if (!confirm(confirmMessage)) {
        return;
      }
      
      try {
        const response = await fetch(`/api/pomodoro/${session.id}`, {
          method: 'DELETE',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        });
        
        if (response.ok) {
          // セッション一覧から削除
          this.recentSessions = this.recentSessions.filter(s => s.id !== session.id);
          
          // 統計データを再読み込み（削除の影響を反映）
          await this.loadStats();
          
          console.log('セッションを削除しました');
        } else {
          const errorData = await response.json();
          alert(errorData.message || 'セッションの削除に失敗しました');
        }
      } catch (error) {
        console.error('セッション削除エラー:', error);
        alert('セッションの削除中にエラーが発生しました');
      }
    }
  }
}
</script>

<style scoped>
.pomodoro-page {
  padding-bottom: 2rem;
}

/* グラフのアニメーション */
.grid > div {
  transition: transform 0.2s ease;
}

.grid > div:hover {
  transform: translateY(-2px);
}

/* レスポンシブ調整 */
@media (max-width: 768px) {
  .grid {
    grid-template-columns: 1fr;
  }
}

/* カスタムスクロールバー */
.overflow-x-auto::-webkit-scrollbar {
  height: 4px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 2px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 2px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #a1a1a1;
}
</style>