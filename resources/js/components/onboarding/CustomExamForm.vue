<template>
  <div class="space-y-4">
    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <span class="text-blue-400" aria-hidden="true">💡</span>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-blue-800">
            カスタム試験を作成
          </h3>
          <p class="mt-1 text-sm text-blue-700">
            あなた独自の試験や資格を設定できます
          </p>
        </div>
      </div>
    </div>

    <!-- 試験名 -->
    <div>
      <label for="custom-exam-name" class="block text-sm font-medium text-gray-700 mb-2">
        試験名 <span class="text-red-500">*</span>
      </label>
      <input
        id="custom-exam-name"
        v-model="form.name"
        type="text"
        maxlength="255"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        :class="{ 'border-red-500': errors.name }"
        placeholder="例: 情報セキュリティマネジメント試験"
        required
        aria-describedby="custom-exam-name-error"
        @keydown.enter.prevent
      />
      <p 
        v-if="errors.name" 
        id="custom-exam-name-error" 
        class="mt-1 text-sm text-red-600"
        role="alert"
      >
        {{ errors.name }}
      </p>
    </div>

    <!-- 試験説明 -->
    <div>
      <label for="custom-exam-description" class="block text-sm font-medium text-gray-700 mb-2">
        試験説明（任意）
      </label>
      <textarea
        id="custom-exam-description"
        v-model="form.description"
        rows="3"
        maxlength="1000"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        :class="{ 'border-red-500': errors.description }"
        placeholder="例: セキュリティ関連の資格試験"
        aria-describedby="custom-exam-description-help custom-exam-description-error"
        @keydown.enter.prevent
      />
      <p id="custom-exam-description-help" class="mt-1 text-sm text-gray-500">
        {{ form.description.length }}/1000文字
      </p>
      <p 
        v-if="errors.description" 
        id="custom-exam-description-error" 
        class="mt-1 text-sm text-red-600"
        role="alert"
      >
        {{ errors.description }}
      </p>
    </div>

    <!-- カラー選択 -->
    <div>
      <label for="custom-exam-color" class="block text-sm font-medium text-gray-700 mb-2">
        テーマカラー
      </label>
      <div class="flex items-center space-x-3">
        <input
          id="custom-exam-color"
          v-model="form.color"
          type="color"
          class="h-10 w-16 border border-gray-300 rounded-md cursor-pointer"
          aria-describedby="custom-exam-color-help"
        />
        <div class="flex-1">
          <input
            v-model="form.color"
            type="text"
            pattern="^#[0-9A-Fa-f]{6}$"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :class="{ 'border-red-500': errors.color }"
            placeholder="#3B82F6"
            aria-describedby="custom-exam-color-error"
            @keydown.enter.prevent
          />
        </div>
      </div>
      <p id="custom-exam-color-help" class="mt-1 text-sm text-gray-500">
        学習統計やダッシュボードで使用されます
      </p>
      <p 
        v-if="errors.color" 
        id="custom-exam-color-error" 
        class="mt-1 text-sm text-red-600"
        role="alert"
      >
        {{ errors.color }}
      </p>
    </div>

    <!-- 学習分野 -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        学習分野（任意）
      </label>
      <div class="space-y-3">
        <!-- 既存の学習分野リスト -->
        <div v-if="form.subjects.length > 0" class="space-y-2">
          <div
            v-for="(subject, index) in form.subjects"
            :key="index"
            class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
          >
            <div class="flex-1">
              <input
                v-model="subject.name"
                type="text"
                maxlength="255"
                class="w-full px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                placeholder="例: データ構造とアルゴリズム"
                @keydown.enter.prevent
              />
            </div>
            <button
              type="button"
              @click="removeSubject(index)"
              class="ml-3 text-red-600 hover:text-red-800 transition-colors"
              title="削除"
            >
              🗑️
            </button>
          </div>
        </div>
        
        <!-- 学習分野追加ボタン -->
        <button
          type="button"
          @click="addSubject"
          class="w-full px-3 py-2 border-2 border-dashed border-gray-300 rounded-md text-gray-600 hover:border-blue-400 hover:text-blue-600 transition-colors"
        >
          + 学習分野を追加
        </button>
        
        <p class="text-sm text-gray-500">
          学習進捗を詳細に追跡するための分野を設定できます（最大10個）
        </p>
      </div>
      <p 
        v-if="errors.subjects" 
        class="mt-1 text-sm text-red-600"
        role="alert"
      >
        {{ errors.subjects }}
      </p>
    </div>

    <!-- メモ・ノート -->
    <div>
      <label for="custom-exam-notes" class="block text-sm font-medium text-gray-700 mb-2">
        メモ・ノート（任意）
      </label>
      <textarea
        id="custom-exam-notes"
        v-model="form.notes"
        rows="3"
        maxlength="2000"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        :class="{ 'border-red-500': errors.notes }"
        placeholder="例: スコア目標: 700点以上、受験料: 7,500円"
        aria-describedby="custom-exam-notes-help custom-exam-notes-error"
        @keydown.enter.prevent
      />
      <p id="custom-exam-notes-help" class="mt-1 text-sm text-gray-500">
        目標スコアや受験料などの覚書に使用できます（{{ form.notes.length }}/2000文字）
      </p>
      <p 
        v-if="errors.notes" 
        id="custom-exam-notes-error" 
        class="mt-1 text-sm text-red-600"
        role="alert"
      >
        {{ errors.notes }}
      </p>
    </div>
  </div>
</template>

<script>
import { reactive, computed, watch } from 'vue'

export default {
  name: 'CustomExamForm',
  props: {
    modelValue: {
      type: Object,
      default: () => ({})
    }
  },
  emits: ['update:modelValue', 'validation-change'],
  setup(props, { emit }) {
    // フォームデータ
    const form = reactive({
      name: props.modelValue.name || '',
      description: props.modelValue.description || '',
      color: props.modelValue.color || '#9333EA',
      notes: props.modelValue.notes || '',
      subjects: props.modelValue.subjects || []
    })

    // エラー状態
    const errors = reactive({})

    // 計算プロパティ
    const isValid = computed(() => {
      return Object.keys(errors).length === 0 && form.name.trim() !== ''
    })

    // バリデーション関数
    const validateForm = () => {
      // エラーをクリア
      Object.keys(errors).forEach(key => delete errors[key])

      // 試験名チェック（必須）
      if (!form.name.trim()) {
        errors.name = '試験名を入力してください'
      } else if (form.name.length > 255) {
        errors.name = '試験名は255文字以内で入力してください'
      }

      // 説明チェック
      if (form.description && form.description.length > 1000) {
        errors.description = '試験説明は1000文字以内で入力してください'
      }

      // カラーコードチェック
      const colorPattern = /^#[0-9A-Fa-f]{6}$/
      if (form.color && !colorPattern.test(form.color)) {
        errors.color = '有効なカラーコードを入力してください（例: #3B82F6）'
      }

      // ノートチェック
      if (form.notes && form.notes.length > 2000) {
        errors.notes = 'メモは2000文字以内で入力してください'
      }

      // 学習分野チェック（設定値から取得）
      const maxCustomSubjects = 10 // TODO: config/exams.phpの設定値を使用したい
      if (form.subjects.length > maxCustomSubjects) {
        errors.subjects = `学習分野は${maxCustomSubjects}個まで登録できます`
      }

      // 各学習分野の名前チェック
      for (let i = 0; i < form.subjects.length; i++) {
        if (!form.subjects[i].name || form.subjects[i].name.trim() === '') {
          errors.subjects = '学習分野名を入力してください'
          break
        }
        if (form.subjects[i].name.length > 255) {
          errors.subjects = '学習分野名は255文字以内で入力してください'
          break
        }
      }

      return Object.keys(errors).length === 0
    }

    // フォームデータの更新を親に通知
    const emitUpdate = () => {
      const data = {
        name: form.name,
        description: form.description || null,
        color: form.color,
        notes: form.notes || null,
        subjects: form.subjects.filter(subject => subject.name.trim() !== '')
      }
      emit('update:modelValue', data)
    }

    // バリデーション状態を親に通知
    const emitValidation = () => {
      emit('validation-change', {
        isValid: isValid.value,
        errors: { ...errors }
      })
    }

    // ウォッチャー
    watch(() => form, () => {
      validateForm()
      emitUpdate()
      emitValidation()
    }, { deep: true })

    // 学習分野管理メソッド
    const addSubject = () => {
      const maxCustomSubjects = 10 // TODO: config/exams.phpの設定値を使用したい
      if (form.subjects.length < maxCustomSubjects) {
        form.subjects.push({ name: '' })
      }
    }

    const removeSubject = (index) => {
      form.subjects.splice(index, 1)
    }

    // 初期バリデーション
    validateForm()
    emitValidation()

    return {
      form,
      errors,
      isValid,
      validateForm,
      addSubject,
      removeSubject
    }
  }
}
</script>

<style scoped>
/* カラーピッカーのスタイル調整 */
input[type="color"] {
  -webkit-appearance: none;
  border: none;
  cursor: pointer;
}

input[type="color"]::-webkit-color-swatch-wrapper {
  padding: 0;
}

input[type="color"]::-webkit-color-swatch {
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
}
</style>