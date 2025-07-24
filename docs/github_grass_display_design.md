# 学習時間 GitHub草表示機能 設計書

## 1. 概要

学習記録アプリに、GitHubのコントリビューション表示のような学習時間の視覚化機能を追加する。学習開始/終了とポモドーロタイマーの2種類の計測時間を合算し、過去1年間の学習活動をヒートマップで表示する。

## 2. システム構成

```
【フロントエンド】
Vue.js 3 + TailwindCSS
├── StudyGrassChart.vue (メイン)
├── GrassCalendar.vue (カレンダー)  
├── GrassTooltip.vue (ツールチップ)
└── useStudyActivity.js (API通信)

【バックエンド】
Laravel 12 + SQLite
├── StudyActivityController (新規)
├── StudyActivityService (拡張)
└── DailyStudySummary (モデル拡張)

【データベース】
├── study_sessions (既存)
├── pomodoro_sessions (既存)  
└── daily_study_summaries (拡張)
```

## 3. データフロー

```
学習完了 → StudyActivityService → DailyStudySummary更新
                ↓
API: /api/study-activity/grass-data
                ↓
Vue.js: StudyGrassChart表示
```

## 4. 機能要件

### 4.1 GitHub草表示機能の要件定義

#### 機能概要
- 学習時間をGitHubのコントリビューション表示みたいにヒートマップで視覚化する
- 学習開始とポモドーロタイマーの2つの計測時間を合算して表示
- 過去1年間の学習活動を一目で把握できるようにする

#### 詳細要件

**データ合算ルール**
- StudySessionの`duration_minutes`（学習開始/終了計測）
- PomodoroSessionの`actual_duration`（完了したフォーカスセッションのみ）
- 上記2つを日別に合算して草表示の濃さを決定

**表示仕様**
- 過去365日間の学習データを表示
- 日曜日〜土曜日の週単位で配置（GitHubと同じレイアウト）
- 学習時間に応じて4段階の色の濃さ
  - 0分: 薄いグレー
  - 1-60分: 薄い緑
  - 61-120分: 中間の緑
  - 121分以上: 濃い緑

**インタラクション**
- 日付をホバーすると詳細ツールチップ表示
- クリックでその日の学習詳細画面へ遷移
- 月別の学習時間統計も表示

## 5. データベース設計

### 5.1 現状分析
既存の`DailyStudySummary`テーブルはあるけど、StudySessionとPomodoroSession両方の時間を合算する仕組みが必要。

### 5.2 設計方針
1. **既存テーブル活用**: `daily_study_summaries`テーブルをベースにする
2. **リアルタイム更新**: 学習完了時に自動で日別サマリーを更新
3. **パフォーマンス重視**: 草表示用の高速データ取得

### 5.3 DailyStudySummaryテーブル拡張案

```sql
-- 既存テーブルに新しいカラムを追加
ALTER TABLE daily_study_summaries ADD COLUMN pomodoro_minutes INT DEFAULT 0;
ALTER TABLE daily_study_summaries ADD COLUMN study_session_minutes INT DEFAULT 0;
ALTER TABLE daily_study_summaries ADD COLUMN total_focus_sessions INT DEFAULT 0;
ALTER TABLE daily_study_summaries ADD COLUMN study_streak_days INT DEFAULT 0;
```

### 5.4 データ更新ロジック

**StudyActivityService**の拡張:
```php
class StudyActivityService 
{
    // StudySession完了時の更新
    public function updateDailySummaryFromStudySession($studySession)
    {
        $summary = DailyStudySummary::firstOrCreate([
            'user_id' => $studySession->user_id,
            'study_date' => $studySession->started_at->toDateString()
        ]);
        
        $summary->study_session_minutes += $studySession->duration_minutes;
        $summary->total_minutes = $summary->study_session_minutes + $summary->pomodoro_minutes;
        $summary->updateSubjectBreakdown($studySession);
        $summary->save();
    }
    
    // PomodoroSession完了時の更新  
    public function updateDailySummaryFromPomodoro($pomodoroSession)
    {
        if ($pomodoroSession->session_type !== 'focus' || !$pomodoroSession->is_completed) {
            return; // フォーカスセッションで完了したもののみ
        }
        
        $summary = DailyStudySummary::firstOrCreate([
            'user_id' => $pomodoroSession->user_id,
            'study_date' => $pomodoroSession->started_at->toDateString()
        ]);
        
        $summary->pomodoro_minutes += $pomodoroSession->actual_duration;
        $summary->total_minutes = $summary->study_session_minutes + $summary->pomodoro_minutes;
        $summary->total_focus_sessions += 1;
        $summary->save();
    }
}
```

### 5.5 インデックス設計
草表示の高速化のために:
```sql
CREATE INDEX idx_daily_summary_user_date_range ON daily_study_summaries(user_id, study_date);
CREATE INDEX idx_daily_summary_total_minutes ON daily_study_summaries(total_minutes);
```

## 6. API設計

### 6.1 新しいコントローラー: StudyActivityController

```php
// routes/api.php
Route::prefix('study-activity')->middleware('auth:sanctum')->group(function () {
    Route::get('/grass-data', [StudyActivityController::class, 'getGrassData']);
    Route::get('/monthly-stats/{year}/{month}', [StudyActivityController::class, 'getMonthlyStats']);
    Route::get('/day-detail/{date}', [StudyActivityController::class, 'getDayDetail']);
    Route::get('/streak-info', [StudyActivityController::class, 'getStreakInfo']);
});

// 既存のダッシュボードAPIに追加
Route::get('/dashboard/grass-summary', [DashboardController::class, 'getGrassSummary']);
```

### 6.2 APIエンドポイント詳細

**1. 草表示データ取得**
```php
// GET /api/study-activity/grass-data?year=2025
{
    "success": true,
    "data": {
        "year": 2025,
        "days": [
            {
                "date": "2025-01-01",
                "total_minutes": 85,
                "level": 2,  // 0-3の4段階
                "study_session_minutes": 45,
                "pomodoro_minutes": 40,
                "session_count": 3
            },
            // ... 365日分
        ],
        "totals": {
            "total_study_minutes": 15420,
            "active_days": 186,
            "longest_streak": 12,
            "current_streak": 5
        }
    }
}
```

**2. 月別統計**
```php
// GET /api/study-activity/monthly-stats/2025/7
{
    "success": true,
    "data": {
        "year": 2025,
        "month": 7,
        "total_minutes": 1240,
        "active_days": 15,
        "average_per_day": 82.7,
        "best_day": {
            "date": "2025-07-15",
            "minutes": 180
        },
        "subject_breakdown": {
            "AWS": 450,
            "Laravel": 380,
            "Vue.js": 410
        }
    }
}
```

**3. 日別詳細**
```php
// GET /api/study-activity/day-detail/2025-07-23
{
    "success": true,
    "data": {
        "date": "2025-07-23",
        "total_minutes": 125,
        "sessions": [
            {
                "type": "study_session",
                "duration_minutes": 65,
                "subject": "AWS",
                "started_at": "2025-07-23 09:00:00",
                "ended_at": "2025-07-23 10:05:00",
                "comment": "EC2の基礎学習"
            },
            {
                "type": "pomodoro",
                "duration_minutes": 25,
                "subject": "Laravel",
                "started_at": "2025-07-23 14:00:00",
                "completed_at": "2025-07-23 14:25:00",
                "was_interrupted": false
            }
        ]
    }
}
```

### 6.3 コントローラー実装例

```php
class StudyActivityController extends Controller
{
    public function getGrassData(Request $request)
    {
        $year = $request->get('year', now()->year);
        $userId = auth()->id();
        
        $startDate = Carbon::create($year, 1, 1);
        $endDate = Carbon::create($year, 12, 31);
        
        $summaries = DailyStudySummary::byUser($userId)
            ->dateRange($startDate, $endDate)
            ->get()
            ->keyBy('study_date');
            
        $grassData = $this->buildGrassDataArray($startDate, $endDate, $summaries);
        
        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'days' => $grassData['days'],
                'totals' => $grassData['totals']
            ]
        ]);
    }
    
    private function calculateStudyLevel($minutes): int
    {
        if ($minutes == 0) return 0;
        if ($minutes <= 60) return 1;
        if ($minutes <= 120) return 2;
        return 3;
    }
}
```

## 7. フロントエンド設計

### 7.1 コンポーネント構成

```
StudyGrassChart.vue          // メインコンポーネント
├── GrassCalendar.vue        // カレンダーグリッド
├── GrassTooltip.vue         // ホバー時のツールチップ
├── GrassStats.vue           // 統計情報表示
└── MonthNavigator.vue       // 月選択ナビゲーション
```

### 7.2 StudyGrassChart.vue（メインコンポーネント）

```vue
<template>
  <div class="study-grass-chart bg-white rounded-lg shadow-sm p-6">
    <!-- ヘッダー -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-xl font-bold text-gray-800">学習アクティビティ</h2>
      <MonthNavigator 
        :current-year="selectedYear" 
        @year-changed="handleYearChange"
      />
    </div>

    <!-- 統計サマリー -->
    <GrassStats :stats="yearlyStats" class="mb-6" />

    <!-- 草カレンダー -->
    <GrassCalendar 
      :grass-data="grassData"
      :loading="loading"
      @day-click="handleDayClick"
      @day-hover="handleDayHover"
    />

    <!-- ツールチップ -->
    <GrassTooltip 
      v-if="hoveredDay"
      :day-data="hoveredDay"
      :position="tooltipPosition"
    />

    <!-- 凡例 -->
    <div class="flex items-center justify-center mt-4 text-sm text-gray-600">
      <span class="mr-2">少ない</span>
      <div class="flex gap-1 mr-2">
        <div class="w-3 h-3 bg-gray-200 rounded-sm"></div>
        <div class="w-3 h-3 bg-green-200 rounded-sm"></div>
        <div class="w-3 h-3 bg-green-400 rounded-sm"></div>
        <div class="w-3 h-3 bg-green-600 rounded-sm"></div>
      </div>
      <span>多い</span>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useStudyActivity } from '@/composables/useStudyActivity'

export default {
  name: 'StudyGrassChart',
  setup() {
    const selectedYear = ref(new Date().getFullYear())
    const hoveredDay = ref(null)
    const tooltipPosition = ref({ x: 0, y: 0 })
    
    const { grassData, yearlyStats, loading, fetchGrassData } = useStudyActivity()
    
    const handleYearChange = (year) => {
      selectedYear.value = year
      fetchGrassData(year)
    }
    
    const handleDayClick = (dayData) => {
      if (dayData.total_minutes > 0) {
        // 詳細画面へ遷移
        router.push(`/study-detail/${dayData.date}`)
      }
    }
    
    const handleDayHover = (dayData, event) => {
      hoveredDay.value = dayData
      tooltipPosition.value = {
        x: event.clientX,
        y: event.clientY
      }
    }
    
    onMounted(() => {
      fetchGrassData(selectedYear.value)
    })
    
    return {
      selectedYear,
      grassData,
      yearlyStats,
      loading,
      hoveredDay,
      tooltipPosition,
      handleYearChange,
      handleDayClick,
      handleDayHover
    }
  }
}
</script>
```

### 7.3 GrassCalendar.vue（カレンダーグリッド）

```vue
<template>
  <div class="grass-calendar">
    <!-- 曜日ラベル -->
    <div class="weekday-labels grid grid-cols-1 gap-1 text-xs text-gray-500 mb-2">
      <div v-for="day in ['日', '月', '火', '水', '木', '金', '土']" 
           :key="day" 
           class="h-3 flex items-center justify-center">
        {{ day }}
      </div>
    </div>

    <!-- カレンダーグリッド -->
    <div class="calendar-grid">
      <div v-for="week in calendarWeeks" :key="week.weekNumber" class="week-row flex gap-1 mb-1">
        <div
          v-for="day in week.days"
          :key="day.date"
          :class="getDayClass(day)"
          class="day-cell w-3 h-3 rounded-sm cursor-pointer transition-all duration-200 hover:ring-2 hover:ring-blue-400"
          @click="$emit('day-click', day)"
          @mouseenter="handleMouseEnter($event, day)"
          @mouseleave="$emit('day-hover', null)"
        >
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'GrassCalendar',
  props: {
    grassData: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  emits: ['day-click', 'day-hover'],
  setup(props, { emit }) {
    const calendarWeeks = computed(() => {
      // 365日のデータを週単位でグループ化
      return buildCalendarWeeks(props.grassData)
    })
    
    const getDayClass = (day) => {
      const baseClass = 'day-cell'
      
      if (props.loading) {
        return `${baseClass} bg-gray-100 animate-pulse`
      }
      
      switch (day.level) {
        case 0: return `${baseClass} bg-gray-200`
        case 1: return `${baseClass} bg-green-200`
        case 2: return `${baseClass} bg-green-400`  
        case 3: return `${baseClass} bg-green-600`
        default: return `${baseClass} bg-gray-200`
      }
    }
    
    const handleMouseEnter = (event, day) => {
      emit('day-hover', day, event)
    }
    
    return {
      calendarWeeks,
      getDayClass,
      handleMouseEnter
    }
  }
}
</script>
```

### 7.4 useStudyActivity.js（Composable）

```javascript
// composables/useStudyActivity.js
import { ref, reactive } from 'vue'
import axios from 'axios'

export function useStudyActivity() {
  const grassData = ref([])
  const yearlyStats = reactive({
    total_study_minutes: 0,
    active_days: 0,
    longest_streak: 0,
    current_streak: 0
  })
  const loading = ref(false)
  
  const fetchGrassData = async (year) => {
    loading.value = true
    try {
      const response = await axios.get(`/api/study-activity/grass-data?year=${year}`)
      grassData.value = response.data.data.days
      Object.assign(yearlyStats, response.data.data.totals)
    } catch (error) {
      console.error('草データの取得に失敗:', error)
    } finally {
      loading.value = false
    }
  }
  
  return {
    grassData,
    yearlyStats,
    loading,
    fetchGrassData
  }
}
```

### 7.5 TailwindCSS設定

```javascript
// tailwind.config.js に追加
module.exports = {
  theme: {
    extend: {
      colors: {
        'grass': {
          0: '#ebedf0',    // レベル0: グレー
          1: '#9be9a8',    // レベル1: 薄い緑  
          2: '#40c463',    // レベル2: 中間の緑
          3: '#30a14e',    // レベル3: 濃い緑
        }
      }
    }
  }
}
```

## 8. 実装手順

### Phase 1: データベース拡張
1. DailyStudySummaryテーブルにカラム追加
2. StudyActivityServiceの拡張
3. データ更新ロジックの実装

### Phase 2: API実装  
1. StudyActivityControllerの作成
2. 草表示用エンドポイントの実装
3. レスポンス形式の統一

### Phase 3: フロントエンド実装
1. Vue.jsコンポーネントの作成
2. TailwindCSSでのスタイリング
3. インタラクション機能の実装

## 9. データ仕様

**学習時間合算ルール:**
- StudySession.duration_minutes（学習開始/終了）
- PomodoroSession.actual_duration（完了フォーカスセッションのみ）
- 上記を日別に合算してtotal_minutesに格納

**草表示レベル分け:**
- レベル0: 0分（薄いグレー）
- レベル1: 1-60分（薄い緑）
- レベル2: 61-120分（中間の緑）
- レベル3: 121分以上（濃い緑）

## 10. パフォーマンス考慮

- DailyStudySummaryテーブルのインデックス最適化
- 365日分のデータを効率的に取得するクエリ
- フロントエンドでの適切なキャッシング

## 11. 拡張性

- 科目別の草表示
- 週単位/月単位の表示切り替え
- 学習ストリーク機能
- 目標達成率の可視化

## 12. セキュリティ

- 認証済みユーザーのみアクセス可能
- ユーザーIDでのデータフィルタリング
- SQLインジェクション対策

## 13. 技術的制約

- Laravel 12 + Vue.js 3 + TailwindCSS 4.0
- SQLiteデータベース（開発環境）
- 既存のDailyStudySummaryテーブル構造を活用
- パフォーマンスを重視した設計

## 14. 今後の課題

- リアルタイム更新機能（WebSocket等）
- モバイル対応の最適化
- データエクスポート機能
- 学習目標との連携強化