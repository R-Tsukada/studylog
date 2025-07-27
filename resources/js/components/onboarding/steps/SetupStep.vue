<template>
  <div>
    <!-- ステップタイトル -->
    <div class="text-center mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">
        基本設定
      </h2>
      <p class="text-gray-600">
        あなたの学習内容を設定してください
      </p>
    </div>

    <!-- フォーム -->
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- 受験予定の資格 -->
      <div>
        <label for="exam-type" class="block text-sm font-medium text-gray-700 mb-2">
          受験予定の資格 <span class="text-red-500">*</span>
        </label>
        <select
          id="exam-type"
          v-model="form.examType"
          @change="handleExamTypeChange"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          :class="{ 'border-red-500': errors.examType }"
          required
          aria-describedby="exam-type-error"
        >
          <option value="">選択してください</option>
          <option v-for="exam in examTypes" :key="exam.value" :value="exam.value">
            {{ exam.label }}
          </option>
          <option value="custom">カスタム試験を作成</option>
        </select>
        <p 
          v-if="errors.examType" 
          id="exam-type-error" 
          class="mt-1 text-sm text-red-600"
          role="alert"
        >
          {{ errors.examType }}
        </p>
      </div>

      <!-- カスタム試験フォーム -->
      <div v-if="form.examType === 'custom'" class="bg-gray-50 border border-gray-200 rounded-md p-4">
        <CustomExamForm
          v-model="form.customExam"
          @validation-change="handleCustomExamValidation"
        />
      </div>

      <!-- 試験日程 -->
      <div>
        <label for="exam-date" class="block text-sm font-medium text-gray-700 mb-2">
          試験予定日（任意）
        </label>
        <input
          id="exam-date"
          v-model="form.examDate"
          type="date"
          :min="minDate"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          :class="{ 'border-red-500': errors.examDate }"
          aria-describedby="exam-date-help exam-date-error"
        />
        <p id="exam-date-help" class="mt-1 text-sm text-gray-500">
          設定すると試験までの日数を表示します
        </p>
        <p 
          v-if="errors.examDate" 
          id="exam-date-error" 
          class="mt-1 text-sm text-red-600"
          role="alert"
        >
          {{ errors.examDate }}
        </p>
      </div>

      <!-- 学習分野（資格選択後に表示） -->
      <div v-if="form.examType && availableSubjects.length > 0">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          重点的に学習したい分野
        </label>
        <div class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-md p-3">
          <div
            v-for="subject in availableSubjects"
            :key="subject.value"
            class="flex items-center"
          >
            <input
              :id="`subject-${subject.value}`"
              v-model="form.subjects"
              :value="subject.value"
              type="checkbox"
              class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
            />
            <label
              :for="`subject-${subject.value}`"
              class="ml-2 text-sm text-gray-700 cursor-pointer"
            >
              {{ subject.label }}
            </label>
          </div>
        </div>
        <p class="mt-1 text-sm text-gray-500">
          複数選択可能です（後から変更できます）
        </p>
      </div>

      <!-- 1日の目標学習時間 -->
      <div>
        <label for="daily-goal" class="block text-sm font-medium text-gray-700 mb-2">
          1日の目標学習時間
        </label>
        <div class="flex items-center space-x-3">
          <input
            id="daily-goal"
            v-model.number="form.dailyGoalMinutes"
            type="range"
            min="15"
            max="480"
            step="15"
            class="flex-1"
            aria-describedby="daily-goal-display"
          />
          <div
            id="daily-goal-display"
            class="text-sm font-medium text-gray-700 w-20 text-right"
          >
            {{ formatMinutes(form.dailyGoalMinutes) }}
          </div>
        </div>
        <div class="mt-1 flex justify-between text-xs text-gray-500">
          <span>15分</span>
          <span>8時間</span>
        </div>
      </div>

      <!-- プロフィール情報（任意） -->
      <div class="border-t border-gray-200 pt-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
          プロフィール情報（任意）
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- 表示名 -->
          <div>
            <label for="display-name" class="block text-sm font-medium text-gray-700 mb-2">
              表示名
            </label>
            <input
              id="display-name"
              v-model="form.displayName"
              type="text"
              maxlength="50"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="例: 太郎"
              aria-describedby="display-name-help"
            />
            <p id="display-name-help" class="mt-1 text-sm text-gray-500">
              学習統計で使用されます
            </p>
          </div>

          <!-- 職業・属性 -->
          <div>
            <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">
              職業・属性
            </label>
            <select
              id="occupation"
              v-model="form.occupation"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">選択してください</option>
              <option value="student">学生</option>
              <option value="engineer">エンジニア</option>
              <option value="office_worker">会社員</option>
              <option value="freelancer">フリーランス</option>
              <option value="other">その他</option>
            </select>
          </div>
        </div>
      </div>
    </form>

    <!-- バリデーション要約 -->
    <div v-if="Object.keys(errors).length > 0" class="mt-6 p-4 bg-red-50 border border-red-200 rounded-md">
      <div class="flex">
        <div class="flex-shrink-0">
          <span class="text-red-400" aria-hidden="true">⚠️</span>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">
            入力内容を確認してください
          </h3>
          <div class="mt-2 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
              <li v-for="(error, field) in errors" :key="field">
                {{ error }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { reactive, computed, watch, onMounted } from 'vue'
import { examTypes, subjectsByExam } from '../../../utils/examConfig'
import CustomExamForm from '../CustomExamForm.vue'

export default {
  name: 'SetupStep',
  components: {
    CustomExamForm
  },
  emits: ['step-data', 'validation-change'],
  setup(_, { emit }) {
    // フォームデータ
    const form = reactive({
      examType: '',
      examDate: '',
      subjects: [],
      dailyGoalMinutes: 60,
      displayName: '',
      occupation: '',
      customExam: {
        name: '',
        description: '',
        color: '#9333EA',
        notes: ''
      }
    })

    // エラー状態
    const errors = reactive({})
    
    // カスタム試験のバリデーション状態
    const customExamValidation = reactive({
      isValid: false,
      errors: {}
    })

    // 計算プロパティ
    const availableSubjects = computed(() => {
      return subjectsByExam[form.examType] || []
    })

    const minDate = computed(() => {
      const today = new Date()
      return today.toISOString().split('T')[0]
    })

    const isValid = computed(() => {
      const basicValid = Object.keys(errors).length === 0 && form.examType !== ''
      
      // カスタム試験の場合は追加バリデーション
      if (form.examType === 'custom') {
        return basicValid && customExamValidation.isValid
      }
      
      return basicValid
    })

    // メソッド
    const formatMinutes = (minutes) => {
      if (typeof minutes !== 'number' || isNaN(minutes) || minutes < 0) {
        console.warn('Invalid minutes value:', minutes);
        return '0分';
      }
      
      const hours = Math.floor(minutes / 60)
      const mins = minutes % 60
      if (hours === 0) {
        return `${mins}分`
      } else if (mins === 0) {
        return `${hours}時間`
      } else {
        return `${hours}時間${mins}分`
      }
    }

    const validateForm = () => {
      // エラーをクリア
      Object.keys(errors).forEach(key => delete errors[key])

      // 必須チェック
      if (!form.examType) {
        errors.examType = '受験予定の資格を選択してください'
      }

      // 試験日チェック
      if (form.examDate) {
        const selectedDate = new Date(form.examDate)
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        selectedDate.setHours(0, 0, 0, 0)
        
        if (selectedDate < today) {
          errors.examDate = '試験日は今日以降の日付を選択してください'
        }
      }

      // 表示名の長さチェック
      if (form.displayName && form.displayName.length > 50) {
        errors.displayName = '表示名は50文字以内で入力してください'
      }

      return Object.keys(errors).length === 0
    }

    const handleExamTypeChange = () => {
      // 資格変更時に学習分野をクリア
      form.subjects = []
      
      // カスタム試験でない場合はカスタム試験データをクリア
      if (form.examType !== 'custom') {
        form.customExam = {
          name: '',
          description: '',
          color: '#9333EA',
          notes: ''
        }
        customExamValidation.isValid = false
        customExamValidation.errors = {}
      }
      
      validateForm()
    }

    const handleCustomExamValidation = (validation) => {
      customExamValidation.isValid = validation.isValid
      customExamValidation.errors = validation.errors
    }

    const handleSubmit = () => {
      if (validateForm()) {
        emitStepData()
      }
    }

    const emitStepData = () => {
      // バックエンドのOnboardingCompleteRequestで期待される形式
      const stepData = {
        exam_type: form.examType,
        exam_date: form.examDate || null,
        daily_goal_minutes: form.dailyGoalMinutes
      }

      // カスタム試験の場合は追加データを含める
      if (form.examType === 'custom' && form.customExam.name) {
        stepData.custom_exam_name = form.customExam.name
        stepData.custom_exam_description = form.customExam.description || null
        stepData.custom_exam_color = form.customExam.color
        stepData.custom_exam_notes = form.customExam.notes || null
      }

      const data = {
        // 既存のプロフィール情報（後方互換性のため）
        examType: form.examType,
        examDate: form.examDate || null,
        subjects: form.subjects,
        dailyGoalMinutes: form.dailyGoalMinutes,
        displayName: form.displayName || null,
        occupation: form.occupation || null,
        
        // バックエンド用のstep_data形式
        step_data: {
          setup_step: stepData
        }
      }
      
      emit('step-data', data)
    }

    const emitValidation = () => {
      emit('validation-change', {
        isValid: isValid.value,
        errors: { ...errors }
      })
    }

    // ウォッチャー
    watch(() => form, () => {
      validateForm()
      emitStepData()
      emitValidation()
    }, { deep: true })

    watch(() => errors, () => {
      emitValidation()
    }, { deep: true })

    // ライフサイクル
    onMounted(() => {
      // 初期バリデーション
      validateForm()
      emitValidation()
    })

    return {
      form,
      errors,
      examTypes,
      availableSubjects,
      minDate,
      isValid,
      formatMinutes,
      handleExamTypeChange,
      handleCustomExamValidation,
      handleSubmit
    }
  }
}
</script>

<style scoped>
/* カスタムスライダーのスタイル */
input[type="range"] {
  appearance: none;
  height: 6px;
  border-radius: 3px;
  background: #e5e7eb;
  outline: none;
}

input[type="range"]::-webkit-slider-thumb {
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #3b82f6;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

input[type="range"]::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #3b82f6;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>