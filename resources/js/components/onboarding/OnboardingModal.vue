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
          class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col
                 transform transition-all duration-300 ease-out"
          :class="{ 'scale-95 opacity-0': state.isLoading }"
        >
          <!-- ヘッダー -->
          <header class="flex-shrink-0 flex justify-between items-center p-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
              <span class="text-2xl" aria-hidden="true">📚</span>
              <div>
                <h1 id="onboarding-title" class="text-lg font-semibold text-gray-900">
                  すたログ - 初回セットアップガイド
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
          <div class="flex-shrink-0 border-b border-gray-200">
            <div 
              class="h-2 bg-blue-600 transition-all duration-300 ease-out"
              :style="{ width: progress + '%' }"
              role="progressbar"
              :aria-valuenow="progress"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-valuetext="`${progress}% 完了`"
            ></div>
          </div>
          
          <!-- メインコンテンツ -->
          <main 
            id="onboarding-content"
            class="flex-1 p-6 overflow-y-auto min-h-0"
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
            class="flex-shrink-0 flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50"
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
  <div 
    v-if="showSkipDialog"
    class="fixed inset-0 z-60 flex items-center justify-center"
  >
    <div class="absolute inset-0 bg-black bg-opacity-50" @click="showSkipDialog = false"></div>
    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">
        オンボーディングをスキップしますか？
      </h3>
      <p class="text-gray-600 mb-6">
        後で設定画面の「使い方ガイド」からいつでも確認できます。
      </p>
      <div class="flex justify-end gap-3">
        <button 
          @click="showSkipDialog = false"
          class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
        >
          キャンセル
        </button>
        <button 
          @click="handleSkipConfirm"
          class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
        >
          スキップ
        </button>
      </div>
    </div>
  </div>

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

<script>
import { reactive, computed, ref, onMounted, onUnmounted, nextTick } from 'vue'
import OnboardingAPI from '../../utils/OnboardingAPI'
import OnboardingStorage from '../../utils/OnboardingStorage'

// ステップコンポーネント
import WelcomeStep from './steps/WelcomeStep.vue'
import SetupStep from './steps/SetupStep.vue'
import FeatureStep from './steps/FeatureStep.vue'
import CompletionStep from './steps/CompletionStep.vue'

export default {
  name: 'OnboardingModal',
  components: {
    WelcomeStep,
    SetupStep,
    FeatureStep,
    CompletionStep
  },
  setup() {
    // リアクティブな状態
    const state = reactive({
      currentStep: 1,
      totalSteps: 4,
      completedSteps: [],
      isVisible: false,
      isLoading: false,
      startedAt: null,
      lastActivity: null
    })

    // ローカル状態
    const modalRef = ref(null)
    const showSkipDialog = ref(false)
    const stepValidation = ref({})

    // ステップコンポーネントマッピング
    const stepComponents = {
      1: WelcomeStep,
      2: SetupStep,
      3: FeatureStep,
      4: CompletionStep
    }

    // 計算プロパティ
    const currentStepComponent = computed(() => 
      stepComponents[state.currentStep]
    )

    const progress = computed(() => 
      Math.round((state.currentStep / state.totalSteps) * 100)
    )

    const canProceed = computed(() => {
      // Step 2のみバリデーションが必要
      if (state.currentStep === 2) {
        return stepValidation.value.isValid === true
      }
      return true
    })

    const canGoBack = computed(() => state.currentStep > 1)

    const isLastStep = computed(() => state.currentStep === state.totalSteps)

    // メソッド
    const showOnboarding = async () => {
      try {
        state.isLoading = true
        
        // サーバーから状態を取得
        const response = await OnboardingAPI.getStatus()
        
        if (response.success && response.data.should_show) {
          state.isVisible = true
          state.startedAt = new Date()
          
          // 既存の進捗があれば復元
          if (response.data.progress) {
            restoreProgress(response.data.progress)
          }
        }
      } catch (error) {
        console.error('オンボーディング表示エラー:', error)
        // フォールバック: ローカルストレージから復元
        restoreFromStorage()
      } finally {
        state.isLoading = false
      }
    }

    const nextStep = async () => {
      if (!canProceed.value) return false

      try {
        state.isLoading = true

        // 現在のステップを完了済みに追加
        if (!state.completedSteps.includes(state.currentStep)) {
          state.completedSteps.push(state.currentStep)
        }

        // 最後のステップの場合は完了処理
        if (isLastStep.value) {
          await completeOnboarding()
          return true
        }

        // 次のステップに進行
        state.currentStep++
        state.lastActivity = new Date()

        // サーバーに進捗を同期
        await syncProgress()

        // スクリーンリーダーに通知
        announceStepChange()

        return true
      } catch (error) {
        console.error('ステップ進行エラー:', error)
        return false
      } finally {
        state.isLoading = false
      }
    }

    const prevStep = async () => {
      if (!canGoBack.value) return false

      try {
        state.currentStep--
        state.lastActivity = new Date()
        
        await syncProgress()
        announceStepChange()
        
        return true
      } catch (error) {
        console.error('ステップ後退エラー:', error)
        return false
      }
    }

    const skipOnboarding = async (reason = 'user_choice') => {
      try {
        state.isLoading = true

        // サーバーにスキップを記録
        await OnboardingAPI.skip({
          currentStep: state.currentStep,
          reason: reason,
          completedSteps: state.completedSteps
        })

        // 状態リセット
        resetState()
        
      } catch (error) {
        console.error('スキップ処理エラー:', error)
        // エラーでも閉じる
        resetState()
      } finally {
        state.isLoading = false
      }
    }

    const completeOnboarding = async () => {
      try {
        state.isLoading = true

        // 全ステップ完了済みにマーク
        state.completedSteps = [1, 2, 3, 4]

        // サーバーに完了を記録（step_dataを含める）
        let completionData
        try {
          const allStepData = OnboardingStorage.getAllStepData()
          const setupStepData = allStepData[2] // SetupStepは2番目のステップ
          
          completionData = {
            completed_steps: state.completedSteps,
            total_time_spent: calculateTotalTime(),
            step_data: setupStepData?.step_data || {}
          }
          
          // デバッグログ追加
          console.log('🔍 OnboardingModal completeOnboarding:', {
            completionData,
            allStepData,
            setupStepData,
            extractedStepData: setupStepData?.step_data
          })
        } catch (dataError) {
          console.error('step_data抽出エラー:', dataError)
          // フォールバック：step_dataなしで完了
          completionData = {
            completed_steps: state.completedSteps,
            total_time_spent: calculateTotalTime(),
            step_data: {}
          }
        }
        
        await OnboardingAPI.complete(completionData)

        // 状態リセット
        resetState()

      } catch (error) {
        console.error('完了処理エラー:', error)
        // エラーでも閉じる
        resetState()
      } finally {
        state.isLoading = false
      }
    }

    // ヘルパーメソッド
    const syncProgress = async () => {
      try {
        await OnboardingAPI.updateProgress({
          current_step: state.currentStep,
          completed_steps: state.completedSteps,
          step_data: OnboardingStorage.getAllStepData(),
          timestamp: new Date().toISOString().replace(/\.\d{3}Z$/, 'Z')
        })

        // ローカルストレージにも保存
        OnboardingStorage.saveState(state)
      } catch (error) {
        console.error('進捗同期エラー:', error)
        // ローカルストレージのみ保存
        OnboardingStorage.saveState(state)
      }
    }

    const restoreProgress = (progressData) => {
      if (progressData.current_step) {
        state.currentStep = progressData.current_step
      }
      if (progressData.completed_steps) {
        state.completedSteps = [...progressData.completed_steps]
      }
      if (progressData.step_data) {
        OnboardingStorage.restoreStepData(progressData.step_data)
      }
    }

    const restoreFromStorage = () => {
      const savedState = OnboardingStorage.getState()
      if (savedState && OnboardingStorage.isValidSession()) {
        Object.assign(state, savedState)
        state.isVisible = true
      }
    }

    const resetState = () => {
      state.currentStep = 1
      state.completedSteps = []
      state.isVisible = false
      state.startedAt = null
      state.lastActivity = null
      
      OnboardingStorage.clearAll()
    }

    const calculateTotalTime = () => {
      if (!state.startedAt) return 0
      return Math.floor((Date.now() - state.startedAt.getTime()) / 1000)
    }

    const announceStepChange = () => {
      const liveRegion = document.getElementById('sr-live-region')
      if (liveRegion) {
        liveRegion.textContent = `ステップ${state.currentStep}/${state.totalSteps}に移動しました`
      }
    }

    // イベントハンドラー
    const handleNext = async () => {
      const success = isLastStep.value 
        ? await completeOnboarding()
        : await nextStep()
        
      if (success && !isLastStep.value) {
        await nextTick()
        focusMainContent()
      }
    }

    const handleStepData = (data) => {
      OnboardingStorage.saveStepData(state.currentStep, data)
    }

    const handleValidationChange = (validationResult) => {
      stepValidation.value = validationResult
    }

    const showSkipConfirm = () => {
      showSkipDialog.value = true
    }

    const handleSkipConfirm = async () => {
      showSkipDialog.value = false
      await skipOnboarding('user_clicked_skip')
    }

    const handleBackdropClick = () => {
      // 背景クリックでは閉じない（UX考慮）
      // 必要に応じてスキップ確認画面を表示
    }

    const handleKeyDown = (event) => {
      switch (event.key) {
        case 'Escape':
          showSkipConfirm()
          break
        case 'ArrowRight':
        case 'Enter':
          if (canProceed.value && !state.isLoading) {
            handleNext()
          }
          break
        case 'ArrowLeft':
          if (canGoBack.value && !state.isLoading) {
            prevStep()
          }
          break
      }
    }

    const focusMainContent = () => {
      const content = document.getElementById('onboarding-content')
      content?.focus()
    }

    // ライフサイクル
    onMounted(async () => {
      if (modalRef.value) {
        await nextTick()
        focusMainContent()
      }
    })

    // 公開するプロパティとメソッド
    return {
      // 状態
      state,
      modalRef,
      showSkipDialog,
      
      // 計算プロパティ
      currentStepComponent,
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
      
      // イベントハンドラー
      handleNext,
      handleStepData,
      handleValidationChange,
      showSkipConfirm,
      handleSkipConfirm,
      handleBackdropClick,
      handleKeyDown
    }
  }
}
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