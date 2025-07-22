<template>
  <div class="pomodoro-timer max-w-md mx-auto p-6 bg-white rounded-xl shadow-lg">
    <!-- セッション設定 -->
    <div v-if="!isActive && !currentSession" class="setup-section mb-6">
      <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">🍅 ポモドーロタイマー</h3>
      
      <!-- セッションタイプ選択 -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">セッションタイプ</label>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="type in sessionTypes"
            :key="type.value"
            @click="selectedType = type.value"
            :class="[
              'py-2 px-3 rounded-lg text-sm font-medium transition-colors',
              selectedType === type.value
                ? 'bg-red-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
          >
            {{ type.label }}
          </button>
        </div>
      </div>

      <!-- 時間選択 -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          {{ selectedType === 'focus' ? '集中時間' : '休憩時間' }}
        </label>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="duration in availableDurations"
            :key="duration"
            @click="selectedDuration = duration"
            :class="[
              'py-2 px-3 rounded-lg text-sm font-medium transition-colors',
              selectedDuration === duration
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
          >
            {{ duration }}分
          </button>
        </div>
      </div>

      <!-- 学習分野選択 -->
      <div v-if="selectedType === 'focus'" class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">学習分野</label>
        <select
          v-model="selectedSubjectArea"
          required
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          <option value="">学習分野を選択してください</option>
          <option
            v-for="area in availableSubjectAreas"
            :key="area.id"
            :value="area.id"
          >
            {{ area.exam_type?.name }} - {{ area.name }}
          </option>
        </select>
      </div>

      <!-- 設定オプション -->
      <div class="mb-6">
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm font-medium text-gray-700">音声通知</label>
          <button
            @click="settings.sound_enabled = !settings.sound_enabled"
            :class="[
              'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
              settings.sound_enabled ? 'bg-blue-500' : 'bg-gray-300'
            ]"
          >
            <span
              :class="[
                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                settings.sound_enabled ? 'translate-x-6' : 'translate-x-1'
              ]"
            />
          </button>
        </div>
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium text-gray-700">自動で次のセッションを開始</label>
          <button
            @click="settings.auto_start = !settings.auto_start"
            :class="[
              'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
              settings.auto_start ? 'bg-blue-500' : 'bg-gray-300'
            ]"
          >
            <span
              :class="[
                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                settings.auto_start ? 'translate-x-6' : 'translate-x-1'
              ]"
            />
          </button>
        </div>
      </div>

      <!-- スタートボタン -->
      <button
        @click="startSession"
        :disabled="!selectedType || !selectedDuration || (selectedType === 'focus' && !selectedSubjectArea)"
        class="w-full py-3 px-4 bg-red-500 text-white font-bold rounded-lg hover:bg-red-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
      >
        セッション開始
      </button>

      <!-- 時間計測紹介 -->
      <div class="mt-8 p-4 bg-green-50 border border-green-200 rounded-lg">
        <h4 class="font-semibold text-green-800 mb-2">⏰ 自由な時間で学習したい方に</h4>
        <p class="text-sm text-green-700 mb-3">
          時間を気にせず自分のペースで学習したい場合は、シンプルな時間計測もご利用いただけます。
        </p>
        <router-link 
          to="/study" 
          class="inline-block bg-green-500 hover:bg-green-600 text-white text-sm px-3 py-2 rounded transition-colors"
        >
          時間計測を試す
        </router-link>
      </div>
    </div>

    <!-- アクティブセッション表示 -->
    <div v-else-if="isActive || currentSession" class="active-session text-center">
      <div class="mb-4">
        <h3 class="text-2xl font-bold text-gray-800 mb-2">
          {{ currentSessionTypeLabel }}
        </h3>
        <div class="text-sm text-gray-600">
          {{ formatDateTime(currentSession?.started_at) }}
        </div>
      </div>

      <!-- タイマー表示 -->
      <div class="timer-display mb-6">
        <div
          :class="[
            'text-6xl font-mono font-bold mb-4',
            timeRemaining <= 60 ? 'text-red-500' : 'text-gray-800'
          ]"
        >
          {{ formatTime(globalPomodoroTimer.timeRemaining) }}
        </div>
        
        <!-- プログレスバー -->
        <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
          <div
            :class="[
              'h-2 rounded-full transition-all duration-1000',
              currentSession?.session_type === 'focus' ? 'bg-red-500' : 'bg-green-500'
            ]"
            :style="{ width: `${progressPercentage}%` }"
          ></div>
        </div>

        <div class="text-sm text-gray-600">
          残り時間: {{ Math.floor(timeRemaining / 60) }}分{{ timeRemaining % 60 }}秒
        </div>
      </div>

      <!-- コントロールボタン -->
      <div class="controls grid grid-cols-2 gap-3 mb-4">
        <button
          @click="pauseSession"
          v-if="!isPaused"
          class="py-2 px-4 bg-yellow-500 text-white font-medium rounded-lg hover:bg-yellow-600 transition-colors"
        >
          一時停止
        </button>
        <button
          @click="resumeSession"
          v-else
          class="py-2 px-4 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors"
        >
          再開
        </button>
        
        <button
          @click="completeSession"
          class="py-2 px-4 bg-blue-500 text-white font-medium rounded-lg hover:bg-blue-600 transition-colors"
        >
          完了
        </button>
      </div>

      <button
        @click="stopSession"
        class="w-full py-2 px-4 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors"
      >
        セッション中止
      </button>

      <!-- ノート入力 -->
      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">メモ（オプション）</label>
        <textarea
          v-model="sessionNotes"
          placeholder="セッションについてのメモを入力..."
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
          rows="2"
        ></textarea>
      </div>
    </div>

    <!-- 今日の統計 -->
    <div v-if="!isActive && !currentSession && todayStats" class="stats-section mt-6 p-4 bg-gray-50 rounded-lg">
      <h4 class="font-semibold text-gray-800 mb-3">今日の統計</h4>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <div class="text-gray-600">完了セッション</div>
          <div class="font-bold text-lg">{{ todayStats.total_sessions }}</div>
        </div>
        <div>
          <div class="text-gray-600">集中時間</div>
          <div class="font-bold text-lg">{{ Math.floor(todayStats.total_focus_time / 60) }}h {{ todayStats.total_focus_time % 60 }}m</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PomodoroTimer',
  inject: ['globalPomodoroTimer', 'startGlobalPomodoroTimer', 'stopGlobalPomodoroTimer'],
  data() {
    return {
      // セッション設定
      selectedType: 'focus',
      selectedDuration: 25,
      selectedSubjectArea: '',
      settings: {
        sound_enabled: true,
        auto_start: true,  // デフォルトで自動開始を有効に
      },
      
      // セッション管理
      isPaused: false,
      sessionNotes: '',
      
      // データ
      availableSubjectAreas: [],
      todayStats: null,
      
      // 定数
      sessionTypes: [
        { value: 'focus', label: '集中' },
        { value: 'short_break', label: '短い休憩' },
        { value: 'long_break', label: '長い休憩' }
      ],
      
      durations: {
        focus: [15, 25, 50],
        short_break: [5, 10, 15],
        long_break: [15, 20, 30]
      }
    }
  },
  
  computed: {
    availableDurations() {
      return this.durations[this.selectedType] || [];
    },
    
    // グローバルタイマーの状態を参照
    isActive() {
      return this.globalPomodoroTimer.isActive;
    },
    
    currentSession() {
      return this.globalPomodoroTimer.currentSession;
    },
    
    timeRemaining() {
      return this.globalPomodoroTimer.timeRemaining;
    },
    
    currentSessionTypeLabel() {
      const labels = {
        focus: '🎯 集中セッション',
        short_break: '☕ 短い休憩',
        long_break: '🛋️ 長い休憩'
      };
      return labels[this.currentSession?.session_type] || '';
    },
    
    progressPercentage() {
      if (!this.currentSession) return 0;
      const totalTime = this.currentSession.planned_duration * 60;
      const elapsed = totalTime - this.globalPomodoroTimer.timeRemaining;
      return Math.min(100, (elapsed / totalTime) * 100);
    }
  },
  
  watch: {
    selectedType() {
      // タイプが変更されたら最初の選択肢を自動選択
      this.selectedDuration = this.availableDurations[0] || null;
    }
  },
  
  async mounted() {
    await this.checkCurrentSession();
    await this.loadAvailableSubjectAreas();
    await this.loadTodayStats();
    
    // ページを離れる時の確認
    window.addEventListener('beforeunload', this.handleBeforeUnload);
  },
  
  beforeUnmount() {
    window.removeEventListener('beforeunload', this.handleBeforeUnload);
  },
  
  methods: {
    async checkCurrentSession() {
      try {
        console.log('現在のポモドーロセッション確認開始...');
        const response = await fetch('/api/pomodoro/current', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        });
        
        console.log('ポモドーロcurrentレスポンス状態:', response.status);
        
        if (response.ok) {
          const data = await response.json();
          console.log('API側の現在のポモドーロセッション:', data);
          
          // グローバルタイマーが動いていない場合、API側のセッションでタイマーを開始
          if (!this.globalPomodoroTimer.isActive && data) {
            console.log('API側にアクティブセッション発見、グローバルタイマーを再開');
            
            // セッション開始時刻から経過時間を計算
            const startedAt = new Date(data.started_at).getTime();
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            const totalDuration = data.planned_duration * 60;
            const remaining = Math.max(0, totalDuration - elapsed);
            
            if (remaining > 0) {
              // タイマーを復元
              this.globalPomodoroTimer.currentSession = data;
              this.globalPomodoroTimer.isActive = true;
              this.globalPomodoroTimer.startTime = startedAt;
              this.globalPomodoroTimer.timeRemaining = remaining;
              
              this.startGlobalPomodoroTimer(data);
            } else {
              // 時間切れなので自動完了
              await this.autoCompleteExpiredSession(data);
            }
          }
        } else if (response.status === 404) {
          console.log('アクティブなポモドーロセッションなし');
          // グローバルタイマーも停止
          if (this.globalPomodoroTimer.isActive) {
            console.log('グローバルタイマーを停止（API側にセッションなし）');
            this.stopGlobalPomodoroTimer();
          }
        } else {
          const errorData = await response.json();
          console.error('ポモドーロセッション取得エラー:', errorData);
        }
      } catch (error) {
        console.error('現在のセッション取得エラー:', error);
      }
    },
    
    async loadAvailableSubjectAreas() {
      try {
        console.log('学習分野取得開始...');
        const token = localStorage.getItem('auth_token');
        
        const response = await fetch('/api/user/subject-areas', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
          }
        });
        
        console.log('学習分野レスポンス状態:', response.status);
        const data = await response.json();
        console.log('学習分野データ:', data);
        
        if (response.ok) {
          this.availableSubjectAreas = data.subject_areas || [];
          console.log('取得した学習分野:', this.availableSubjectAreas);
        } else {
          console.error('学習分野APIエラー:', data);
        }
      } catch (error) {
        console.error('学習分野取得エラー:', error);
      }
    },
    
    async loadTodayStats() {
      try {
        const today = new Date().toISOString().split('T')[0];
        const response = await fetch(`/api/pomodoro/stats?start_date=${today}&end_date=${today}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Accept': 'application/json'
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          this.todayStats = data.stats;
        }
      } catch (error) {
        console.error('統計取得エラー:', error);
      }
    },
    
    async startSession() {
      try {
        const sessionData = {
          session_type: this.selectedType,
          planned_duration: this.selectedDuration,
          study_session_id: null, // ポモドーロは独立運用
          subject_area_id: this.selectedSubjectArea, // 学習分野を直接記録
          settings: {
            focus_duration: this.durations.focus[1], // デフォルト25分
            short_break_duration: this.durations.short_break[0], // デフォルト5分
            long_break_duration: this.durations.long_break[0], // デフォルト15分
            auto_start_break: this.settings.auto_start,
            auto_start_focus: this.settings.auto_start,
            sound_enabled: this.settings.sound_enabled,
          }
        };
        
        const response = await fetch('/api/pomodoro', {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(sessionData)
        });
        
        if (response.ok) {
          const data = await response.json();
          this.startGlobalPomodoroTimer(data);
          this.sessionNotes = '';
        } else {
          const errorData = await response.json();
          alert(errorData.message || 'セッション開始エラー');
        }
      } catch (error) {
        console.error('セッション開始エラー:', error);
        alert('セッション開始に失敗しました');
      }
    },
    
    
    async completeSession() {
      if (!this.currentSession) return;
      
      const actualDuration = Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60); // 分に変換
      
      try {
        const response = await fetch(`/api/pomodoro/${this.currentSession.id}/complete`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            actual_duration: actualDuration,
            was_interrupted: false,
            notes: this.sessionNotes
          })
        });
        
        if (response.ok) {
          this.stopGlobalPomodoroTimer();
          this.showCompletionMessage();
          await this.loadTodayStats();
          
          // 2秒後に次のセッション提案
          if (this.settings.auto_start) {
            setTimeout(() => {
              this.suggestNextSession();
            }, 2000);
          }
        }
      } catch (error) {
        console.error('セッション完了エラー:', error);
      }
    },
    
    async stopSession() {
      if (!this.currentSession) return;
      
      if (!confirm('セッションを中止しますか？')) return;
      
      const actualDuration = Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60);
      
      try {
        const response = await fetch(`/api/pomodoro/${this.currentSession.id}/complete`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            actual_duration: actualDuration,
            was_interrupted: true,
            notes: this.sessionNotes
          })
        });
        
        if (response.ok) {
          this.stopGlobalPomodoroTimer();
          await this.loadTodayStats();
        }
      } catch (error) {
        console.error('セッション中止エラー:', error);
      }
    },
    
    
    playNotificationSound() {
      if (this.settings.sound_enabled) {
        // ブラウザの通知音を再生
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmccBzuU3OzMeShiSNcjGiusY');
        audio.play().catch(console.error);
      }
      
      // ブラウザ通知
      if (Notification.permission === 'granted') {
        new Notification('ポモドーロタイマー', {
          body: `${this.currentSessionTypeLabel}が完了しました！`,
          icon: '/favicon.ico'
        });
      }
    },
    
    showCompletionMessage() {
      const messages = {
        focus: '🎉 集中セッション完了！お疲れ様でした！',
        short_break: '☕ 短い休憩完了！',
        long_break: '🛋️ 長い休憩完了！リフレッシュできましたか？'
      };
      
      alert(messages[this.currentSession?.session_type] || 'セッション完了！');
    },

    async autoCompleteExpiredSession(session) {
      try {
        console.log('期限切れセッションを自動完了:', session.id);
        
        const response = await fetch(`/api/pomodoro/${session.id}/complete`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            actual_duration: session.planned_duration,
            was_interrupted: false,
            notes: '自動完了（時間切れ）'
          })
        });
        
        if (response.ok) {
          console.log('期限切れセッション自動完了成功');
        } else {
          console.error('期限切れセッション自動完了失敗');
        }
      } catch (error) {
        console.error('期限切れセッション自動完了エラー:', error);
      }
    },

    suggestNextSession() {
      // 次のセッションを提案するロジック
      // フォーカス -> 短い休憩 -> フォーカス -> 長い休憩のサイクル
    },
    
    formatTime(seconds) {
      const mins = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    },
    
    formatDateTime(datetime) {
      if (!datetime) return '';
      return new Date(datetime).toLocaleString('ja-JP');
    },
    
    handleBeforeUnload(event) {
      if (this.isActive) {
        event.preventDefault();
        event.returnValue = '';
      }
    }
  }
}
</script>

<style scoped>
.pomodoro-timer {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.timer-display {
  user-select: none;
}

/* タイマーのアニメーション */
@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.timer-display.pulse {
  animation: pulse 2s infinite;
}

/* 通知スタイル */
.notification {
  position: fixed;
  top: 20px;
  right: 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  padding: 16px;
  z-index: 1000;
}
</style>