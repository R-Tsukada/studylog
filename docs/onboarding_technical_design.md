# 新規ユーザーオンボーディング機能 技術設計書

## 🏗️ 1. システムアーキテクチャ概要

### 1.1 システム構成図
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend       │    │   Database      │
│   Vue.js 3      │◄──►│   Laravel 12    │◄──►│   SQLite/MySQL  │
│                 │    │                 │    │                 │
│ ┌─────────────┐ │    │ ┌─────────────┐ │    │ ┌─────────────┐ │
│ │OnBoarding   │ │    │ │OnBoarding   │ │    │ │users        │ │
│ │Components   │ │    │ │Controller   │ │    │ │onboarding   │ │
│ └─────────────┘ │    │ └─────────────┘ │    │ │_logs        │ │
│ ┌─────────────┐ │    │ ┌─────────────┐ │    │ └─────────────┘ │
│ │Composables  │ │    │ │User Model   │ │    └─────────────────┘  
│ │(useOnboard) │ │    │ │Extensions   │ │          
│ └─────────────┘ │    │ └─────────────┘ │          
│ ┌─────────────┐ │    │ ┌─────────────┐ │          
│ │State Mgmt   │ │    │ │Event        │ │          
│ │(reactive)   │ │    │ │Listeners    │ │          
│ └─────────────┘ │    │ └─────────────┘ │          
└─────────────────┘    └─────────────────┘          
        │                       │
        └─────── Sanctum ────────┘
           （認証・セッション管理）
```

### 1.2 技術スタック
- **フロントエンド**: Vue.js 3 + Composition API + TypeScript
- **スタイリング**: TailwindCSS 4.0
- **状態管理**: Vue 3 Reactivity API
- **バックエンド**: Laravel 12 + PHP 8.2+
- **認証**: Laravel Sanctum
- **データベース**: SQLite (開発) / MySQL (本番)
- **テスト**: Vitest (フロント) + PHPUnit (バック)

## 🎨 2. フロントエンド設計

### 2.1 コンポーネント構成
```
src/
├── components/
│   └── onboarding/
│       ├── OnboardingModal.vue           # メインモーダル
│       ├── OnboardingProgress.vue        # プログレスバー
│       ├── OnboardingStep.vue           # ステップ基底コンポーネント
│       ├── steps/
│       │   ├── WelcomeStep.vue          # Step 1: ウェルカム
│       │   ├── SetupStep.vue            # Step 2: インタラクティブ設定
│       │   ├── FeatureStep.vue          # Step 3: 機能説明
│       │   └── CompletionStep.vue       # Step 4: 完了画面
│       └── dialogs/
│           ├── SkipConfirmDialog.vue    # スキップ確認
│           └── ResumeDialog.vue         # 復帰確認
├── composables/
│   ├── useOnboarding.ts                 # メイン状態管理
│   ├── useOnboardingAPI.ts              # API通信
│   ├── useOnboardingStorage.ts          # ローカルストレージ管理
│   ├── useOnboardingAccessibility.ts   # アクセシビリティ
│   └── useOnboardingAnalytics.ts       # 分析・ログ
├── types/
│   └── onboarding.ts                    # TypeScript型定義
└── utils/
    ├── onboardingConfig.ts              # 設定管理
    └── onboardingValidators.ts          # バリデーション
```

### 2.2 型定義 (TypeScript)
```typescript
// types/onboarding.ts

// メイン状態インターフェース
export interface OnboardingState {
  readonly currentStep: number;
  readonly totalSteps: number;
  readonly completedSteps: ReadonlyArray<number>;
  readonly isVisible: boolean;
  readonly isLoading: boolean;
  readonly startedAt?: Date;
  readonly lastActivity?: Date;
}

// ステップ設定
export interface OnboardingStepConfig {
  id: string;
  title: string;
  component: string;
  required: boolean;
  skipAllowed: boolean;
  timeoutSeconds?: number;
  validation?: string[];
  nextStepConditions?: string[];
}

// 全体設定
export interface OnboardingConfig {
  version: string;
  steps: OnboardingStepConfig[];
  display: {
    showProgressBar: boolean;
    animation: 'slide' | 'fade' | 'scale';
    theme: 'default' | 'dark' | 'auto';
  };
  behavior: {
    autoSave: boolean;
    sessionTimeout: number;
    maxIdleTime: number;
    enableAnalytics: boolean;
  };
  accessibility: {
    enableKeyboardNavigation: boolean;
    enableScreenReader: boolean;
    enableFocusTrap: boolean;
  };
}

// API関連
export interface OnboardingApiResponse<T = unknown> {
  success: boolean;
  message: string;
  data?: T;
  errors?: ValidationErrors;
  meta: {
    timestamp: string;
    requestId: string;
    version: string;
  };
}

export interface OnboardingProgressData {
  currentStep: number;
  completedSteps: number[];
  stepData?: Record<string, unknown>;
  timeSpent?: number;
}

// イベント関連
export interface OnboardingEvent {
  type: 'started' | 'step_completed' | 'skipped' | 'completed' | 'error';
  step?: number;
  data?: Record<string, unknown>;
  timestamp: Date;
}

// ユーザー行動分析
export interface OnboardingAnalytics {
  userId: string;
  sessionId: string;
  events: OnboardingEvent[];
  startTime: Date;
  endTime?: Date;
  completionRate: number;
  deviceInfo: {
    userAgent: string;
    screenSize: string;
    isMobile: boolean;
  };
}
```

### 2.3 メインComposable実装
```typescript
// composables/useOnboarding.ts
import { ref, reactive, computed, watch } from 'vue';
import { useOnboardingAPI } from './useOnboardingAPI';
import { useOnboardingStorage } from './useOnboardingStorage';
import { useOnboardingAnalytics } from './useOnboardingAnalytics';

export function useOnboarding() {
  // リアクティブな状態
  const state = reactive<OnboardingState>({
    currentStep: 1,
    totalSteps: 4,
    completedSteps: [],
    isVisible: false,
    isLoading: false,
    startedAt: undefined,
    lastActivity: undefined
  });

  // API・ストレージ・分析用Composables
  const api = useOnboardingAPI();
  const storage = useOnboardingStorage();
  const analytics = useOnboardingAnalytics();

  // 計算プロパティ
  const progress = computed(() => 
    Math.round((state.completedSteps.length / state.totalSteps) * 100)
  );

  const canProceed = computed(() => {
    // ステップ固有の進行条件チェック
    return validateCurrentStep(state.currentStep);
  });

  const canGoBack = computed(() => state.currentStep > 1);

  const isLastStep = computed(() => state.currentStep === state.totalSteps);

  // メソッド定義
  const showOnboarding = async (): Promise<void> => {
    try {
      state.isLoading = true;
      
      // サーバーから状態を取得
      const response = await api.getStatus();
      if (!response.success) {
        throw new Error(response.message);
      }

      // 表示判定
      if (response.data.shouldShow) {
        state.isVisible = true;
        state.startedAt = new Date();
        
        // 分析開始
        analytics.trackEvent('started', undefined, {
          userAgent: navigator.userAgent,
          referrer: document.referrer
        });

        // 既存の進捗があれば復元
        if (response.data.progress) {
          await restoreProgress(response.data.progress);
        }
      }
    } catch (error) {
      console.error('オンボーディング表示エラー:', error);
      // フォールバック: ローカルストレージから復元
      await restoreFromStorage();
    } finally {
      state.isLoading = false;
    }
  };

  const nextStep = async (): Promise<boolean> => {
    if (!canProceed.value) return false;

    try {
      // 現在のステップを完了済みに追加
      if (!state.completedSteps.includes(state.currentStep)) {
        state.completedSteps.push(state.currentStep);
      }

      // 最後のステップの場合は完了処理
      if (isLastStep.value) {
        await completeOnboarding();
        return true;
      }

      // 次のステップに進行
      state.currentStep++;
      state.lastActivity = new Date();

      // サーバーに進捗を同期
      await syncProgress();

      // 分析イベント記録
      analytics.trackEvent('step_completed', state.currentStep - 1);

      return true;
    } catch (error) {
      console.error('ステップ進行エラー:', error);
      return false;
    }
  };

  const prevStep = async (): Promise<boolean> => {
    if (!canGoBack.value) return false;

    try {
      state.currentStep--;
      state.lastActivity = new Date();
      
      await syncProgress();
      return true;
    } catch (error) {
      console.error('ステップ後退エラー:', error);
      return false;
    }
  };

  const skipOnboarding = async (reason?: string): Promise<void> => {
    try {
      state.isLoading = true;

      // サーバーにスキップを記録
      await api.skip({
        currentStep: state.currentStep,
        reason: reason || 'user_choice',
        completedSteps: state.completedSteps
      });

      // 分析イベント記録
      analytics.trackEvent('skipped', state.currentStep, { reason });

      // 状態リセット
      resetState();
      
    } catch (error) {
      console.error('スキップ処理エラー:', error);
    } finally {
      state.isLoading = false;
    }
  };

  const completeOnboarding = async (): Promise<void> => {
    try {
      state.isLoading = true;

      // 全ステップ完了済みにマーク
      state.completedSteps = [1, 2, 3, 4];

      // サーバーに完了を記録
      await api.complete({
        completedSteps: state.completedSteps,
        totalTimeSpent: calculateTotalTime(),
        stepTimes: calculateStepTimes()
      });

      // 分析イベント記録
      analytics.trackEvent('completed', undefined, {
        totalTime: calculateTotalTime(),
        completionRate: 100
      });

      // 状態リセット
      resetState();

    } catch (error) {
      console.error('完了処理エラー:', error);
    } finally {
      state.isLoading = false;
    }
  };

  // ヘルパーメソッド
  const validateCurrentStep = (step: number): boolean => {
    // ステップ固有のバリデーションロジック
    switch (step) {
      case 1: return true; // ウェルカムは常にOK
      case 2: return validateSetupStep(); // 設定項目チェック
      case 3: return true; // 説明は常にOK
      case 4: return true; // 完了は常にOK
      default: return false;
    }
  };

  const validateSetupStep = (): boolean => {
    // Step 2の必須項目をチェック
    const setupData = storage.getStepData(2);
    return !!(setupData?.examType && setupData?.subjectAreas?.length > 0);
  };

  const syncProgress = async (): Promise<void> => {
    try {
      await api.updateProgress({
        currentStep: state.currentStep,
        completedSteps: state.completedSteps,
        stepData: storage.getAllStepData(),
        timestamp: new Date().toISOString()
      });

      // ローカルストレージにも保存
      storage.saveState(state);
    } catch (error) {
      console.error('進捗同期エラー:', error);
      // ローカルストレージのみ保存
      storage.saveState(state);
    }
  };

  const restoreProgress = async (progressData: OnboardingProgressData): Promise<void> => {
    state.currentStep = progressData.currentStep;
    state.completedSteps = [...progressData.completedSteps];
    
    if (progressData.stepData) {
      storage.restoreStepData(progressData.stepData);
    }
  };

  const restoreFromStorage = async (): Promise<void> => {
    const savedState = storage.getState();
    if (savedState && storage.isValidSession()) {
      Object.assign(state, savedState);
      state.isVisible = true;
    }
  };

  const resetState = (): void => {
    state.currentStep = 1;
    state.completedSteps = [];
    state.isVisible = false;
    state.startedAt = undefined;
    state.lastActivity = undefined;
    
    storage.clearState();
  };

  const calculateTotalTime = (): number => {
    if (!state.startedAt) return 0;
    return Math.floor((Date.now() - state.startedAt.getTime()) / 1000);
  };

  const calculateStepTimes = (): Record<number, number> => {
    return analytics.getStepTimes();
  };

  // ライフサイクル管理
  watch(() => state.currentStep, (newStep) => {
    analytics.recordStepChange(newStep);
  });

  // 定期的な状態保存
  let autoSaveTimer: NodeJS.Timeout;
  const startAutoSave = () => {
    autoSaveTimer = setInterval(() => {
      if (state.isVisible) {
        storage.saveState(state);
      }
    }, 10000); // 10秒毎
  };

  const stopAutoSave = () => {
    if (autoSaveTimer) {
      clearInterval(autoSaveTimer);
    }
  };

  // 初期化時に自動保存開始
  startAutoSave();

  // 返却オブジェクト
  return {
    // 状態
    state: readonly(state),
    
    // 計算プロパティ
    progress,
    canProceed,
    canGoBack,
    isLastStep,
    
    // メソッド
    showOnboarding,
    nextStep,
    prevStep,
    skipOnboarding,
    completeOnboarding,
    
    // ユーティリティ
    resetState,
    syncProgress,
    
    // ライフサイクル
    startAutoSave,
    stopAutoSave
  };
}
```

### 2.4 メインモーダルコンポーネント
```vue
<!-- components/onboarding/OnboardingModal.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal" appear>
      <div 
        v-if="state.isVisible" 
        class="fixed inset-0 z-50 flex items-center justify-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="onboarding-title"
        aria-describedby="onboarding-content"
        @keydown="handleKeyDown"
      >
        <!-- 背景オーバーレイ -->
        <div 
          class="absolute inset-0 bg-black bg-opacity-50 transition-opacity"
          @click="handleBackdropClick"
          aria-hidden="true"
        ></div>
        
        <!-- モーダル本体 -->
        <div 
          ref="modalRef"
          class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden
                 transform transition-all duration-300 ease-out"
          :class="{ 'scale-95 opacity-0': state.isLoading }"
        >
          <!-- ヘッダー -->
          <header class="flex justify-between items-center p-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
              <span class="text-2xl" aria-hidden="true">📚</span>
              <div>
                <h1 id="onboarding-title" class="text-lg font-semibold text-gray-900">
                  すたログ - 初回設定ガイド
                </h1>
                <div 
                  id="step-indicator" 
                  class="text-sm text-gray-500"
                  aria-live="polite"
                >
                  ステップ {{ state.currentStep }}/{{ state.totalSteps }}
                </div>
              </div>
            </div>
            <button 
              @click="showSkipConfirm"
              class="text-gray-500 hover:text-gray-700 p-1 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-label="オンボーディングを閉じる"
              :disabled="state.isLoading"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </header>
          
          <!-- プログレスバー -->
          <OnboardingProgress 
            :current="progress" 
            :total="100"
            class="border-b border-gray-200"
          />
          
          <!-- メインコンテンツ -->
          <main 
            id="onboarding-content"
            class="p-6 overflow-y-auto"
            tabindex="-1"
          >
            <Transition name="step-slide" mode="out-in">
              <component 
                :is="currentStepComponent" 
                :key="state.currentStep"
                @step-data="handleStepData"
                @validation-change="handleValidationChange"
              />
            </Transition>
          </main>
          
          <!-- フッター -->
          <footer 
            class="flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50"
            role="navigation"
            aria-label="オンボーディングナビゲーション"
          >
            <button 
              v-if="canGoBack" 
              @click="prevStep"
              :disabled="state.isLoading"
              class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors
                     disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500"
              aria-describedby="prev-step-desc"
            >
              戻る
            </button>
            <div v-else></div>
            
            <div class="flex gap-2">
              <button 
                @click="showSkipConfirm"
                :disabled="state.isLoading"
                class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors
                       disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-describedby="skip-desc"
              >
                スキップ
              </button>
              
              <button 
                @click="handleNext"
                :disabled="!canProceed || state.isLoading"
                class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors
                       disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500"
                :aria-describedby="isLastStep ? 'complete-desc' : 'next-step-desc'"
              >
                <span v-if="state.isLoading" class="inline-flex items-center">
                  <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  処理中...
                </span>
                <span v-else>
                  {{ isLastStep ? '完了' : '次へ' }}
                </span>
              </button>
            </div>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- 確認ダイアログ -->
  <SkipConfirmDialog 
    v-if="showSkipDialog"
    @confirm="handleSkipConfirm"
    @cancel="showSkipDialog = false"
  />

  <!-- 復帰確認ダイアログ -->
  <ResumeDialog
    v-if="showResumeDialog"
    :savedStep="savedStep"
    @resume="handleResume"
    @restart="handleRestart"
  />

  <!-- スクリーンリーダー用の説明テキスト -->
  <div class="sr-only">
    <div id="prev-step-desc">前のステップに戻ります</div>
    <div id="skip-desc">オンボーディングをスキップして、後で設定画面から確認できます</div>
    <div id="next-step-desc">次のステップに進みます</div>
    <div id="complete-desc">オンボーディングを完了します</div>
  </div>

  <!-- ライブリージョン -->
  <div aria-live="polite" id="sr-live-region" class="sr-only"></div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useOnboarding } from '@/composables/useOnboarding';
import { useOnboardingAccessibility } from '@/composables/useOnboardingAccessibility';

// コンポーネントインポート
import OnboardingProgress from './OnboardingProgress.vue';
import SkipConfirmDialog from './dialogs/SkipConfirmDialog.vue';
import ResumeDialog from './dialogs/ResumeDialog.vue';

// ステップコンポーネント
import WelcomeStep from './steps/WelcomeStep.vue';
import SetupStep from './steps/SetupStep.vue';
import FeatureStep from './steps/FeatureStep.vue';
import CompletionStep from './steps/CompletionStep.vue';

// Composables
const {
  state,
  progress,
  canProceed,
  canGoBack,
  isLastStep,
  nextStep,
  prevStep,
  skipOnboarding,
  completeOnboarding
} = useOnboarding();

const {
  setupFocusTrap,
  setupKeyboardNavigation,
  announceToScreenReader
} = useOnboardingAccessibility();

// ローカル状態
const modalRef = ref<HTMLElement>();
const showSkipDialog = ref(false);
const showResumeDialog = ref(false);
const savedStep = ref(0);

// ステップコンポーネントマッピング
const stepComponents = {
  1: WelcomeStep,
  2: SetupStep,
  3: FeatureStep,
  4: CompletionStep
};

// 計算プロパティ
const currentStepComponent = computed(() => 
  stepComponents[state.currentStep as keyof typeof stepComponents]
);

// イベントハンドラー
const handleNext = async () => {
  const success = isLastStep.value 
    ? await completeOnboarding()
    : await nextStep();
    
  if (success && !isLastStep.value) {
    announceToScreenReader(`ステップ${state.currentStep}に移動しました`);
    await nextTick();
    focusMainContent();
  }
};

const handleStepData = (data: Record<string, unknown>) => {
  // ステップデータの保存処理
  console.log('Step data received:', data);
};

const handleValidationChange = (isValid: boolean) => {
  // バリデーション状態の更新
  console.log('Validation changed:', isValid);
};

const showSkipConfirm = () => {
  showSkipDialog.value = true;
};

const handleSkipConfirm = async () => {
  showSkipDialog.value = false;
  await skipOnboarding('user_clicked_skip');
};

const handleBackdropClick = () => {
  // 背景クリックでは閉じない（UX考慮）
  // 必要に応じてスキップ確認を表示
};

const handleKeyDown = (event: KeyboardEvent) => {
  setupKeyboardNavigation(event, {
    onNext: canProceed.value ? handleNext : undefined,
    onPrev: canGoBack.value ? prevStep : undefined,
    onSkip: showSkipConfirm,
    onEscape: showSkipConfirm
  });
};

const focusMainContent = () => {
  const content = document.getElementById('onboarding-content');
  content?.focus();
};

const handleResume = () => {
  showResumeDialog.value = false;
  // 既存の進捗から継続
};

const handleRestart = () => {
  showResumeDialog.value = false;
  // 最初からやり直し
  state.currentStep = 1;
  state.completedSteps = [];
};

// ライフサイクル
onMounted(async () => {
  if (modalRef.value) {
    setupFocusTrap(modalRef.value);
    await nextTick();
    focusMainContent();
  }
});

onUnmounted(() => {
  // クリーンアップ処理
});
</script>

<style scoped>
/* モーダルアニメーション */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .relative,
.modal-leave-active .relative {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from .relative,
.modal-leave-to .relative {
  transform: scale(0.9) translateY(-20px);
  opacity: 0;
}

/* ステップアニメーション */
.step-slide-enter-active,
.step-slide-leave-active {
  transition: all 0.2s ease;
}

.step-slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.step-slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

/* スクリーンリーダー専用 */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
```

## 🔧 3. バックエンド設計

### 3.1 データベース設計

#### マイグレーション: ユーザーテーブル拡張
```php
<?php
// database/migrations/2024_01_15_000001_add_onboarding_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // オンボーディング関連カラム
            $table->timestamp('onboarding_completed_at')->nullable()
                  ->after('updated_at')
                  ->comment('オンボーディング完了日時');
                  
            $table->json('onboarding_progress')->nullable()
                  ->after('onboarding_completed_at')
                  ->comment('進捗データ（JSON）');
                  
            $table->boolean('onboarding_skipped')->default(false)
                  ->after('onboarding_progress')
                  ->comment('スキップフラグ');
                  
            $table->string('onboarding_version', 10)->default('1.0')
                  ->after('onboarding_skipped')
                  ->comment('オンボーディングバージョン');
                  
            $table->unsignedTinyInteger('login_count')->default(0)
                  ->after('onboarding_version')
                  ->comment('ログイン回数');
            
            // インデックス追加
            $table->index('onboarding_completed_at', 'idx_users_onboarding_completed');
            $table->index(['onboarding_skipped', 'created_at'], 'idx_users_onboarding_skipped');
            $table->index('login_count', 'idx_users_login_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_onboarding_completed');
            $table->dropIndex('idx_users_onboarding_skipped');
            $table->dropIndex('idx_users_login_count');
            
            $table->dropColumn([
                'onboarding_completed_at',
                'onboarding_progress',
                'onboarding_skipped',
                'onboarding_version',
                'login_count'
            ]);
        });
    }
};
```

#### マイグレーション: オンボーディングログテーブル
```php
<?php
// database/migrations/2024_01_15_000002_create_onboarding_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event_type', 50)->comment('イベント種別');
            $table->unsignedTinyInteger('step_number')->nullable()->comment('ステップ番号');
            $table->json('data')->nullable()->comment('追加データ');
            $table->string('session_id', 100)->nullable()->comment('セッションID');
            $table->string('user_agent', 500)->nullable()->comment('ユーザーエージェント');
            $table->ipAddress('ip_address')->nullable()->comment('IPアドレス');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            
            // インデックス
            $table->index(['user_id', 'event_type'], 'idx_onboarding_logs_user_event');
            $table->index('created_at', 'idx_onboarding_logs_created_at');
            $table->index(['event_type', 'created_at'], 'idx_onboarding_logs_event_created');
            $table->index('session_id', 'idx_onboarding_logs_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_logs');
    }
};
```

### 3.2 Eloquentモデル

#### OnboardingLogモデル
```php
<?php
// app/Models/OnboardingLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class OnboardingLog extends Model
{
    public $timestamps = false; // created_atのみ使用
    
    protected $fillable = [
        'user_id',
        'event_type',
        'step_number',
        'data',
        'session_id',
        'user_agent',
        'ip_address'
    ];
    
    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime'
    ];
    
    // イベントタイプ定数
    const EVENT_STARTED = 'started';
    const EVENT_STEP_COMPLETED = 'step_completed';
    const EVENT_STEP_ENTERED = 'step_entered';
    const EVENT_SKIPPED = 'skipped';
    const EVENT_COMPLETED = 'completed';
    const EVENT_REOPENED = 'reopened';
    const EVENT_ERROR = 'error';
    
    /**
     * ユーザーリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * ログ記録用のヘルパーメソッド
     */
    public static function logEvent(
        int $userId, 
        string $eventType, 
        ?int $stepNumber = null, 
        array $data = [],
        ?string $sessionId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'step_number' => $stepNumber,
            'data' => $data,
            'session_id' => $sessionId ?? session()->getId(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip()
        ]);
    }
    
    /**
     * 特定期間のイベントを取得
     */
    public function scopeInPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
    
    /**
     * 特定イベントタイプでフィルタ
     */
    public function scopeOfType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }
    
    /**
     * ユーザーの完了率を計算
     */
    public static function getCompletionRate(string $startDate, string $endDate): float
    {
        $started = self::ofType(self::EVENT_STARTED)
            ->inPeriod($startDate, $endDate)
            ->distinct('user_id')
            ->count();
            
        $completed = self::ofType(self::EVENT_COMPLETED)
            ->inPeriod($startDate, $endDate)
            ->distinct('user_id')
            ->count();
            
        return $started > 0 ? round(($completed / $started) * 100, 2) : 0;
    }
}
```

#### Userモデル拡張
```php
<?php
// app/Models/User.php に追加

use App\Models\OnboardingLog;

class User extends Authenticatable
{
    protected $fillable = [
        // 既存...
        'onboarding_completed_at',
        'onboarding_progress',
        'onboarding_skipped',
        'onboarding_version',
        'login_count'
    ];
    
    protected $casts = [
        // 既存...
        'onboarding_completed_at' => 'datetime',
        'onboarding_progress' => 'array',
        'onboarding_skipped' => 'boolean'
    ];
    
    /**
     * オンボーディングログリレーション
     */
    public function onboardingLogs(): HasMany
    {
        return $this->hasMany(OnboardingLog::class);
    }
    
    /**
     * オンボーディングを表示すべきかチェック
     */
    public function shouldShowOnboarding(): bool
    {
        // 1. 既に完了している場合は表示しない
        if ($this->onboarding_completed_at) {
            return false;
        }
        
        // 2. 登録から30日以内のユーザーのみ
        $daysSinceRegistration = $this->created_at->diffInDays(now());
        if ($daysSinceRegistration > 30) {
            return false;
        }
        
        // 3. ログイン回数が5回以下（新規ユーザー判定）
        if ($this->login_count > 5) {
            return false;
        }
        
        // 4. 管理者による無効化チェック
        if ($this->onboarding_disabled ?? false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * ログイン回数をインクリメント
     */
    public function incrementLoginCount(): void
    {
        $this->increment('login_count');
    }
    
    /**
     * オンボーディング進捗を更新
     */
    public function updateOnboardingProgress(
        int $currentStep, 
        array $completedSteps = [], 
        array $stepData = []
    ): void {
        $progress = $this->onboarding_progress ?? [];
        
        $progress['current_step'] = $currentStep;
        $progress['completed_steps'] = array_unique(array_merge(
            $progress['completed_steps'] ?? [],
            $completedSteps
        ));
        $progress['step_data'] = array_merge(
            $progress['step_data'] ?? [],
            $stepData
        );
        $progress['last_activity_at'] = now()->toISOString();
        
        // 開始時刻が未設定の場合は設定
        if (!isset($progress['started_at'])) {
            $progress['started_at'] = now()->toISOString();
            
            // 開始ログ記録
            OnboardingLog::logEvent($this->id, OnboardingLog::EVENT_STARTED);
        }
        
        $this->update(['onboarding_progress' => $progress]);
        
        // ステップ完了ログ記録
        foreach ($completedSteps as $step) {
            OnboardingLog::logEvent(
                $this->id,
                OnboardingLog::EVENT_STEP_COMPLETED,
                $step,
                ['timestamp' => now()->toISOString()]
            );
        }
    }
    
    /**
     * オンボーディング完了処理
     */
    public function completeOnboarding(array $completionData = []): void
    {
        $progress = $this->onboarding_progress ?? [];
        $progress['completed_steps'] = [1, 2, 3, 4];
        $progress['completed_at'] = now()->toISOString();
        $progress['completion_data'] = $completionData;
        
        $this->update([
            'onboarding_completed_at' => now(),
            'onboarding_progress' => $progress,
            'onboarding_skipped' => false
        ]);
        
        // 完了ログ記録
        OnboardingLog::logEvent(
            $this->id,
            OnboardingLog::EVENT_COMPLETED,
            null,
            array_merge(['completion_method' => 'normal'], $completionData)
        );
    }
    
    /**
     * オンボーディングスキップ処理
     */
    public function skipOnboarding(?int $currentStep = null, string $reason = 'user_choice'): void
    {
        $this->update([
            'onboarding_completed_at' => now(),
            'onboarding_skipped' => true
        ]);
        
        // スキップログ記録
        OnboardingLog::logEvent(
            $this->id,
            OnboardingLog::EVENT_SKIPPED,
            $currentStep,
            [
                'skip_method' => $reason,
                'completed_steps' => $this->onboarding_progress['completed_steps'] ?? []
            ]
        );
    }
    
    /**
     * オンボーディング統計取得
     */
    public function getOnboardingStats(): array
    {
        $progress = $this->onboarding_progress ?? [];
        
        return [
            'is_completed' => !is_null($this->onboarding_completed_at),
            'is_skipped' => $this->onboarding_skipped,
            'completed_steps' => $progress['completed_steps'] ?? [],
            'current_step' => $progress['current_step'] ?? 1,
            'started_at' => $progress['started_at'] ?? null,
            'total_steps' => 4,
            'completion_rate' => count($progress['completed_steps'] ?? []) / 4 * 100,
            'version' => $this->onboarding_version
        ];
    }
}
```

### 3.3 APIコントローラー

```php
<?php
// app/Http/Controllers/Api/OnboardingController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    /**
     * オンボーディング状態取得
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // ログイン回数を増加（セッション毎に1回のみ）
            if (!session()->has('login_counted_' . $user->id)) {
                $user->incrementLoginCount();
                session()->put('login_counted_' . $user->id, true);
            }
            
            $shouldShow = $user->shouldShowOnboarding();
            $stats = $user->getOnboardingStats();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'should_show' => $shouldShow,
                    'completed_at' => $user->onboarding_completed_at?->toISOString(),
                    'progress' => $user->onboarding_progress,
                    'skipped' => (bool) $user->onboarding_skipped,
                    'stats' => $stats
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse('状態取得中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディング進捗更新
     */
    public function updateProgress(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_step' => 'required|integer|min:1|max:4',
                'completed_steps' => 'array',
                'completed_steps.*' => 'integer|min:1|max:4',
                'step_data' => 'array',
                'timestamp' => 'string|date_format:Y-m-d\TH:i:s\Z'
            ]);
            
            $user = $request->user();
            
            // 進捗更新
            $user->updateOnboardingProgress(
                $validated['current_step'],
                $validated['completed_steps'] ?? [],
                $validated['step_data'] ?? []
            );
            
            return response()->json([
                'success' => true,
                'message' => '進捗を更新しました',
                'data' => [
                    'current_step' => $validated['current_step'],
                    'completed_steps' => $validated['completed_steps'] ?? []
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('進捗更新中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディング完了
     */
    public function complete(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'completed_steps' => 'array',
                'completed_steps.*' => 'integer|min:1|max:4',
                'total_time_spent' => 'integer|min:0',
                'step_times' => 'array',
                'feedback' => 'string|max:1000'
            ]);
            
            $user = $request->user();
            
            // 完了処理
            $user->completeOnboarding([
                'total_time_spent' => $validated['total_time_spent'] ?? 0,
                'step_times' => $validated['step_times'] ?? [],
                'feedback' => $validated['feedback'] ?? null,
                'completion_source' => 'web_app'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'オンボーディングが完了しました',
                'data' => [
                    'completed_at' => $user->fresh()->onboarding_completed_at->toISOString(),
                    'stats' => $user->getOnboardingStats()
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('完了処理中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディングスキップ
     */
    public function skip(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_step' => 'integer|min:1|max:4',
                'reason' => 'string|max:100',
                'completed_steps' => 'array',
                'completed_steps.*' => 'integer|min:1|max:4'
            ]);
            
            $user = $request->user();
            
            // スキップ処理
            $user->skipOnboarding(
                $validated['current_step'] ?? null,
                $validated['reason'] ?? 'user_choice'
            );
            
            return response()->json([
                'success' => true,
                'message' => 'オンボーディングをスキップしました',
                'data' => [
                    'skipped_at' => $user->fresh()->onboarding_completed_at->toISOString(),
                    'skipped_step' => $validated['current_step'] ?? null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('スキップ処理中にエラーが発生しました', $e);
        }
    }
    
    /**
     * オンボーディング統計取得（管理者用）
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'group_by' => 'string|in:day,week,month'
            ]);
            
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            
            // 基本統計
            $completionRate = OnboardingLog::getCompletionRate($startDate, $endDate);
            
            // ステップ別完了率
            $stepCompletions = OnboardingLog::ofType(OnboardingLog::EVENT_STEP_COMPLETED)
                ->inPeriod($startDate, $endDate)
                ->selectRaw('step_number, COUNT(*) as completions')
                ->groupBy('step_number')
                ->orderBy('step_number')
                ->get();
            
            // 日別統計
            $dailyStats = OnboardingLog::inPeriod($startDate, $endDate)
                ->selectRaw('DATE(created_at) as date, event_type, COUNT(*) as count')
                ->groupBy('date', 'event_type')
                ->orderBy('date')
                ->get()
                ->groupBy('date');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'completion_rate' => $completionRate,
                    'step_completions' => $stepCompletions,
                    'daily_stats' => $dailyStats,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー',
                'errors' => $e->errors(),
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'requestId' => $request->header('X-Request-ID', uniqid()),
                    'version' => '1.0'
                ]
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('統計取得中にエラーが発生しました', $e);
        }
    }
    
    /**
     * エラーレスポンス生成
     */
    private function errorResponse(string $message, \Exception $e, int $statusCode = 500): JsonResponse
    {
        // ログ記録
        logger()->error('Onboarding API Error', [
            'message' => $message,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => app()->environment('production') ? null : $e->getMessage(),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'requestId' => request()->header('X-Request-ID', uniqid()),
                'version' => '1.0'
            ]
        ], $statusCode);
    }
}
```

### 3.4 ルーティング
```php
<?php
// routes/api.php に追加

use App\Http\Controllers\Api\OnboardingController;

Route::middleware('auth:sanctum')->group(function () {
    // オンボーディング関連API
    Route::prefix('onboarding')->group(function () {
        Route::get('/status', [OnboardingController::class, 'status']);
        Route::post('/progress', [OnboardingController::class, 'updateProgress']);
        Route::post('/complete', [OnboardingController::class, 'complete']);
        Route::post('/skip', [OnboardingController::class, 'skip']);
        
        // 管理者用統計API（必要に応じて権限チェック追加）
        Route::middleware('can:view-analytics')->group(function () {
            Route::get('/analytics', [OnboardingController::class, 'analytics']);
        });
    });
});
```

## 🧪 4. テスト設計

### 4.1 フロントエンドテスト（Vitest）
```typescript
// tests/components/OnboardingModal.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import OnboardingModal from '@/components/onboarding/OnboardingModal.vue';
import { useOnboarding } from '@/composables/useOnboarding';

// Mock composable
vi.mock('@/composables/useOnboarding');

describe('OnboardingModal', () => {
  const mockOnboarding = {
    state: {
      currentStep: 1,
      totalSteps: 4,
      completedSteps: [],
      isVisible: true,
      isLoading: false
    },
    progress: 25,
    canProceed: true,
    canGoBack: false,
    isLastStep: false,
    nextStep: vi.fn(),
    prevStep: vi.fn(),
    skipOnboarding: vi.fn(),
    completeOnboarding: vi.fn()
  };

  beforeEach(() => {
    vi.mocked(useOnboarding).mockReturnValue(mockOnboarding);
  });

  it('モーダルが正しく表示される', () => {
    const wrapper = mount(OnboardingModal);
    
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
    expect(wrapper.find('#onboarding-title').text()).toContain('すたログ - 初回設定ガイド');
    expect(wrapper.find('#step-indicator').text()).toContain('ステップ 1/4');
  });

  it('次へボタンクリックで nextStep が呼ばれる', async () => {
    const wrapper = mount(OnboardingModal);
    
    await wrapper.find('button:contains("次へ")').trigger('click');
    
    expect(mockOnboarding.nextStep).toHaveBeenCalledOnce();
  });

  it('キーボードナビゲーションが機能する', async () => {
    const wrapper = mount(OnboardingModal);
    
    // Enterキーで次へ進む
    await wrapper.trigger('keydown', { key: 'Enter' });
    expect(mockOnboarding.nextStep).toHaveBeenCalledOnce();
    
    // Escapeキーでスキップ確認
    await wrapper.trigger('keydown', { key: 'Escape' });
    expect(wrapper.find('.skip-dialog').exists()).toBe(true);
  });

  it('アクセシビリティ属性が正しく設定される', () => {
    const wrapper = mount(OnboardingModal);
    
    const modal = wrapper.find('[role="dialog"]');
    expect(modal.attributes('aria-modal')).toBe('true');
    expect(modal.attributes('aria-labelledby')).toBe('onboarding-title');
    expect(modal.attributes('aria-describedby')).toBe('onboarding-content');
  });

  it('プログレスバーが正しい値を表示する', () => {
    const wrapper = mount(OnboardingModal);
    
    const progressBar = wrapper.find('[role="progressbar"]');
    expect(progressBar.attributes('aria-valuenow')).toBe('25');
    expect(progressBar.attributes('aria-valuetext')).toBe('25% 完了');
  });
});
```

### 4.2 E2Eテスト（Playwright）
```typescript
// tests/e2e/onboarding.spec.ts
import { test, expect } from '@playwright/test';

test.describe('オンボーディング機能', () => {
  test.beforeEach(async ({ page }) => {
    // 新規ユーザーとしてログイン
    await page.goto('/register');
    await page.fill('[data-testid="nickname"]', 'テストユーザー');
    await page.fill('[data-testid="email"]', 'test@example.com');
    await page.fill('[data-testid="password"]', 'password123');
    await page.fill('[data-testid="password_confirmation"]', 'password123');
    await page.click('[data-testid="register-button"]');
  });

  test('新規ユーザーのハッピーパス', async ({ page }) => {
    // オンボーディング自動表示確認
    await expect(page.locator('[data-testid="onboarding-modal"]')).toBeVisible();
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 1/4');

    // Step 1: ウェルカム
    await expect(page.locator('h2')).toContainText('すたログへようこそ');
    await page.click('[data-testid="next-button"]');

    // Step 2: 設定
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 2/4');
    await page.click('[data-testid="exam-type-jstqb"]');
    await page.check('[data-testid="subject-area-test-design"]');
    await page.click('[data-testid="next-button"]');

    // Step 3: 機能説明
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 3/4');
    await expect(page.locator('text=基本機能紹介')).toBeVisible();
    await page.click('[data-testid="next-button"]');

    // Step 4: 完了
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 4/4');
    await expect(page.locator('text=準備完了')).toBeVisible();
    await page.click('[data-testid="complete-button"]');

    // ダッシュボードに遷移確認
    await expect(page.locator('[data-testid="dashboard"]')).toBeVisible();
    await expect(page.url()).toContain('/dashboard');
  });

  test('スキップ機能', async ({ page }) => {
    // オンボーディング表示確認
    await expect(page.locator('[data-testid="onboarding-modal"]')).toBeVisible();

    // スキップボタンクリック
    await page.click('[data-testid="skip-button"]');

    // 確認ダイアログ表示
    await expect(page.locator('[data-testid="skip-confirm-dialog"]')).toBeVisible();
    await expect(page.locator('text=後で設定画面から確認できます')).toBeVisible();

    // スキップ確定
    await page.click('[data-testid="skip-confirm-yes"]');

    // ダッシュボードに遷移
    await expect(page.locator('[data-testid="dashboard"]')).toBeVisible();
  });

  test('中断・復帰機能', async ({ page }) => {
    // Step 2まで進行
    await page.click('[data-testid="next-button"]'); // Step 1 → 2
    await page.click('[data-testid="exam-type-jstqb"]');

    // ページを再読み込み（中断を模倣）
    await page.reload();

    // 復帰確認ダイアログ表示
    await expect(page.locator('[data-testid="resume-dialog"]')).toBeVisible();
    await expect(page.locator('text=前回はステップ2まで進んでいました')).toBeVisible();

    // 続きから開始
    await page.click('[data-testid="resume-continue"]');

    // Step 2から再開確認
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 2/4');
    await expect(page.locator('[data-testid="exam-type-jstqb"]')).toBeChecked();
  });

  test('アクセシビリティ', async ({ page }) => {
    // キーボードナビゲーション
    await page.keyboard.press('Enter'); // 次へ
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 2/4');

    await page.keyboard.press('ArrowLeft'); // 戻る
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 1/4');

    await page.keyboard.press('Escape'); // スキップ確認
    await expect(page.locator('[data-testid="skip-confirm-dialog"]')).toBeVisible();
  });

  test('レスポンシブ対応', async ({ page }) => {
    // モバイルサイズに変更
    await page.setViewportSize({ width: 375, height: 667 });

    await expect(page.locator('[data-testid="onboarding-modal"]')).toBeVisible();

    // タッチ操作（左スワイプで次へ）
    const modal = page.locator('[data-testid="onboarding-modal"]');
    await modal.hover();
    await page.mouse.down();
    await page.mouse.move(-100, 0); // 左に100px移動
    await page.mouse.up();

    // 次のステップに進行確認
    await expect(page.locator('[data-testid="step-indicator"]')).toContainText('ステップ 2/4');
  });
});
```

### 4.3 バックエンドテスト（PHPUnit）
```php
<?php
// tests/Feature/OnboardingControllerTest.php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OnboardingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_status_returns_correct_data_for_new_user(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'should_show' => true,
                    'completed_at' => null,
                    'skipped' => false
                ]
            ]);
    }

    public function test_status_returns_false_for_completed_user(): void
    {
        $this->user->update(['onboarding_completed_at' => now()]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'should_show' => false
                ]
            ]);
    }

    public function test_update_progress_saves_correctly(): void
    {
        $data = [
            'current_step' => 2,
            'completed_steps' => [1],
            'step_data' => ['exam_type' => 'JSTQB']
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $progress = $this->user->onboarding_progress;

        $this->assertEquals(2, $progress['current_step']);
        $this->assertEquals([1], $progress['completed_steps']);
        $this->assertEquals(['exam_type' => 'JSTQB'], $progress['step_data']);
    }

    public function test_complete_marks_user_as_completed(): void
    {
        $data = [
            'completed_steps' => [1, 2, 3, 4],
            'total_time_spent' => 300
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/complete', $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
        $this->assertFalse($this->user->onboarding_skipped);

        // ログ記録確認
        $this->assertDatabaseHas('onboarding_logs', [
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_COMPLETED
        ]);
    }

    public function test_skip_marks_user_as_skipped(): void
    {
        $data = [
            'current_step' => 2,
            'reason' => 'user_choice'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/skip', $data);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->user->refresh();
        $this->assertNotNull($this->user->onboarding_completed_at);
        $this->assertTrue($this->user->onboarding_skipped);

        // ログ記録確認
        $this->assertDatabaseHas('onboarding_logs', [
            'user_id' => $this->user->id,
            'event_type' => OnboardingLog::EVENT_SKIPPED,
            'step_number' => 2
        ]);
    }

    public function test_validation_errors_are_handled_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/onboarding/progress', [
                'current_step' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'バリデーションエラー'
            ])
            ->assertJsonStructure([
                'errors' => ['current_step']
            ]);
    }

    public function test_analytics_returns_correct_statistics(): void
    {
        // テストデータ作成
        $users = User::factory()->count(10)->create();
        
        foreach ($users as $index => $user) {
            OnboardingLog::factory()->create([
                'user_id' => $user->id,
                'event_type' => OnboardingLog::EVENT_STARTED
            ]);
            
            if ($index < 7) { // 70%が完了
                OnboardingLog::factory()->create([
                    'user_id' => $user->id,
                    'event_type' => OnboardingLog::EVENT_COMPLETED
                ]);
            }
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/onboarding/analytics?' . http_build_query([
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->toDateString()
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'completion_rate' => 70.0
                ]
            ]);
    }
}
```

## 🚀 5. デプロイ・実装順序

### 5.1 実装フェーズ
```
Phase 1: 基盤構築（1-2週間）
├── データベースマイグレーション実行
├── Eloquentモデル拡張
├── 基本APIエンドポイント実装
└── 基本テスト作成

Phase 2: フロントエンド実装（2-3週間）
├── Composables作成
├── 基本モーダルコンポーネント実装
├── 各ステップコンポーネント実装
└── アクセシビリティ対応

Phase 3: 統合・テスト（1-2週間）
├── App.vueへの統合
├── 認証フローとの連携
├── E2Eテスト実装
└── バグ修正・調整

Phase 4: 改善・最適化（1週間）
├── アニメーション調整
├── パフォーマンス最適化
├── 分析機能実装
└── ドキュメント整備
```

### 5.2 リリース計画
1. **Beta版**: 内部テスト・フィードバック収集
2. **Staged rollout**: 新規ユーザーの10%から開始
3. **Full rollout**: 段階的に100%まで拡大
4. **Post-launch**: データ分析・改善施策実施

## 📊 6. 監視・メトリクス

### 6.1 技術メトリクス
- **API レスポンス時間**: 平均 < 200ms
- **フロントエンド読み込み時間**: 初回表示 < 500ms
- **エラー率**: < 1%
- **可用性**: > 99.9%

### 6.2 ビジネスメトリクス
- **完了率**: > 80%
- **スキップ率**: < 30%
- **平均所要時間**: < 3分
- **再表示率**: リリース後測定

これらの設計に基づいて、段階的に実装を進めることで、堅牢で使いやすいオンボーディング機能が実現できます。