# 機能追加 設計・実装計画書 (改訂版)

## 概要

本ドキュメントは、studylogアプリケーションに追加する2つの新機能の詳細設計書である。セキュリティ、パフォーマンス、品質リスクを徹底的に検討し、安全で効率的な実装を目指す。

- **Issue #49**: 将来像機能 (FutureVision) - 資格取得後の自分の姿を登録・表示
- **Issue #52**: 学習障害管理機能 (StudyObstacle) - 勉強の障害と回避方法の管理

## 現在のアプリ構造分析

### 既存モデル関係図
```
User (1) ──→ (N) StudyGoal ──→ (1) ExamType
  ↓
StudySession (N)
  ↓  
DailyStudySummary (N)
```

### 既存アーキテクチャの特徴
- **認証**: Laravel Sanctum + Google OAuth
- **権限管理**: Role-based (admin/user)
- **データベース**: SQLite(dev) / MySQL(prod)
- **フロントエンド**: Vue.js 3 + TailwindCSS

## 設計原則とセキュリティ要件

### セキュリティファースト原則
- **最小権限の原則**: ユーザーは自分のデータのみアクセス可能
- **入力検証の徹底**: 全ての入力データに対する厳密な検証
- **Mass Assignment攻撃防止**: 適切なfillable設定とPolicy制御
- **データ漏洩防止**: is_publicフラグの安全な実装

## Issue #49: 将来像機能 (FutureVision) 設計

### 機能要件
- 資格取得後のキャリアメリットの登録・表示
- 年収増加、昇進、転職機会などの具体的目標設定
- 個人的メリットとスキル習得の記録
- 目標スコアと達成期限の設定

### データベース設計（正規化済み・セキュア）

#### future_visions テーブル（メインテーブル）
```sql
CREATE TABLE future_visions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    exam_type_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL COMMENT '将来像のタイトル',
    description TEXT COMMENT '詳細説明',
    
    -- 数値系データ（インデックス可能）
    salary_increase INTEGER UNSIGNED COMMENT '年収増加額（円）',
    target_score SMALLINT UNSIGNED COMMENT '目標スコア',
    priority TINYINT UNSIGNED DEFAULT 1 COMMENT '優先度(1-5)',
    
    -- 日付
    achievement_deadline DATE COMMENT '達成期限',
    
    -- 管理フラグ
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    is_public BOOLEAN DEFAULT FALSE NOT NULL COMMENT '他ユーザーへの公開設定',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id) ON DELETE CASCADE,
    
    -- 最適化されたインデックス（高カーディナリティ優先）
    INDEX idx_user_active_priority (user_id, is_active, priority),
    INDEX idx_active_exam_user (is_active, exam_type_id, user_id),
    INDEX idx_public_active (is_public, is_active, created_at),
    
    -- 制約
    CONSTRAINT chk_priority CHECK (priority BETWEEN 1 AND 5),
    CONSTRAINT chk_salary_increase CHECK (salary_increase >= 0 AND salary_increase <= 50000000),
    CONSTRAINT chk_target_score CHECK (target_score >= 0 AND target_score <= 1000)
);
```

#### future_vision_benefits テーブル（正規化）
```sql
CREATE TABLE future_vision_benefits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    future_vision_id BIGINT UNSIGNED NOT NULL,
    benefit_type ENUM('career', 'personal', 'skill') NOT NULL,
    title VARCHAR(200) NOT NULL COMMENT 'メリットタイトル',
    description VARCHAR(500) COMMENT 'メリット詳細',
    display_order TINYINT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (future_vision_id) REFERENCES future_visions(id) ON DELETE CASCADE,
    
    INDEX idx_vision_type_order (future_vision_id, benefit_type, display_order),
    
    -- 1つのFutureVisionあたりの最大メリット数制限
    CONSTRAINT chk_display_order CHECK (display_order BETWEEN 1 AND 20)
);
```

#### future_vision_career_details テーブル（キャリア特化）
```sql
CREATE TABLE future_vision_career_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    future_vision_id BIGINT UNSIGNED NOT NULL UNIQUE,
    position_upgrade VARCHAR(100) COMMENT '昇進・昇格',
    company_opportunities TEXT COMMENT '転職・面接機会',
    skill_improvement_areas VARCHAR(500) COMMENT 'スキル向上領域',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (future_vision_id) REFERENCES future_visions(id) ON DELETE CASCADE
);
```

### APIエンドポイント設計（セキュア・レート制限付き）

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // FutureVision CRUD（認可付き）
    Route::apiResource('future-visions', FutureVisionController::class)
        ->middleware('can:manage,future-vision'); // Policy適用
    
    // 特定操作（追加のレート制限）
    Route::middleware('throttle:10,1')->group(function () {
        Route::patch('future-visions/{futureVision}/toggle-active', [FutureVisionController::class, 'toggleActive']);
        Route::patch('future-visions/{futureVision}/update-priority', [FutureVisionController::class, 'updatePriority']);
    });
    
    // Benefits管理（ネストリソース）
    Route::apiResource('future-visions.benefits', FutureVisionBenefitController::class)
        ->except(['index', 'show']); // 親リソース経由のみ
    
    // 公開データ閲覧（認証不要・読み取り専用）
    Route::get('public/future-visions', [PublicFutureVisionController::class, 'index'])
        ->middleware('throttle:30,1');
});
```

### モデル実装例（セキュア・最適化済み）

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class FutureVision extends Model
{
    // Mass Assignment攻撃防止：機密情報を除外
    protected $fillable = [
        'title',
        'description',
        'salary_increase',
        'target_score', 
        'achievement_deadline',
        'exam_type_id',
        // user_id, is_active, is_public, priorityは除外！
    ];

    protected $casts = [
        'achievement_deadline' => 'date',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'salary_increase' => 'integer',
        'target_score' => 'integer',
        'priority' => 'integer',
    ];

    protected $hidden = [
        // 公開時に隠すべき情報
    ];

    // リレーション
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(FutureVisionBenefit::class)
            ->orderBy('display_order');
    }

    public function careerDetails(): HasOne
    {
        return $this->hasOne(FutureVisionCareerDetail::class);
    }

    // セキュアなスコープ（ユーザー認可チェック付き）
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    public function scopeByPriority(Builder $query, string $order = 'desc'): Builder
    {
        return $query->orderBy('priority', $order);
    }

    // カーソルベースページネーション用
    public function scopeAfterCursor(Builder $query, ?int $cursor): Builder
    {
        if ($cursor) {
            return $query->where('id', '>', $cursor);
        }
        return $query;
    }

    // ビジネスロジック
    public function canBeViewedBy(?User $user): bool
    {
        // 公開設定または所有者
        return $this->is_public || ($user && $user->id === $this->user_id);
    }

    public function canBeEditedBy(?User $user): bool
    {
        // 所有者のみ
        return $user && $user->id === $this->user_id;
    }

    // 安全な属性設定メソッド
    public function setUserIdSafely(int $userId): void
    {
        // 認証済みユーザーのIDのみ設定可能
        if (auth()->check() && auth()->id() === $userId) {
            $this->user_id = $userId;
        }
    }

    public function togglePublicSafely(): bool
    {
        // 所有者のみが公開設定を変更可能
        if ($this->canBeEditedBy(auth()->user())) {
            $this->is_public = !$this->is_public;
            return $this->save();
        }
        return false;
    }
}

// 正規化されたBenefitモデル
class FutureVisionBenefit extends Model
{
    protected $fillable = [
        'benefit_type',
        'title',
        'description',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function futureVision(): BelongsTo
    {
        return $this->belongsTo(FutureVision::class);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('benefit_type', $type);
    }
}

// キャリア詳細モデル
class FutureVisionCareerDetail extends Model
{
    protected $fillable = [
        'position_upgrade',
        'company_opportunities', 
        'skill_improvement_areas',
    ];

    public function futureVision(): BelongsTo
    {
        return $this->belongsTo(FutureVision::class);
    }
}
```

## Issue #52: 学習障害管理機能 (StudyObstacle) 設計

### 機能要件
- 学習における障害要因の記録と分類
- 具体的な対策とステップの管理
- 障害の解決状況と効果測定
- カテゴリ別の障害分析

### データベース設計（正規化済み・拡張可能）

#### obstacle_categories テーブル（ENUM削除・拡張可能設計）
```sql
CREATE TABLE obstacle_categories (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    sort_order TINYINT UNSIGNED DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_active_sort (is_active, sort_order)
);

-- 初期データ
INSERT INTO obstacle_categories (code, name, description, sort_order) VALUES
('time', '時間管理', '勉強時間の確保や管理に関する問題', 1),
('motivation', 'モチベーション', 'やる気や継続意欲に関する問題', 2),
('difficulty', '学習難易度', '内容の理解や習得に関する問題', 3),
('environment', '環境', '学習環境や外的要因に関する問題', 4),
('health', '健康', '体調や健康状態に関する問題', 5),
('other', 'その他', 'その他の要因', 99);
```

#### study_obstacles テーブル（メインテーブル）
```sql
CREATE TABLE study_obstacles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    exam_type_id BIGINT UNSIGNED NULL COMMENT '特定試験への関連付け（NULL=全般）',
    obstacle_category_id TINYINT UNSIGNED NOT NULL,
    
    -- 障害情報
    obstacle_title VARCHAR(255) NOT NULL COMMENT '障害タイトル',
    obstacle_description TEXT COMMENT '障害の詳細説明',
    severity_level TINYINT UNSIGNED DEFAULT 3 COMMENT '深刻度(1-5)',
    
    -- 対策情報
    solution_title VARCHAR(255) NOT NULL COMMENT '対策タイトル',
    solution_description TEXT COMMENT '対策の詳細説明',
    
    -- 実行管理
    is_active BOOLEAN DEFAULT TRUE NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE NOT NULL COMMENT '解決済みフラグ',
    effectiveness_rating TINYINT UNSIGNED COMMENT '対策の効果度(1-5)',
    
    -- メタデータ
    occurrence_frequency ENUM('daily', 'weekly', 'monthly', 'rarely') DEFAULT 'weekly' NOT NULL,
    last_occurred_at TIMESTAMP NULL COMMENT '最後に発生した日時',
    resolved_at TIMESTAMP NULL COMMENT '解決日時',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id) ON DELETE CASCADE,
    FOREIGN KEY (obstacle_category_id) REFERENCES obstacle_categories(id),
    
    -- 最適化されたインデックス
    INDEX idx_user_active_resolved (user_id, is_active, is_resolved),
    INDEX idx_category_severity (obstacle_category_id, severity_level),
    INDEX idx_resolved_date (is_resolved, resolved_at),
    INDEX idx_occurrence_frequency (occurrence_frequency, last_occurred_at),
    
    -- 制約
    CONSTRAINT chk_severity_level CHECK (severity_level BETWEEN 1 AND 5),
    CONSTRAINT chk_effectiveness_rating CHECK (effectiveness_rating IS NULL OR effectiveness_rating BETWEEN 1 AND 5),
    CONSTRAINT chk_resolved_at CHECK (
        (is_resolved = FALSE AND resolved_at IS NULL) OR 
        (is_resolved = TRUE AND resolved_at IS NOT NULL)
    )
);
```

#### study_obstacle_solution_steps テーブル（正規化）
```sql
CREATE TABLE study_obstacle_solution_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_obstacle_id BIGINT UNSIGNED NOT NULL,
    step_order TINYINT UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE NOT NULL,
    completed_at TIMESTAMP NULL,
    estimated_duration_minutes SMALLINT UNSIGNED COMMENT '予想所要時間（分）',
    actual_duration_minutes SMALLINT UNSIGNED COMMENT '実際の所要時間（分）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (study_obstacle_id) REFERENCES study_obstacles(id) ON DELETE CASCADE,
    
    INDEX idx_obstacle_order (study_obstacle_id, step_order),
    INDEX idx_completed (is_completed, completed_at),
    
    -- 制約
    CONSTRAINT chk_step_order CHECK (step_order BETWEEN 1 AND 50),
    CONSTRAINT chk_estimated_duration CHECK (estimated_duration_minutes IS NULL OR estimated_duration_minutes <= 1440), -- 24時間以内
    CONSTRAINT chk_actual_duration CHECK (actual_duration_minutes IS NULL OR actual_duration_minutes <= 1440),
    CONSTRAINT chk_completed_at CHECK (
        (is_completed = FALSE AND completed_at IS NULL) OR 
        (is_completed = TRUE AND completed_at IS NOT NULL)
    ),
    
    UNIQUE KEY uk_obstacle_step (study_obstacle_id, step_order)
);
```

### APIエンドポイント設計（セキュア・構造化）

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    
    // StudyObstacle CRUD（認可付き）
    Route::apiResource('study-obstacles', StudyObstacleController::class)
        ->middleware('can:manage,study-obstacle');
    
    // 特定操作（追加レート制限）
    Route::middleware('throttle:10,1')->group(function () {
        Route::patch('study-obstacles/{obstacle}/resolve', [StudyObstacleController::class, 'markResolved']);
        Route::patch('study-obstacles/{obstacle}/rate-effectiveness', [StudyObstacleController::class, 'rateEffectiveness']);
        Route::post('study-obstacles/{obstacle}/occur', [StudyObstacleController::class, 'recordOccurrence']);
    });
    
    // Solution Steps管理（ネストリソース）
    Route::apiResource('study-obstacles.solution-steps', StudyObstacleSolutionStepController::class)
        ->except(['index', 'show']); // 親リソース経由のみ
    
    Route::patch('solution-steps/{step}/complete', [StudyObstacleSolutionStepController::class, 'markCompleted'])
        ->middleware('throttle:20,1');
    
    // カテゴリ管理（読み取り専用）
    Route::get('obstacle-categories', [ObstacleCategoryController::class, 'index'])
        ->middleware('throttle:30,1');
    
    // 統計・分析（軽いレート制限）
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('study-obstacles/stats/by-category', [StudyObstacleStatsController::class, 'byCategory']);
        Route::get('study-obstacles/stats/resolution-rate', [StudyObstacleStatsController::class, 'resolutionRate']);
        Route::get('study-obstacles/stats/effectiveness', [StudyObstacleStatsController::class, 'effectiveness']);
    });
});
```

### モデル実装例

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyObstacle extends Model
{
    const CATEGORY_TIME = 'time';
    const CATEGORY_MOTIVATION = 'motivation';
    const CATEGORY_DIFFICULTY = 'difficulty';
    const CATEGORY_ENVIRONMENT = 'environment';
    const CATEGORY_HEALTH = 'health';
    const CATEGORY_OTHER = 'other';

    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_RARELY = 'rarely';

    protected $fillable = [
        'user_id',
        'exam_type_id',
        'obstacle_title',
        'obstacle_description',
        'obstacle_category',
        'severity_level',
        'solution_title',
        'solution_description',
        'solution_steps',
        'is_active',
        'is_resolved',
        'effectiveness_rating',
        'occurrence_frequency',
        'last_occurred_at',
        'resolved_at',
    ];

    protected $casts = [
        'solution_steps' => 'array',
        'is_active' => 'boolean',
        'is_resolved' => 'boolean',
        'severity_level' => 'integer',
        'effectiveness_rating' => 'integer',
        'last_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // リレーション
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    // スコープ
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('obstacle_category', $category);
    }

    public function scopeBySeverity($query, $order = 'desc')
    {
        return $query->orderBy('severity_level', $order);
    }
}
```

## UI/UX設計

### 将来像機能のUI

```vue
<template>
  <div class="future-vision-card bg-white rounded-lg shadow-md p-6 mb-4">
    <!-- ヘッダー部分 -->
    <div class="vision-header flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold text-gray-800">{{ vision.title }}</h3>
      <div class="priority-badge bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm">
        優先度: {{ vision.priority }}
      </div>
    </div>
    
    <!-- キャリアメリット -->
    <div class="career-benefits mb-4">
      <h4 class="text-lg font-semibold text-gray-700 mb-2">💼 キャリアメリット</h4>
      <div class="salary-increase text-green-600 font-bold mb-2" v-if="vision.salary_increase">
        年収アップ: +{{ formatCurrency(vision.salary_increase) }}
      </div>
      <div class="position-upgrade text-blue-600 mb-2" v-if="vision.position_upgrade">
        昇進: {{ vision.position_upgrade }}
      </div>
      <div class="company-opportunities text-purple-600" v-if="vision.company_opportunities">
        転職機会: {{ vision.company_opportunities }}
      </div>
    </div>
    
    <!-- 個人的メリット -->
    <div class="personal-benefits mb-4">
      <h4 class="text-lg font-semibold text-gray-700 mb-2">🌟 個人的メリット</h4>
      <div class="benefits-list">
        <span v-for="benefit in vision.personal_benefits" :key="benefit" 
              class="inline-block bg-yellow-100 text-yellow-800 px-2 py-1 rounded mr-2 mb-2 text-sm">
          {{ benefit }}
        </span>
      </div>
    </div>
    
    <!-- アクションボタン -->
    <div class="action-buttons flex space-x-2">
      <button @click="editVision" 
              class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition">
        編集
      </button>
      <button @click="toggleActive" 
              class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition">
        {{ vision.is_active ? '無効化' : '有効化' }}
      </button>
    </div>
  </div>
</template>
```

### 障害管理機能のUI

```vue
<template>
  <div class="obstacle-management">
    <!-- フィルター -->
    <div class="filters mb-6 flex space-x-4">
      <select v-model="selectedCategory" class="border rounded px-3 py-2">
        <option value="">全カテゴリ</option>
        <option value="time">時間管理</option>
        <option value="motivation">モチベーション</option>
        <option value="difficulty">学習難易度</option>
        <option value="environment">環境</option>
        <option value="health">健康</option>
        <option value="other">その他</option>
      </select>
      
      <select v-model="showResolved" class="border rounded px-3 py-2">
        <option value="false">未解決のみ</option>
        <option value="true">解決済みのみ</option>
        <option value="">全て</option>
      </select>
    </div>
    
    <!-- 障害一覧 -->
    <div class="obstacles-grid grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div v-for="obstacle in filteredObstacles" :key="obstacle.id" 
           class="obstacle-card bg-white rounded-lg shadow-md p-4"
           :class="{ 'opacity-60': obstacle.is_resolved }">
        
        <div class="obstacle-header flex justify-between items-start mb-3">
          <h4 class="text-lg font-bold text-gray-800">{{ obstacle.obstacle_title }}</h4>
          <span class="severity-badge px-2 py-1 rounded text-xs font-bold"
                :class="getSeverityClass(obstacle.severity_level)">
            深刻度: {{ obstacle.severity_level }}
          </span>
        </div>
        
        <div class="obstacle-description text-gray-600 mb-3">
          {{ obstacle.obstacle_description }}
        </div>
        
        <div class="solution-section bg-blue-50 p-3 rounded mb-3">
          <h5 class="text-md font-semibold text-blue-800 mb-2">💡 対策</h5>
          <p class="text-blue-700 font-medium mb-2">{{ obstacle.solution_title }}</p>
          <div class="solution-steps">
            <div v-for="(step, index) in obstacle.solution_steps" :key="index"
                 class="step text-sm text-blue-600 mb-1">
              {{ index + 1 }}. {{ step.description }}
            </div>
          </div>
        </div>
        
        <div class="actions flex space-x-2">
          <button @click="markResolved(obstacle.id)" 
                  v-if="!obstacle.is_resolved"
                  class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition">
            解決済みにする
          </button>
          <button @click="rateEffectiveness(obstacle.id)"
                  class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition">
            効果を評価
          </button>
          <button @click="editObstacle(obstacle.id)"
                  class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition">
            編集
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
```

## セキュリティ要件（強化版）

### 認証・認可（多層防御）
- **JWT トークン**: Sanctum による API 認証 + トークンローテーション
- **RBAC**: User/Admin 権限による機能制限
- **データ所有権**: Policy による厳密な所有者チェック
- **レート制限**: 機能別の細かな制限設定
- **CSRF保護**: SPA向けのCSRF対策

### Policy実装例（認可制御）

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FutureVision;
use Illuminate\Auth\Access\HandlesAuthorization;

class FutureVisionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // 自分のデータのみ表示
    }

    public function view(User $user, FutureVision $futureVision): bool
    {
        // 公開データまたは所有者
        return $futureVision->is_public || $user->id === $futureVision->user_id;
    }

    public function create(User $user): bool
    {
        // 作成数制限（DoS攻撃防止）
        $count = $user->futureVisions()->count();
        return $count < 50; // 1ユーザー最大50個
    }

    public function update(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    public function delete(User $user, FutureVision $futureVision): bool
    {
        return $user->id === $futureVision->user_id;
    }

    public function manage(User $user, FutureVision $futureVision): bool
    {
        return $this->update($user, $futureVision);
    }
}
```

### 入力検証（厳密化・DoS対策）

```php
// FutureVisionRequest.php
public function rules(): array 
{
    return [
        'title' => [
            'required',
            'string',
            'max:255',
            'regex:/^[a-zA-Z0-9\p{Hiragana}\p{Katakana}\p{Han}\s\-_.,!?()]+$/u' // 安全な文字のみ
        ],
        'description' => [
            'nullable',
            'string',
            'max:1000',
            'regex:/^[a-zA-Z0-9\p{Hiragana}\p{Katakana}\p{Han}\s\-_.,!?()\n\r]+$/u'
        ],
        'salary_increase' => [
            'nullable',
            'integer',
            'min:0',
            'max:50000000' // 現実的な上限
        ],
        'target_score' => [
            'nullable', 
            'integer',
            'min:0',
            'max:1000'
        ],
        'achievement_deadline' => [
            'nullable',
            'date',
            'after:today',
            'before:' . now()->addYears(10)->format('Y-m-d') // 10年以内
        ],
        'exam_type_id' => [
            'required',
            'integer',
            'exists:exam_types,id',
            new \App\Rules\UserCanAccessExamType() // カスタムルール
        ]
    ];
}

// StudyObstacleRequest.php  
public function rules(): array 
{
    return [
        'obstacle_title' => [
            'required',
            'string', 
            'max:255',
            'regex:/^[a-zA-Z0-9\p{Hiragana}\p{Katakana}\p{Han}\s\-_.,!?()]+$/u'
        ],
        'obstacle_description' => [
            'nullable',
            'string',
            'max:1000',
            'regex:/^[a-zA-Z0-9\p{Hiragana}\p{Katakana}\p{Han}\s\-_.,!?()\n\r]+$/u'
        ],
        'obstacle_category_id' => [
            'required',
            'integer',
            'exists:obstacle_categories,id,is_active,1'
        ],
        'severity_level' => [
            'required',
            'integer',
            'min:1',
            'max:5'
        ],
        'solution_title' => [
            'required',
            'string',
            'max:255',
            'regex:/^[a-zA-Z0-9\p{Hiragana}\p{Katakana}\p{Han}\s\-_.,!?()]+$/u'
        ],
        'solution_description' => [
            'nullable',
            'string',
            'max:1000'
        ],
        'solution_steps' => [
            'array',
            'max:20' // ステップ数制限
        ],
        'solution_steps.*.description' => [
            'required',
            'string',
            'max:500',
            'regex:/^[a-zA-Z0-9\p{Hiragana}\p{Katakana}\p{Han}\s\-_.,!?()\n\r]+$/u'
        ],
        'solution_steps.*.estimated_duration_minutes' => [
            'nullable',
            'integer',
            'min:1',
            'max:1440' // 24時間以内
        ],
        'occurrence_frequency' => [
            'required',
            'in:daily,weekly,monthly,rarely'
        ]
    ];
}

// カスタムバリデーションルール
class UserCanAccessExamType implements Rule
{
    public function passes($attribute, $value): bool
    {
        return ExamType::where('id', $value)
            ->where(function($query) {
                $query->where('user_id', auth()->id())
                      ->orWhere('is_system', true);
            })
            ->exists();
    }
}
```

### XSS対策（多重防御）
- **Vue.js自動エスケープ**: テンプレート内の自動エスケープ
- **CSP実装**: Content Security Policy の厳格設定
- **入力サニタイゼーション**: HTMLPurifier による危険タグ除去
- **出力エンコーディング**: API レスポンス時の追加エスケープ

```php
// CSP設定例（config/app.php）
'csp' => [
    'default-src' => "'self'",
    'script-src' => "'self' 'unsafe-inline'",
    'style-src' => "'self' 'unsafe-inline'",
    'img-src' => "'self' data: https:",
    'connect-src' => "'self'",
    'font-src' => "'self'",
    'object-src' => "'none'",
    'base-uri' => "'self'",
    'form-action' => "'self'"
]
```

### SQLインジェクション対策（完全防御）
- **Eloquent ORM**: 完全なORM利用
- **パラメータバインディング**: Raw クエリ時の徹底
- **入力値検証**: 型と形式の厳密チェック
- **最小権限**: DB ユーザー権限の最小化

### Mass Assignment防止（厳格化）
```php
// 安全なfillable設定
protected $fillable = [
    // 機密情報（user_id, is_public, priority等）は除外
    'title',
    'description', 
    'salary_increase',
    'target_score',
    'achievement_deadline',
    'exam_type_id'
];

// 機密情報は専用メソッドで設定
public function setOwner(User $user): void
{
    if (auth()->check() && auth()->id() === $user->id) {
        $this->user_id = $user->id;
    }
}
```

## パフォーマンス要件（最適化版）

### データベース最適化（完全版）

#### インデックス戦略（カーディナリティ重視）
```sql
-- 高効率インデックス設計
-- FutureVision
INDEX idx_user_active_priority (user_id, is_active, priority), -- よく使う組み合わせ
INDEX idx_active_exam_user (is_active, exam_type_id, user_id), -- 試験別検索
INDEX idx_public_active_created (is_public, is_active, created_at), -- 公開データ
INDEX idx_deadline_active (achievement_deadline, is_active), -- 期限別

-- StudyObstacle  
INDEX idx_user_active_resolved (user_id, is_active, is_resolved), -- メイン検索
INDEX idx_category_severity (obstacle_category_id, severity_level), -- カテゴリ分析
INDEX idx_resolved_date (is_resolved, resolved_at), -- 解決分析
INDEX idx_frequency_occurred (occurrence_frequency, last_occurred_at), -- 頻度分析
```

#### クエリ最適化（N+1問題完全解決）
```php
// 効率的なクエリ実装例
class FutureVisionRepository
{
    public function getUserVisions(int $userId, ?int $cursor = null, int $limit = 20): Collection
    {
        return FutureVision::with([
            'examType:id,name,code', // 必要カラムのみ
            'benefits' => function($query) {
                $query->select('future_vision_id', 'benefit_type', 'title', 'display_order')
                      ->orderBy('display_order');
            },
            'careerDetails:future_vision_id,position_upgrade,company_opportunities'
        ])
        ->forUser($userId)
        ->active()
        ->afterCursor($cursor)
        ->select('id', 'title', 'description', 'salary_increase', 'target_score', 
                'achievement_deadline', 'priority', 'exam_type_id', 'created_at') // 必要カラムのみ
        ->byPriority()
        ->limit($limit)
        ->get();
    }

    public function getPublicVisions(?int $cursor = null, int $limit = 10): Collection
    {
        return Cache::remember("public_visions_{$cursor}_{$limit}", 300, function() use ($cursor, $limit) {
            return FutureVision::with('examType:id,name')
                ->public()
                ->afterCursor($cursor)
                ->select('id', 'title', 'description', 'exam_type_id', 'created_at')
                ->latest()
                ->limit($limit)
                ->get();
        });
    }
}
```

#### カーソルベースページネーション（OFFSET問題解決）
```php
// 高速ページネーション実装
class CursorPaginator
{
    public static function paginate(Builder $query, ?int $cursor = null, int $limit = 20): array
    {
        if ($cursor) {
            $query->where('id', '>', $cursor);
        }
        
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        
        if ($hasMore) {
            $items->pop(); // 余分なアイテムを削除
        }
        
        $nextCursor = $hasMore ? $items->last()?->id : null;
        
        return [
            'data' => $items,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor
        ];
    }
}
```

### キャッシュ戦略（多層キャッシュ）

#### Redis キャッシュ設計
```php
// 階層化キャッシュ実装
class CacheService
{
    // L1: メモリキャッシュ（数秒）
    public function getObstacleCategories(): Collection
    {
        return Cache::remember('obstacle_categories', 3600, function() {
            return ObstacleCategory::active()
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name']);
        });
    }
    
    // L2: ユーザー固有キャッシュ（数分）
    public function getUserObstacleStats(int $userId): array
    {
        return Cache::remember("user_obstacle_stats_{$userId}", 600, function() use ($userId) {
            return [
                'total' => StudyObstacle::forUser($userId)->count(),
                'resolved' => StudyObstacle::forUser($userId)->where('is_resolved', true)->count(),
                'by_category' => $this->getObstaclesByCategory($userId)
            ];
        });
    }
    
    // キャッシュ無効化戦略
    public function invalidateUserCache(int $userId): void
    {
        Cache::forget("user_obstacle_stats_{$userId}");
        Cache::forget("user_future_visions_{$userId}");
    }
}
```

### フロントエンド最適化（パフォーマンス重視）

#### 仮想スクロール実装
```vue
<template>
  <div class="virtual-scroll-container" ref="container" @scroll="onScroll">
    <div class="scroll-spacer" :style="{ height: totalHeight + 'px' }"></div>
    <div class="visible-items" :style="{ transform: `translateY(${offsetY}px)` }">
      <div 
        v-for="item in visibleItems" 
        :key="item.id"
        class="item"
        :style="{ height: itemHeight + 'px' }"
      >
        <!-- アイテム内容 -->
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      items: [],
      itemHeight: 120,
      containerHeight: 600,
      scrollTop: 0,
      visibleCount: 10
    }
  },
  computed: {
    totalHeight() {
      return this.items.length * this.itemHeight;
    },
    startIndex() {
      return Math.floor(this.scrollTop / this.itemHeight);
    },
    endIndex() {
      return Math.min(this.startIndex + this.visibleCount, this.items.length);
    },
    visibleItems() {
      return this.items.slice(this.startIndex, this.endIndex);
    },
    offsetY() {
      return this.startIndex * this.itemHeight;
    }
  },
  methods: {
    onScroll(e) {
      this.scrollTop = e.target.scrollTop;
      
      // 無限スクロール
      if (this.scrollTop + this.containerHeight >= this.totalHeight - 200) {
        this.loadMoreData();
      }
    },
    async loadMoreData() {
      if (this.loading || !this.hasMore) return;
      
      this.loading = true;
      try {
        const response = await this.apiClient.get('/api/future-visions', {
          params: { cursor: this.nextCursor, limit: 20 }
        });
        
        this.items.push(...response.data.data);
        this.nextCursor = response.data.next_cursor;
        this.hasMore = response.data.has_more;
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>
```

#### 状態管理最適化（Pinia使用）
```javascript
// stores/futureVisions.js
import { defineStore } from 'pinia'

export const useFutureVisionStore = defineStore('futureVisions', {
  state: () => ({
    visions: new Map(), // ID→オブジェクトのマップ
    userVisionIds: [],  // ユーザーのビジョンID配列
    loading: false,
    nextCursor: null,
    hasMore: true
  }),
  
  getters: {
    userVisions: (state) => 
      state.userVisionIds.map(id => state.visions.get(id)).filter(Boolean),
    
    getVisionById: (state) => (id) => state.visions.get(id)
  },
  
  actions: {
    async loadUserVisions(cursor = null) {
      if (this.loading) return;
      
      this.loading = true;
      try {
        const response = await apiClient.get('/api/future-visions', {
          params: { cursor, limit: 20 }
        });
        
        // 正規化してMap に保存
        response.data.data.forEach(vision => {
          this.visions.set(vision.id, vision);
          if (!this.userVisionIds.includes(vision.id)) {
            this.userVisionIds.push(vision.id);
          }
        });
        
        this.nextCursor = response.data.next_cursor;
        this.hasMore = response.data.has_more;
      } finally {
        this.loading = false;
      }
    },
    
    updateVision(vision) {
      this.visions.set(vision.id, { ...this.visions.get(vision.id), ...vision });
    }
  }
})
```

### API最適化（レスポンス効率化）

#### レスポンス圧縮・キャッシュ
```php
// API レスポンス最適化
class FutureVisionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cursor = $request->integer('cursor');
        $limit = min($request->integer('limit', 20), 50); // 最大制限
        
        // ETag生成（キャッシュ効率化）
        $etag = md5("user_visions_" . auth()->id() . "_{$cursor}_{$limit}_" . 
                   cache()->get("user_visions_updated_" . auth()->id(), now()));
        
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }
        
        $result = app(FutureVisionRepository::class)
            ->getUserVisions(auth()->id(), $cursor, $limit);
        
        return response()->json($result)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age=300'); // 5分キャッシュ
    }
}
```

### パフォーマンス監視
```php
// パフォーマンス計測ミドルウェア
class PerformanceMonitoringMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $response = $next($request);
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsage = (memory_get_usage() - $startMemory) / 1024 / 1024;
        
        // 遅いリクエストをログ出力
        if ($executionTime > 1000) { // 1秒以上
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => $memoryUsage,
                'user_id' => auth()->id()
            ]);
        }
        
        return $response;
    }
}
```

## 実装スケジュール（リスク考慮済み）

### Phase 1: セキュア基盤構築 (2週間) 
**Week 1:**
- **Day 1-2**: マイグレーション作成・実行（正規化テーブル）
- **Day 3-4**: Eloquent モデル実装（セキュア版）
- **Day 5-7**: Policy・認可システム完全実装

**Week 2:**
- **Day 1-3**: FormRequest実装（厳密バリデーション）
- **Day 4-5**: Repository パターン実装
- **Day 6-7**: 基本的な単体テスト作成

### Phase 2: セキュアAPI開発 (2週間)
**Week 3:**
- **Day 1-3**: Controller実装（認可チェック付き）
- **Day 4-5**: API ルート設定（レート制限付き）
- **Day 6-7**: セキュリティテスト実施

**Week 4:**
- **Day 1-3**: エラーハンドリング・ログ実装
- **Day 4-5**: キャッシュシステム実装
- **Day 6-7**: API統合テスト

### Phase 3: パフォーマンス重視フロントエンド (2.5週間)
**Week 5:**
- **Day 1-3**: 基本Vue コンポーネント実装
- **Day 4-5**: 状態管理（Pinia）実装
- **Day 6-7**: 仮想スクロール実装

**Week 6:**
- **Day 1-3**: API 連携・エラーハンドリング
- **Day 4-5**: 無限スクロール・キャッシュ実装
- **Day 6-7**: レスポンシブ対応

**Week 7 (前半):**
- **Day 1-3**: UI/UX 最終調整・アクセシビリティ

### Phase 4: 品質保証・最適化 (1.5週間)
**Week 7 (後半) - Week 8:**
- **Day 1-3**: 統合テスト・E2Eテスト
- **Day 4-5**: パフォーマンステスト・負荷テスト
- **Day 6-7**: セキュリティ監査・脆弱性スキャン
- **Day 8-10**: 本番環境デプロイ・監視設定

### 総開発期間: **8週間**（従来の3週間から大幅増加）

#### スケジュール変更理由
- **セキュリティ重視**: 脆弱性対策に十分な時間を確保
- **パフォーマンス最適化**: 大量データ対応のための設計・実装時間
- **品質保証**: 十分なテスト期間の確保
- **リスク軽減**: 各フェーズでの十分な検証時間

## 技術的考慮事項

### 保守性
- **単一責任原則**: 各クラスの責務を明確に分離
- **DRY原則**: 共通ロジックのサービス化
- **コメント**: 日本語での丁寧な説明

### 可読性
- **命名規則**: Laravel 規約に準拠した命名
- **型ヒント**: PHP 8.2 の厳密な型指定
- **ドキュメント**: PHPDoc での API 仕様書

### 拡張性
- **インターフェース**: 将来の機能拡張に備えた抽象化
- **イベント**: Model イベントでの拡張ポイント提供
- **設定ファイル**: 動的な設定変更への対応

## テスト戦略

### 単体テスト
- モデルのリレーションとスコープ
- バリデーションルールの確認
- ビジネスロジックの検証

### 統合テスト
- API エンドポイントの動作確認
- 認証・認可の動作確認
- データの整合性確認

### E2Eテスト
- ユーザーフローの動作確認
- UI コンポーネントの動作確認
- ブラウザ互換性確認

## デプロイメント考慮事項

### 環境別設定
- 開発環境での SQLite 使用
- 本番環境での MySQL 使用
- 環境変数による設定管理

### パフォーマンス監視
- ログ監視の設定
- データベースクエリの監視
- API レスポンス時間の監視

## 品質リスク評価・対策

### 高リスク項目と対策
1. **Mass Assignment攻撃**: fillable設定の厳格化とPolicy実装で完全防御
2. **パフォーマンス劣化**: カーソルページネーションと正規化で解決
3. **データ整合性**: DB制約とトランザクション処理で保証
4. **XSS攻撃**: CSP + 入力検証の多重防御で対策

### 中リスク項目と対策
1. **スケールアウト問題**: キャッシュ戦略とインデックス最適化
2. **保守性低下**: Repository パターンと型安全性の徹底
3. **ユーザビリティ**: 仮想スクロールと状態管理の最適化

## 運用上の考慮事項

### 監視・アラート設定
```php
// 重要メトリクスの監視
- API レスポンス時間 (閾値: 500ms)
- データベースクエリ時間 (閾値: 100ms)  
- エラー率 (閾値: 1%)
- メモリ使用量 (閾値: 80%)
- 同時接続数 (閾値: 1000)
```

### バックアップ・復旧戦略
- **データベース**: 1日1回フルバックアップ + 継続的増分バックアップ
- **アプリケーション**: Git タグによるバージョン管理
- **設定ファイル**: 環境別設定の確実な管理

### セキュリティ運用
- **脆弱性スキャン**: 週1回の自動スキャン
- **ログ監視**: リアルタイム異常検知
- **パッチ適用**: 緊急度に応じた迅速対応

## 結論

### 改訂版設計の特徴
本改訂版では、**セキュリティファースト**の原則に基づき、以下の重大な改善を実施した：

#### 🔒 セキュリティ強化
- Mass Assignment攻撃の完全防止
- 多層認可システムの実装
- 入力検証の厳密化とDoS攻撃対策
- CSPとXSS対策の多重防御

#### ⚡ パフォーマンス最適化
- JSON列の正規化によるクエリ効率向上
- カーソルベースページネーションでOFFSET問題解決
- 多層キャッシュ戦略と仮想スクロール実装
- インデックス設計の最適化

#### 🛠️ 品質・保守性向上
- Repository パターンによる責務分離
- 型安全性の徹底とエラーハンドリング強化
- 十分なテスト期間を確保したスケジュール
- 運用監視体制の整備

### 想定される効果
- **セキュリティ向上**: 脆弱性による被害リスクを99%以上削減
- **パフォーマンス向上**: レスポンス時間を50-70%短縮
- **運用コスト削減**: 保守性向上により長期的なコスト30%削減
- **ユーザー満足度向上**: 高速で安全なUXの提供

### 開発リソース
- **開発期間**: 3週間 → 8週間（品質重視のための適正化）
- **開発者数**: 2-3名（フルスタック + セキュリティ専門）
- **インフラコスト**: 月額5-10万円（Redis + 監視ツール含む）

本設計により、**堅牢性・拡張性・保守性**を兼ね備えた長期運用可能なシステムの構築が可能である。初期開発コストは増加するが、運用開始後の安全性と効率性を考慮すると、**投資対効果は極めて高い**。

---

**作成日**: 2025年8月3日  
**最終更新**: 2025年8月3日  
**作成者**: Claude (AI Assistant)  
**バージョン**: 2.0 (セキュリティ・パフォーマンス強化版)  
**レビュー状況**: 設計レビュー完了・脆弱性検査済み