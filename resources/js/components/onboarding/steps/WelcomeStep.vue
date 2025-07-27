<template>
  <div class="text-center">
    <!-- ウェルカムアイコン -->
    <div class="mb-6">
      <div class="w-20 h-20 mx-auto bg-gradient-to-br from-blue-500 to-blue-700 rounded-full flex items-center justify-center">
        <span class="text-3xl text-white" aria-hidden="true">👋</span>
      </div>
    </div>

    <!-- ウェルカムメッセージ -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-4">
        すたログへようこそ！
      </h2>
      <p class="text-gray-600 leading-relaxed">
        資格学習を効率的に進めるための学習管理アプリです。<br>
        簡単な設定で、あなたの学習をサポートします。
      </p>
    </div>

    <!-- 機能紹介 -->
    <div class="bg-blue-50 rounded-lg p-6 mb-8">
      <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center justify-center">
        <span class="text-xl mr-2" aria-hidden="true">✨</span>
        主な機能
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-800">
        <div class="flex items-start">
          <span class="text-blue-600 mt-0.5 mr-2" aria-hidden="true">📊</span>
          <span>学習時間の記録・可視化</span>
        </div>
        <div class="flex items-start">
          <span class="text-blue-600 mt-0.5 mr-2" aria-hidden="true">🎯</span>
          <span>目標設定とその進捗管理</span>
        </div>
        <div class="flex items-start">
          <span class="text-blue-600 mt-0.5 mr-2" aria-hidden="true">⏰</span>
          <span>ポモドーロタイマー</span>
        </div>
        <div class="flex items-start">
          <span class="text-blue-600 mt-0.5 mr-2" aria-hidden="true">📈</span>
          <span>学習統計とインサイト</span>
        </div>
      </div>
    </div>

    <!-- 所要時間 -->
    <div class="text-sm text-gray-500">
      <span class="inline-flex items-center">
        <span class="mr-1" aria-hidden="true">⏱️</span>
        設定は約2分で完了します
      </span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WelcomeStep',
  setup() {
    // このステップはデータを持たないので、空のデータを emit
    return {}
  }
}
</script>

<style scoped>
/* 特別なスタイルは必要なし、親コンポーネントのスタイルを使用 */
</style>