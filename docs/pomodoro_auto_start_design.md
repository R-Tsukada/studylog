# ポモドーロタイマー自動開始機能の詳細設計 (最終版)

## 問題分析

### 現在の実装状況
1. **App.vue**: グローバルタイマー管理 + 自動開始ロジック実装済み
2. **PomodoroTimer.vue**: UIコンポーネント + 独自の不完全な自動開始実装
3. **問題**: 2つの異なる完了処理パス + 自動開始ロジックの重複

### 根本原因
- **責任分離の不明確**: App.vueとPomodoroTimer.vueで自動開始ロジックが重複
- **完了処理の分岐**: 手動完了と自動完了で異なるパスを辿る
- **設定の不一致**: UIの設定とバックエンドAPIの設定形式が異なる
- **セッションサイクルロジックの不足**: 4回ポモドーロ後の長休憩ロジックが未実装

## 設計方針

### 1. Single Responsibility Principle (単一責任原則)
- **App.vue**: グローバルタイマー状態管理 + 自動開始ロジック + セッションサイクル管理
- **PomodoroTimer.vue**: UI表示 + ユーザーインタラクション

### 2. 既存影響の最小化
- 既存のApp.vueの自動開始ロジックを活用・拡張
- PomodoroTimer.vueの修正を最小限に抑制
- APIインターフェースの変更なし

### 3. 保守性・可読性の向上
- 自動開始ロジックをApp.vueに一元化
- 明確なインターフェース定義
- デバッグ用ログの統一

## 詳細設計

### Phase 1: 定数定義の拡張

#### 1.1 constants.js の更新
```javascript
// resources/js/utils/constants.js に追加
export const POMODORO_CONSTANTS = {
  // 既存の定数...
  
  // 自動開始関連（新規）
  AUTO_START_DELAY_MS: 3000,          // 3秒後に自動開始（推奨値）
  AUTO_START_COUNTDOWN_INTERVAL: 100,  // カウントダウン更新間隔（100ms）
  
  // ポモドーロサイクル管理（新規）
  POMODORO_CYCLE_LENGTH: 4,           // 4回のfocus後にlong_break
  NOTIFICATION_PERMISSION_REQUEST_DELAY: 1000, // 通知権限要求の遅延
  
  // セッション検証（新規）
  MAX_SESSION_DURATION_MINUTES: 240,  // 最大セッション時間（4時間）
  MIN_SESSION_DURATION_MINUTES: 1,    // 最小セッション時間（1分）
  
  // エラーハンドリング（新規）
  API_TIMEOUT_MS: 10000,              // API通信タイムアウト（10秒）
  MAX_RETRY_ATTEMPTS: 3,              // 最大リトライ回数
  
  // セッションタイプ検証（新規）
  ALLOWED_SESSION_TYPES: ['focus', 'short_break', 'long_break']
};
```

### Phase 2: App.vue (タイマー管理層) の実装

#### 2.1 データ構造の拡張
```javascript
// App.vue の data() 更新
data() {
  return {
    // 既存のdata...
    
    // ポモドーロサイクル管理（新規）
    pomodoroCounterState: {
      completedFocusSessions: 0,         // 完了した集中セッション数
      currentCycleStartTime: null,       // 現在のサイクル開始時間
      lastSessionCompletedAt: null,      // 最後のセッション完了時間
      cycleHistory: []                   // サイクル履歴（デバッグ用）
    },
    
    // 自動開始管理（新規）
    autoStartState: {
      timeoutId: null,                   // setTimeout ID
      isPending: false,                  // 自動開始待機中フラグ
      pendingSession: null,              // 次のセッション情報
      startTime: null,                   // 自動開始スケジュール時刻
      remainingMs: 0                     // 残り時間（ミリ秒）
    }
  }
},

async mounted() {
  // 既存の処理...
  
  // 通知権限をリクエスト（遅延実行）
  setTimeout(() => {
    this.requestNotificationPermission();
  }, POMODORO_CONSTANTS.NOTIFICATION_PERMISSION_REQUEST_DELAY);
},

methods: {
  // 新規: 通知権限リクエスト
  async requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
      try {
        const permission = await Notification.requestPermission();
        console.log('通知権限:', permission);
      } catch (error) {
        console.warn('通知権限リクエストエラー:', error);
      }
    }
  },

  // 新規: PomodoroTimer.vueからの完了処理統一
  async handleManualTimerComplete(completedSession, actualDuration, wasInterrupted, notes) {
    try {
      // セッション検証
      this.validateSession(completedSession);
      
      // 1. API完了処理（graceful degradation対応）
      await this.completeCurrentSessionSafely(completedSession, actualDuration, wasInterrupted, notes);
      
      // 2. ポモドーロカウンター更新（中断時は更新しない）
      if (!wasInterrupted) {
        this.updatePomodoroCounter(completedSession);
      } else {
        console.log('セッション中断のためカウンター更新をスキップ');
      }
      
      // 3. 通知処理
      this.showCompletionNotification(completedSession);
      
      // 4. タイマー停止
      this.stopGlobalPomodoroTimer();
      
      // 5. 自動開始判定（改良版）
      if (!wasInterrupted) {
        this.scheduleAutoStartIfEnabled(completedSession);
      }
      
    } catch (error) {
      console.error('手動完了処理エラー:', error);
      this.showError('セッション完了処理でエラーが発生しました: ' + error.message);
    }
  },
  
  // 新規: セッション検証
  validateSession(session) {
    if (!session?.id) {
      throw new Error('セッションIDが存在しません');
    }
    
    if (!POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES.includes(session.session_type)) {
      throw new Error('無効なセッションタイプ: ' + session.session_type);
    }
    
    return true;
  },
  
  // 改良: graceful degradation対応
  async completeCurrentSessionSafely(session, actualDuration = null, wasInterrupted = false, notes = null) {
    try {
      const duration = actualDuration || (this.pomodoroTimerInstance ? 
        this.pomodoroTimerInstance.getActualDurationMinutes() :
        Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60));
      
      // 時間の妥当性チェック
      if (duration > POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES) {
        console.warn('セッション時間が上限を超えています:', duration);
      }
      
      const response = await axios.post(`/api/pomodoro/${session.id}/complete`, {
        actual_duration: duration,
        was_interrupted: wasInterrupted,
        notes: notes || 'v2.0タイマー完了'
      }, {
        timeout: POMODORO_CONSTANTS.API_TIMEOUT_MS,
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });
      
      if (response.status === 200) {
        this.logStateTransition('completing', 'completed', 'api_success');
        console.log('セッション完了成功 (統一処理):', session.session_type, duration + '分');
      }
    } catch (error) {
      console.error('API完了エラー:', error);
      this.showError('セッション記録に失敗しましたが、タイマーは継続します');
      // APIエラーでも処理を継続（graceful degradation）
    }
  },
  
  // 新規: 安全なAuthToken取得
  getAuthToken() {
    try {
      return localStorage.getItem('auth_token');
    } catch (error) {
      console.error('localStorage access error:', error);
      return null;
    }
  },
  
  // 新規: ポモドーロカウンター管理
  updatePomodoroCounter(completedSession) {
    if (completedSession.session_type === 'focus') {
      this.pomodoroCounterState.completedFocusSessions++;
      this.pomodoroCounterState.lastSessionCompletedAt = Date.now();
      
      // サイクル履歴に記録
      this.pomodoroCounterState.cycleHistory.push({
        timestamp: Date.now(),
        sessionType: 'focus',
        count: this.pomodoroCounterState.completedFocusSessions
      });
      
      this.logStateTransition('focus_completed', 'counter_updated', 
        `count: ${this.pomodoroCounterState.completedFocusSessions}`);
    }
    
    // 新しいサイクル開始の判定
    if (this.pomodoroCounterState.completedFocusSessions === 1 && 
        !this.pomodoroCounterState.currentCycleStartTime) {
      this.pomodoroCounterState.currentCycleStartTime = Date.now();
    }
    
    // サイクル完了時のリセット
    if (this.pomodoroCounterState.completedFocusSessions >= POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH && 
        completedSession.session_type === 'long_break') {
      this.resetPomodoroCounter();
    }
  },
  
  // 新規: サイクルリセット
  resetPomodoroCounter() {
    const completedCycle = {
      cycleNumber: Math.floor(this.pomodoroCounterState.cycleHistory.length / POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH) + 1,
      startTime: this.pomodoroCounterState.currentCycleStartTime,
      endTime: Date.now(),
      totalFocusSessions: this.pomodoroCounterState.completedFocusSessions
    };
    
    // サイクル履歴に完了情報を記録
    this.pomodoroCounterState.cycleHistory.push(completedCycle);
    
    this.pomodoroCounterState = {
      completedFocusSessions: 0,
      currentCycleStartTime: null,
      lastSessionCompletedAt: null,
      cycleHistory: this.pomodoroCounterState.cycleHistory // 履歴は保持
    };
    
    this.logStateTransition('cycle_complete', 'counter_reset', 'new_cycle_start');
  },
  
  // 新規: 自動開始スケジューリング（キャンセル機能付き）
  scheduleAutoStartIfEnabled(completedSession) {
    // 自動開始設定確認（個別制御対応）
    const settings = completedSession.settings;
    const nextSessionType = this.determineNextSessionType(completedSession);
    
    const shouldAutoStart = this.shouldAutoStartNext(nextSessionType, settings);
    
    if (!shouldAutoStart) {
      console.log('自動開始設定が無効:', { nextSessionType, settings });
      return;
    }
    
    // 既存の自動開始をキャンセル
    this.cancelAutoStart();
    
    // 新しい自動開始をスケジュール
    this.autoStartState.isPending = true;
    this.autoStartState.startTime = Date.now();
    this.autoStartState.remainingMs = POMODORO_CONSTANTS.AUTO_START_DELAY_MS;
    this.autoStartState.pendingSession = {
      type: nextSessionType,
      duration: this.getSessionDuration(nextSessionType, settings),
      settings: settings
    };
    
    console.log('自動開始スケジュール:', this.autoStartState.pendingSession);
    
    this.autoStartState.timeoutId = setTimeout(() => {
      this.executeAutoStart();
    }, POMODORO_CONSTANTS.AUTO_START_DELAY_MS);
    
    this.logStateTransition('scheduled', 'auto_start_pending', 
      `delay: ${POMODORO_CONSTANTS.AUTO_START_DELAY_MS}ms`);
  },
  
  // 新規: 次セッションタイプ決定ロジック（明確化）
  determineNextSessionType(completedSession) {
    if (completedSession.session_type === 'focus') {
      // ポモドーロカウンターをチェック（4回目なら長休憩）
      if (this.pomodoroCounterState.completedFocusSessions % POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH === 0) {
        return 'long_break';
      } else {
        return 'short_break';
      }
    } else if (completedSession.session_type === 'short_break' || 
               completedSession.session_type === 'long_break') {
      return 'focus';
    }
    
    // フォールバック
    console.warn('不明なセッションタイプ:', completedSession.session_type);
    return 'focus';
  },
  
  // 新規: 自動開始可否判定（個別制御対応）
  shouldAutoStartNext(nextSessionType, settings) {
    if (nextSessionType === 'focus') {
      return settings?.auto_start_focus ?? settings?.auto_start ?? false;
    } else {
      return settings?.auto_start_break ?? settings?.auto_start ?? false;
    }
  },
  
  // 新規: セッション時間取得
  getSessionDuration(sessionType, settings) {
    const durations = {
      focus: settings?.focus_duration ?? 25,
      short_break: settings?.short_break_duration ?? 5,
      long_break: settings?.long_break_duration ?? 20
    };
    return durations[sessionType] || 25;
  },
  
  // 新規: 自動開始実行
  async executeAutoStart() {
    if (!this.autoStartState.isPending || !this.autoStartState.pendingSession) {
      return;
    }
    
    const pendingSession = this.autoStartState.pendingSession;
    
    try {
      this.logStateTransition('auto_start_pending', 'executing', 'timeout_triggered');
      
      // セッションデータの検証
      this.validateSessionData({
        session_type: pendingSession.type,
        planned_duration: pendingSession.duration
      });
      
      // APIで次のセッションを作成
      const sessionData = {
        session_type: pendingSession.type,
        planned_duration: pendingSession.duration,
        study_session_id: null,
        subject_area_id: pendingSession.type === 'focus' ? 
          this.globalPomodoroTimer.currentSession?.subject_area_id : null,
        settings: pendingSession.settings,
        is_auto_started: true  // 自動開始フラグ
      };
      
      const response = await axios.post('/api/pomodoro', sessionData, {
        timeout: POMODORO_CONSTANTS.API_TIMEOUT_MS,
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });
      
      if (response.status === 201 || response.status === 200) {
        const newSession = response.data;
        console.log('次のセッション自動開始成功:', newSession.session_type);
        
        // グローバルタイマーで新しいセッションを開始
        this.startGlobalPomodoroTimer(newSession);
        
        // 自動開始通知
        this.showAutoStartNotification(newSession);
        
        this.logStateTransition('executing', 'auto_started', newSession.session_type);
      } else {
        throw new Error(`API失敗: ${response.status}`);
      }
    } catch (error) {
      console.error('自動開始実行エラー:', error);
      this.showError('次のセッションの自動開始に失敗しました: ' + error.message);
    } finally {
      // 自動開始状態をクリア
      this.clearAutoStartState();
    }
  },
  
  // 新規: セッションデータ検証
  validateSessionData(sessionData) {
    if (!POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES.includes(sessionData.session_type)) {
      throw new Error('無効なセッションタイプ: ' + sessionData.session_type);
    }
    
    if (sessionData.planned_duration > POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES) {
      throw new Error('セッション時間が上限を超えています: ' + sessionData.planned_duration);
    }
    
    if (sessionData.planned_duration < POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES) {
      throw new Error('セッション時間が下限を下回っています: ' + sessionData.planned_duration);
    }
    
    return true;
  },
  
  // 新規: 自動開始キャンセル
  cancelAutoStart() {
    if (this.autoStartState.timeoutId) {
      clearTimeout(this.autoStartState.timeoutId);
      this.logStateTransition('auto_start_pending', 'cancelled', 'user_action');
    }
    this.clearAutoStartState();
  },
  
  // 新規: 自動開始状態クリア
  clearAutoStartState() {
    this.autoStartState = {
      timeoutId: null,
      isPending: false,
      pendingSession: null,
      startTime: null,
      remainingMs: 0
    };
  },
  
  // 新規: 自動開始通知
  showAutoStartNotification(session) {
    if (Notification.permission === 'granted') {
      const messages = {
        focus: '🎯 集中セッション自動開始！',
        short_break: '☕ 短い休憩自動開始！',
        long_break: '🛋️ 長い休憩自動開始！'
      };
      
      new Notification('ポモドーロタイマー', {
        body: messages[session.session_type] || '次のセッション自動開始！',
        icon: '/favicon.ico',
        requireInteraction: false,
        silent: false
      });
    }
  },
  
  // 新規: 状態遷移ログ（統一フォーマット）
  logStateTransition(from, to, trigger) {
    console.log(`[Pomodoro] ${from} → ${to} (trigger: ${trigger})`, {
      timestamp: new Date().toISOString(),
      currentSettings: this.globalPomodoroTimer.currentSession?.settings,
      autoStartPending: this.autoStartState.isPending,
      pomodoroCount: this.pomodoroCounterState.completedFocusSessions,
      cyclePhase: `${this.pomodoroCounterState.completedFocusSessions}/${POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH}`
    });
  }
}
```

### Phase 3: PomodoroTimer.vue (UI層) の実装

#### 3.1 UI改善とカウントダウン表示
```javascript
// PomodoroTimer.vue の実装
inject: [
  'globalPomodoroTimer', 
  'startGlobalPomodoroTimer', 
  'stopGlobalPomodoroTimer',
  'pauseGlobalPomodoroTimer',
  'resumeGlobalPomodoroTimer',
  'handleManualTimerComplete',     // 新規追加
  'cancelAutoStart',              // 新規追加
  'pomodoroCounterState',         // 新規追加（読み取り専用）
  'autoStartState'                // 新規追加（読み取り専用）
],

data() {
  return {
    // 既存のdata...
    
    // 設定の個別制御（改良版）
    settings: {
      sound_enabled: true,
      auto_start: true,              // 基本設定（後方互換性）
      auto_start_break: null,        // 個別設定: 集中→休憩の自動開始
      auto_start_focus: null,        // 個別設定: 休憩→集中の自動開始
    },
    
    // カウントダウン表示（新規）
    autoStartCountdown: 0,
    countdownInterval: null
  }
},

computed: {
  // 新規: 自動開始待機中かどうか
  isAutoStartPending() {
    return this.autoStartState?.isPending || false;
  },
  
  // 新規: 現在のポモドーロカウント表示
  currentPomodoroCount() {
    return this.pomodoroCounterState?.completedFocusSessions || 0;
  },
  
  // 新規: サイクル進捗表示
  cycleProgress() {
    const current = this.currentPomodoroCount % POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH;
    return `${current}/${POMODORO_CONSTANTS.POMODORO_CYCLE_LENGTH}`;
  }
},

watch: {
  // 新規: 自動開始待機状態の監視
  isAutoStartPending(newVal) {
    if (newVal) {
      this.startCountdown();
    } else {
      this.stopCountdown();
    }
  }
},

mounted() {
  // 既存の処理...
  
  // 通知権限の確認
  this.checkNotificationPermission();
},

beforeUnmount() {
  // 既存の処理...
  
  // カウントダウンのクリーンアップ
  this.stopCountdown();
},

methods: {
  // 新規: 通知権限確認
  checkNotificationPermission() {
    if ('Notification' in window) {
      console.log('通知権限状態:', Notification.permission);
    }
  },
  
  // 新規: カウントダウン開始
  startCountdown() {
    this.autoStartCountdown = POMODORO_CONSTANTS.AUTO_START_DELAY_MS;
    this.countdownInterval = setInterval(() => {
      this.autoStartCountdown -= POMODORO_CONSTANTS.AUTO_START_COUNTDOWN_INTERVAL;
      if (this.autoStartCountdown <= 0) {
        this.stopCountdown();
      }
    }, POMODORO_CONSTANTS.AUTO_START_COUNTDOWN_INTERVAL);
  },
  
  // 新規: カウントダウン停止
  stopCountdown() {
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
      this.countdownInterval = null;
      this.autoStartCountdown = 0;
    }
  },
  
  // 既存のcompleteSession()を修正
  async completeSession() {
    if (!this.currentSession) return;
    
    const actualDuration = Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60);
    
    try {
      // App.vueの統一処理を呼び出し
      await this.handleManualTimerComplete(
        this.currentSession,
        actualDuration,
        false, // 手動完了
        this.sessionNotes
      );
      
      // UI更新
      await this.loadTodayStats();
      this.sessionNotes = ''; // リセット
      
    } catch (error) {
      console.error('セッション完了エラー:', error);
      this.showSafeErrorMessage(error);
    }
  },
  
  // 既存のstopSession()を修正
  async stopSession() {
    if (!this.currentSession) return;
    
    if (!confirm('セッションを中止しますか？')) return;
    
    const actualDuration = Math.ceil((Date.now() - this.globalPomodoroTimer.startTime) / 1000 / 60);
    
    try {
      // App.vueの統一処理を呼び出し
      await this.handleManualTimerComplete(
        this.currentSession,
        actualDuration,
        true, // 中断
        this.sessionNotes
      );
      
      // UI更新
      await this.loadTodayStats();
      this.sessionNotes = ''; // リセット
      
    } catch (error) {
      console.error('セッション中止エラー:', error);
      this.showSafeErrorMessage(error);
    }
  },
  
  // 新規: 自動開始キャンセル
  cancelAutoStartSession() {
    this.cancelAutoStart();
    console.log('自動開始をキャンセルしました');
  },
  
  // 改良: 標準化された設定オブジェクト生成（個別制御対応）
  buildStandardSettings() {
    return {
      // 時間設定（App.vueのstartNextAutoSessionで使用）
      focus_duration: this.durations.focus[1] || 25,
      short_break_duration: this.durations.short_break[0] || 5,
      long_break_duration: this.durations.long_break[1] || 20,
      
      // 自動開始設定（個別制御対応）
      auto_start_break: this.settings.auto_start_break ?? this.settings.auto_start,
      auto_start_focus: this.settings.auto_start_focus ?? this.settings.auto_start,
      
      // その他設定
      sound_enabled: this.settings.sound_enabled,
      
      // デバッグ用
      ui_version: '2.0',
      created_at: new Date().toISOString()
    };
  },
  
  // 改良: セッション開始（エラーハンドリング強化）
  async startSession() {
    try {
      const sessionData = {
        session_type: this.selectedType,
        planned_duration: this.selectedDuration,
        study_session_id: null,
        subject_area_id: this.selectedSubjectArea,
        settings: this.buildStandardSettings()
      };
      
      // セッションデータの事前検証
      this.validateSessionDataLocal(sessionData);
      
      const response = await fetch('/api/pomodoro', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.getAuthToken()}`,
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
        throw new Error(errorData.message || 'セッション開始エラー');
      }
    } catch (error) {
      console.error('セッション開始エラー:', error);
      this.showSafeErrorMessage(error);
    }
  },
  
  // 新規: ローカルセッションデータ検証
  validateSessionDataLocal(sessionData) {
    if (!POMODORO_CONSTANTS.ALLOWED_SESSION_TYPES.includes(sessionData.session_type)) {
      throw new Error('無効なセッションタイプが選択されています');
    }
    
    if (sessionData.planned_duration > POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES) {
      throw new Error(`セッション時間は${POMODORO_CONSTANTS.MAX_SESSION_DURATION_MINUTES}分以下で設定してください`);
    }
    
    if (sessionData.planned_duration < POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES) {
      throw new Error(`セッション時間は${POMODORO_CONSTANTS.MIN_SESSION_DURATION_MINUTES}分以上で設定してください`);
    }
    
    return true;
  },
  
  // 新規: 安全なエラーメッセージ表示
  showSafeErrorMessage(error) {
    const safeMessage = error.message ? 
      error.message.replace(/<[^>]*>/g, '') : // HTMLタグ除去
      '操作に失敗しました';
    alert(safeMessage);
  },
  
  // 新規: 安全なlocalStorageアクセス
  getAuthToken() {
    try {
      return localStorage.getItem('auth_token');
    } catch (error) {
      console.error('localStorage access error:', error);
      return null;
    }
  }
  
  // 不要なメソッドを削除
  // - suggestNextSession()メソッドを削除（不要になる）
  // - 既存の自動開始ロジック（512-516行目）を削除
}
```

#### 3.2 テンプレート更新（自動開始待機状態とポモドーロカウンター表示）
```vue
<!-- PomodoroTimer.vue の template 更新 -->
<template>
  <div class="pomodoro-timer">
    
    <!-- ポモドーロサイクル表示（新規） -->
    <div v-if="!isActive && !currentSession" class="cycle-status mb-4 p-3 rounded-lg border"
         style="background-color: var(--color-muted-blue-light); border-color: var(--color-muted-blue);">
      <div class="text-sm font-medium" style="color: var(--color-muted-blue-dark);">
        🍅 ポモドーロサイクル: {{ cycleProgress }}
      </div>
      <div class="text-xs" style="color: var(--color-muted-gray-dark);">
        完了セッション: {{ currentPomodoroCount }}回
      </div>
    </div>
    
    <!-- 既存のセッション設定... -->
    
    <!-- 自動開始待機状態の表示（新規） -->
    <div v-if="isAutoStartPending" class="auto-start-pending mb-4 p-3 rounded-lg border" 
         style="background-color: var(--color-muted-green-light); border-color: var(--color-muted-green);">
      <div class="flex items-center justify-between">
        <div>
          <div class="font-medium text-sm" style="color: var(--color-muted-green-dark);">
            ⏱️ 次のセッションを自動開始します
          </div>
          <div class="text-xs" style="color: var(--color-muted-gray-dark);">
            {{ Math.ceil(autoStartCountdown / 1000) }}秒後に開始
          </div>
        </div>
        <button
          @click="cancelAutoStartSession"
          class="px-3 py-1 text-xs rounded text-white transition-colors"
          style="background-color: var(--color-muted-pink);"
          @mouseover="$event.target.style.backgroundColor = 'var(--color-muted-pink-dark)'"
          @mouseout="$event.target.style.backgroundColor = 'var(--color-muted-pink)'"
        >
          キャンセル
        </button>
      </div>
    </div>
    
    <!-- 設定オプション（個別制御対応） -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-2">
        <label class="text-sm font-medium" style="color: var(--color-muted-blue-dark);">音声通知</label>
        <button
          @click="settings.sound_enabled = !settings.sound_enabled"
          :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors']"
          :style="{
            backgroundColor: settings.sound_enabled ? 'var(--color-muted-blue)' : 'var(--color-muted-gray)'
          }"
        >
          <span
            :class="[
              'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
              settings.sound_enabled ? 'translate-x-6' : 'translate-x-1'
            ]"
          />
        </button>
      </div>
      
      <!-- 基本自動開始設定 -->
      <div class="flex items-center justify-between mb-2">
        <label class="text-sm font-medium" style="color: var(--color-muted-blue-dark);">自動で次のセッションを開始</label>
        <button
          @click="settings.auto_start = !settings.auto_start"
          :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors']"
          :style="{
            backgroundColor: settings.auto_start ? 'var(--color-muted-blue)' : 'var(--color-muted-gray)'
          }"
        >
          <span
            :class="[
              'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
              settings.auto_start ? 'translate-x-6' : 'translate-x-1'
            ]"
          />
        </button>
      </div>
      
      <!-- 個別制御設定（詳細設定として展開可能） -->
      <div v-if="settings.auto_start" class="ml-4 mt-2 p-2 rounded border"
           style="background-color: var(--color-muted-white); border-color: var(--color-muted-gray);">
        <div class="text-xs font-medium mb-2" style="color: var(--color-muted-gray-dark);">詳細設定</div>
        
        <div class="flex items-center justify-between mb-1">
          <label class="text-xs" style="color: var(--color-muted-gray-dark);">集中→休憩</label>
          <button
            @click="settings.auto_start_break = settings.auto_start_break === null ? true : !settings.auto_start_break"
            :class="['relative inline-flex h-4 w-8 items-center rounded-full transition-colors']"
            :style="{
              backgroundColor: (settings.auto_start_break ?? settings.auto_start) ? 'var(--color-muted-green)' : 'var(--color-muted-gray)'
            }"
          >
            <span
              :class="[
                'inline-block h-3 w-3 transform rounded-full bg-white transition-transform',
                (settings.auto_start_break ?? settings.auto_start) ? 'translate-x-4' : 'translate-x-0.5'
              ]"
            />
          </button>
        </div>
        
        <div class="flex items-center justify-between">
          <label class="text-xs" style="color: var(--color-muted-gray-dark);">休憩→集中</label>
          <button
            @click="settings.auto_start_focus = settings.auto_start_focus === null ? true : !settings.auto_start_focus"
            :class="['relative inline-flex h-4 w-8 items-center rounded-full transition-colors']"
            :style="{
              backgroundColor: (settings.auto_start_focus ?? settings.auto_start) ? 'var(--color-muted-green)' : 'var(--color-muted-gray)'
            }"
          >
            <span
              :class="[
                'inline-block h-3 w-3 transform rounded-full bg-white transition-transform',
                (settings.auto_start_focus ?? settings.auto_start) ? 'translate-x-4' : 'translate-x-0.5'
              ]"
            />
          </button>
        </div>
      </div>
    </div>
    
    <!-- 既存のアクティブセッション表示... -->
  </div>
</template>
```

### Phase 4: provide/inject の拡張

#### 4.1 App.vue の provide 更新
```javascript
// App.vue の provide()メソッド更新
provide() {
  return {
    // 既存
    showError: this.showError,
    showSuccess: this.showSuccess,
    globalPomodoroTimer: this.globalPomodoroTimer,
    startGlobalPomodoroTimer: this.startGlobalPomodoroTimer,
    stopGlobalPomodoroTimer: this.stopGlobalPomodoroTimer,
    pauseGlobalPomodoroTimer: this.pauseGlobalPomodoroTimer,
    resumeGlobalPomodoroTimer: this.resumeGlobalPomodoroTimer,
    
    // 学習タイマー関連
    globalStudyTimer: this.globalStudyTimer,
    startGlobalStudyTimer: this.startGlobalStudyTimer,
    stopGlobalStudyTimer: this.stopGlobalStudyTimer,
    
    // イベントバス
    notifyDataUpdate: this.notifyDataUpdate,
    subscribeToDataUpdate: this.subscribeToDataUpdate,
    unsubscribeFromDataUpdate: this.unsubscribeFromDataUpdate,
    
    // 新規: 統一完了処理と自動開始制御
    handleManualTimerComplete: this.handleManualTimerComplete,
    cancelAutoStart: this.cancelAutoStart,
    pomodoroCounterState: this.pomodoroCounterState,  // 読み取り専用
    autoStartState: this.autoStartState              // 読み取り専用
  }
}
```

## テスト戦略（拡張版）

### 1. 統合テスト（新規）
```javascript
// tests/Unit/Frontend/PomodoroAutoStartIntegrationTest.js
import { POMODORO_CONSTANTS } from '@/utils/constants.js';

describe('ポモドーロ自動開始統合テスト', () => {
  let mockApp, mockPomodoro;

  beforeEach(() => {
    jest.useFakeTimers();
    mockApp = new MockAppComponent();
    mockPomodoro = new MockPomodoroComponent();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('基本的な自動開始機能', () => {
    test('手動完了後の自動開始（focus → short_break）', async () => {
      const session = { 
        id: 1,
        session_type: 'focus', 
        settings: { auto_start_break: true } 
      };
      
      await mockApp.handleManualTimerComplete(session, 25, false, '');
      
      expect(mockApp.autoStartState.isPending).toBe(true);
      expect(mockApp.autoStartState.pendingSession.type).toBe('short_break');
      expect(mockApp.autoStartState.remainingMs).toBe(POMODORO_CONSTANTS.AUTO_START_DELAY_MS);
    });
    
    test('自動開始設定の個別制御', () => {
      const settings = { auto_start_break: true, auto_start_focus: false };
      
      expect(mockApp.shouldAutoStartNext('short_break', settings)).toBe(true);
      expect(mockApp.shouldAutoStartNext('focus', settings)).toBe(false);
    });
  });
  
  describe('ポモドーロサイクル管理', () => {
    test('4回目のfocus後はlong_break', async () => {
      // 3回のfocusセッションを完了
      for (let i = 0; i < 3; i++) {
        await mockApp.handleManualTimerComplete(
          { id: i + 1, session_type: 'focus' }, 25, false, ''
        );
        expect(mockApp.pomodoroCounterState.completedFocusSessions).toBe(i + 1);
        
        await mockApp.handleManualTimerComplete(
          { id: i + 10, session_type: 'short_break' }, 5, false, ''
        );
      }
      
      // 4回目のfocus完了
      await mockApp.handleManualTimerComplete(
        { id: 4, session_type: 'focus' }, 25, false, ''
      );
      
      expect(mockApp.pomodoroCounterState.completedFocusSessions).toBe(4);
      expect(mockApp.determineNextSessionType({ session_type: 'focus' })).toBe('long_break');
    });
    
    test('long_break完了後のサイクルリセット', async () => {
      // 4回のfocusを完了させる
      mockApp.pomodoroCounterState.completedFocusSessions = 4;
      
      await mockApp.handleManualTimerComplete(
        { id: 1, session_type: 'long_break' }, 20, false, ''
      );
      
      expect(mockApp.pomodoroCounterState.completedFocusSessions).toBe(0);
      expect(mockApp.pomodoroCounterState.currentCycleStartTime).toBe(null);
    });
  });
  
  describe('自動開始のキャンセル機能', () => {
    test('自動開始のキャンセル', () => {
      const session = { 
        id: 1,
        session_type: 'focus', 
        settings: { auto_start_break: true } 
      };
      
      mockApp.scheduleAutoStartIfEnabled(session);
      expect(mockApp.autoStartState.isPending).toBe(true);
      
      mockApp.cancelAutoStart();
      expect(mockApp.autoStartState.isPending).toBe(false);
      expect(mockApp.autoStartState.timeoutId).toBe(null);
    });
    
    test('自動開始実行前のキャンセル', () => {
      const session = { 
        id: 1,
        session_type: 'focus', 
        settings: { auto_start_break: true } 
      };
      
      mockApp.scheduleAutoStartIfEnabled(session);
      
      // 途中でキャンセル
      jest.advanceTimersByTime(1000); // 1秒経過
      mockApp.cancelAutoStart();
      
      // さらに時間を進めても実行されない
      jest.advanceTimersByTime(POMODORO_CONSTANTS.AUTO_START_DELAY_MS);
      expect(mockApp.autoStartState.isPending).toBe(false);
    });
  });
  
  describe('エラーハンドリングとGraceful Degradation', () => {
    test('APIエラー時のgraceful degradation', async () => {
      // APIエラーをシミュレート
      mockAxios.post.mockRejectedValue(new Error('Network error'));
      
      const session = { 
        id: 1,
        session_type: 'focus', 
        settings: { auto_start_break: true } 
      };
      
      // エラーが発生してもプロセスは継続
      await expect(mockApp.handleManualTimerComplete(session, 25, false, '')).resolves.not.toThrow();
      
      // 自動開始は継続される
      expect(mockApp.autoStartState.isPending).toBe(true);
    });
    
    test('無効なセッションデータの検証', () => {
      expect(() => {
        mockApp.validateSessionData({
          session_type: 'invalid_type',
          planned_duration: 25
        });
      }).toThrow('無効なセッションタイプ');
      
      expect(() => {
        mockApp.validateSessionData({
          session_type: 'focus',
          planned_duration: 300 // 上限超過
        });
      }).toThrow('セッション時間が上限を超えています');
    });
    
    test('セッションIDなしの処理', async () => {
      const sessionWithoutId = { 
        session_type: 'focus', 
        settings: { auto_start_break: true } 
      };
      
      await expect(
        mockApp.handleManualTimerComplete(sessionWithoutId, 25, false, '')
      ).rejects.toThrow('セッションIDが存在しません');
    });
  });
  
  describe('設定オブジェクトの整合性', () => {
    test('buildStandardSettings()の出力検証', () => {
      mockPomodoro.settings = { 
        auto_start: true, 
        auto_start_break: false,
        auto_start_focus: null,
        sound_enabled: false
      };
      
      const settings = mockPomodoro.buildStandardSettings();
      
      expect(settings.auto_start_break).toBe(false);
      expect(settings.auto_start_focus).toBe(true); // auto_startの値を継承
      expect(settings.sound_enabled).toBe(false);
      expect(settings.ui_version).toBe('2.0');
      expect(settings.created_at).toBeDefined();
    });
    
    test('後方互換性の確保', () => {
      mockPomodoro.settings = { auto_start: true };
      const settings = mockPomodoro.buildStandardSettings();
      
      expect(settings.auto_start_break).toBe(true);
      expect(settings.auto_start_focus).toBe(true);
    });
  });
  
  describe('タイムアウトとリトライ', () => {
    test('API通信タイムアウトの処理', async () => {
      const timeoutError = new Error('timeout');
      timeoutError.code = 'ECONNABORTED';
      mockAxios.post.mockRejectedValue(timeoutError);
      
      const session = { id: 1, session_type: 'focus' };
      await mockApp.completeCurrentSessionSafely(session, 25, false, '');
      
      // タイムアウトエラーでも例外は発生しない（graceful degradation）
      expect(mockApp.showError).toHaveBeenCalledWith(
        expect.stringContaining('セッション記録に失敗しましたが、タイマーは継続します')
      );
    });
  });
});
```

### 2. UIテスト（新規）
```javascript
// tests/Unit/Frontend/PomodoroUITest.js
describe('ポモドーロUI統合テスト', () => {
  test('カウントダウン表示の動作', async () => {
    const wrapper = mount(PomodoroTimer);
    
    // 自動開始待機状態をシミュレート
    wrapper.vm.$parent.autoStartState.isPending = true;
    await wrapper.vm.$nextTick();
    
    // カウントダウンが開始される
    expect(wrapper.vm.countdownInterval).toBeDefined();
    expect(wrapper.vm.autoStartCountdown).toBeGreaterThan(0);
    
    // 待機状態解除
    wrapper.vm.$parent.autoStartState.isPending = false;
    await wrapper.vm.$nextTick();
    
    // カウントダウンが停止される
    expect(wrapper.vm.countdownInterval).toBe(null);
    expect(wrapper.vm.autoStartCountdown).toBe(0);
  });
  
  test('個別制御設定UIの動作', async () => {
    const wrapper = mount(PomodoroTimer);
    
    // 基本設定を有効化
    wrapper.vm.settings.auto_start = true;
    await wrapper.vm.$nextTick();
    
    // 詳細設定が表示される
    expect(wrapper.find('.ml-4').exists()).toBe(true);
    
    // 個別設定の変更
    wrapper.vm.settings.auto_start_break = false;
    const settings = wrapper.vm.buildStandardSettings();
    
    expect(settings.auto_start_break).toBe(false);
    expect(settings.auto_start_focus).toBe(true);
  });
});
```

## 実装スケジュール（最終版）

### Day 1: Priority 1 実装
- [ ] `POMODORO_CONSTANTS` の定義拡張
- [ ] App.vue のポモドーロカウンター管理実装
- [ ] 統一完了処理 `handleManualTimerComplete()` 実装
- [ ] 次セッションタイプ決定ロジック `determineNextSessionType()` 実装
- [ ] ユニットテスト実装（Priority 1 対象）

### Day 2: Priority 2 実装
- [ ] 自動開始スケジューリング `scheduleAutoStartIfEnabled()` 実装
- [ ] キャンセル機能 `cancelAutoStart()` 実装
- [ ] PomodoroTimer.vue の修正（completeSession, stopSession）
- [ ] 個別制御設定 `buildStandardSettings()` 改良
- [ ] 統合テスト実装

### Day 3: UI改善とテスト
- [ ] カウントダウン表示実装
- [ ] ポモドーロサイクル表示実装
- [ ] エラーハンドリング強化
- [ ] 全365個の既存テスト確認
- [ ] 新機能テスト（5-7個）の実装

### Day 4: 最終確認とデプロイ
- [ ] UAT（ユーザー受け入れテスト）
- [ ] パフォーマンステスト
- [ ] セキュリティ検証
- [ ] バグ修正と最終調整

## 最終確認チェックリスト

### 技術的確認事項
- [x] `POMODORO_CONSTANTS` の定義確認 ✅
- [ ] 既存のグローバル状態管理との整合性確認
- [ ] WebSocket接続がある場合の影響確認
- [ ] モバイル環境での通知動作確認
- [ ] ブラウザのバックグラウンド処理制限の影響確認

### 機能確認事項
- [ ] 4回ポモドーロ後のlong_break自動遷移
- [ ] 個別制御設定（集中→休憩、休憩→集中）
- [ ] 自動開始のキャンセル機能
- [ ] APIエラー時のgraceful degradation
- [ ] セッション中断時のカウンター処理

### UI/UX確認事項
- [ ] カウントダウン表示の視認性
- [ ] ポモドーロサイクル進捗表示
- [ ] 通知権限リクエストのタイミング
- [ ] エラーメッセージの適切性
- [ ] レスポンシブデザインの維持

## セキュリティ考慮

### 1. API セキュリティ
- 既存のBearer token認証を維持
- エラーハンドリングでセンシティブ情報の漏洩防止
- CSRF対策（既存のLaravel Sanctum設定に依存）
- API通信タイムアウト設定でDoS攻撃防止

### 2. クライアントサイドセキュリティ
- HTMLタグのサニタイゼーション
- localStorage アクセスの安全化
- 入力値検証の強化
- セッションデータの検証

### 3. エラーハンドリング
- 機密情報を含まないエラーメッセージ
- Graceful degradation による可用性確保
- 適切なログ出力（個人情報除外）

## リスク軽減策

### 1. 段階的実装
- Priority 1のみ実装 → 全テスト確認 → Priority 2実装
- 各Priority段階で既存機能の動作確認

### 2. ロールバック計画
- 各ファイルの変更前バックアップ
- 機能単位での細分化コミット
- featureブランチでの開発継続

### 3. 影響範囲の限定
- App.vueとPomodoroTimer.vueのみ変更
- API変更なし
- 他のコンポーネントへの影響なし
- provide/injectインターフェースの後方互換性保持

## 期待される効果

### 1. 堅牢性向上
- レースコンディション完全解決
- Graceful degradation によるエラー耐性
- セッションサイクル管理の自動化

### 2. ユーザビリティ改善
- 個別制御による柔軟な自動開始設定
- 自動開始キャンセル機能
- 直感的なUI表示

### 3. 保守性向上
- 責任分離による明確な設計
- 統一されたログフォーマット
- 包括的なテストカバレッジ

### 4. 拡張性確保
- モジュラー設計による機能追加容易性
- 標準化された設定オブジェクト
- セッション管理基盤の整備

この最終版設計書により、レビューで指摘されたすべての改善点が反映され、堅牢で使いやすいポモドーロタイマー自動開始機能が実現できる。実装時の確認事項もすべて網羅されており、安心して開発を進めることができる。