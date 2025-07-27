<template>
  <div class="text-center">
    <!-- 完了アニメーション -->
    <div class="mb-8">
      <div class="relative mx-auto w-24 h-24">
        <!-- 背景サークル -->
        <div class="absolute inset-0 bg-green-100 rounded-full animate-pulse"></div>
        <!-- チェックマークサークル -->
        <div class="relative w-24 h-24 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center animate-bounce-once">
          <svg 
            class="w-12 h-12 text-white animate-draw-check" 
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path 
              stroke-linecap="round" 
              stroke-linejoin="round" 
              stroke-width="3" 
              d="M5 13l4 4L19 7"
              class="check-path"
            />
          </svg>
        </div>
      </div>
    </div>

    <!-- 完了メッセージ -->
    <div class="mb-8">
      <h2 class="text-3xl font-bold text-gray-900 mb-4">
        🎉 セットアップ完了！
      </h2>
      <p class="text-lg text-gray-600 leading-relaxed">
        おめでとうございます！<br>
        すたログの初期設定が完了しました。
      </p>
    </div>

    <!-- 設定内容サマリー -->
    <div class="bg-gradient-to-r from-blue-50 to-green-50 rounded-lg p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center justify-center">
        <span class="text-xl mr-2" aria-hidden="true">📋</span>
        設定内容
      </h3>
      <div class="space-y-3 text-sm">
        <div class="flex justify-between items-center">
          <span class="text-gray-600">受験予定資格:</span>
          <span class="font-medium text-gray-900">{{ getExamTypeName(settings.examType) }}</span>
        </div>
        <div v-if="settings.examDate" class="flex justify-between items-center">
          <span class="text-gray-600">試験予定日:</span>
          <span class="font-medium text-gray-900">{{ formatDate(settings.examDate) }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-gray-600">1日の目標学習時間:</span>
          <span class="font-medium text-gray-900">{{ formatMinutes(settings.dailyGoalMinutes) }}</span>
        </div>
        <div v-if="settings.subjects && settings.subjects.length > 0" class="flex justify-between items-start">
          <span class="text-gray-600">重点学習分野:</span>
          <div class="text-right">
            <div v-for="subject in settings.subjects.slice(0, 3)" :key="subject" class="font-medium text-gray-900">
              {{ getSubjectName(subject) }}
            </div>
            <div v-if="settings.subjects.length > 3" class="text-xs text-gray-500 mt-1">
              他{{ settings.subjects.length - 3 }}分野
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 次のステップガイド -->
    <div class="bg-white border-2 border-blue-200 rounded-lg p-6 mb-8">
      <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center justify-center">
        <span class="text-xl mr-2" aria-hidden="true">🚀</span>
        さあ、学習を始めましょう！
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="flex items-start p-3 bg-blue-50 rounded-lg">
          <span class="text-blue-600 mt-0.5 mr-3 text-lg" aria-hidden="true">1️⃣</span>
          <div>
            <div class="font-medium text-blue-900">学習を記録</div>
            <div class="text-blue-700 mt-1">「学習開始」ボタンから最初の学習セッションを始めてみましょう</div>
          </div>
        </div>
        <div class="flex items-start p-3 bg-green-50 rounded-lg">
          <span class="text-green-600 mt-0.5 mr-3 text-lg" aria-hidden="true">2️⃣</span>
          <div>
            <div class="font-medium text-green-900">目標を確認</div>
            <div class="text-green-700 mt-1">ダッシュボードで今日の学習目標の進捗をチェック</div>
          </div>
        </div>
        <div class="flex items-start p-3 bg-purple-50 rounded-lg">
          <span class="text-purple-600 mt-0.5 mr-3 text-lg" aria-hidden="true">3️⃣</span>
          <div>
            <div class="font-medium text-purple-900">統計を活用</div>
            <div class="text-purple-700 mt-1">継続的に学習データを蓄積して、学習パターンを分析</div>
          </div>
        </div>
        <div class="flex items-start p-3 bg-orange-50 rounded-lg">
          <span class="text-orange-600 mt-0.5 mr-3 text-lg" aria-hidden="true">4️⃣</span>
          <div>
            <div class="font-medium text-orange-900">設定の調整</div>
            <div class="text-orange-700 mt-1">必要に応じて設定画面から目標や分野を変更</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 励ましメッセージ -->
    <div class="bg-gradient-to-r from-green-400 via-blue-500 to-purple-600 p-1 rounded-lg mb-6">
      <div class="bg-white rounded-md p-4">
        <div class="text-lg font-medium text-gray-900 mb-2">
          ✨ 継続は力なり
        </div>
        <p class="text-gray-600 text-sm">
          小さな一歩でも、毎日続けることで大きな成果に繋がります。<br>
          すたログがあなたの学習をサポートします！
        </p>
      </div>
    </div>

    <!-- アクセシビリティ情報 -->
    <div class="text-xs text-gray-500">
      <p>ヒント: この画面は自動的に閉じられ、ダッシュボードが表示されます</p>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'
import { examTypeNames, subjectNames, getExamTypeName, getSubjectName } from '../../../utils/examConfig'

export default {
  name: 'CompletionStep',
  props: {
    settings: {
      type: Object,
      default: () => ({})
    }
  },
  setup(props) {

    // メソッド

    const formatDate = (dateString) => {
      if (!dateString) return '未設定';
      
      const date = new Date(dateString)
      
      if (isNaN(date.getTime())) {
        console.warn('Invalid date string:', dateString);
        return '日付形式エラー';
      }
      
      return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    }

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

    return {
      getExamTypeName,
      getSubjectName,
      formatDate,
      formatMinutes
    }
  }
}
</script>

<style scoped>
/* 一回だけのバウンスアニメーション */
@keyframes bounce-once {
  0%, 20%, 53%, 80%, 100% {
    transform: translate3d(0, 0, 0);
  }
  40%, 43% {
    transform: translate3d(0, -10px, 0);
  }
  70% {
    transform: translate3d(0, -5px, 0);
  }
  90% {
    transform: translate3d(0, -2px, 0);
  }
}

.animate-bounce-once {
  animation: bounce-once 1s ease-in-out;
}

/* チェックマーク描画アニメーション */
@keyframes draw-check {
  0% {
    stroke-dasharray: 0 50;
    stroke-dashoffset: 0;
  }
  50% {
    stroke-dasharray: 25 50;
    stroke-dashoffset: 0;
  }
  100% {
    stroke-dasharray: 25 50;
    stroke-dashoffset: -25;
  }
}

.animate-draw-check .check-path {
  stroke-dasharray: 50;
  stroke-dashoffset: 50;
  animation: draw-check 1s ease-in-out 0.5s forwards;
}

/* パルスアニメーションの調整 */
.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(1.05);
  }
}

/* グラデーションボーダーの効果 */
.bg-gradient-to-r {
  animation: gradientShift 3s ease-in-out infinite;
}

@keyframes gradientShift {
  0%, 100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}
</style>