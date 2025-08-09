/**
 * テキストバリデーションルール定義
 * 
 * プラグイン的に拡張可能なバリデーションルールシステム
 * 各ルールは独立しており、組み合わせて使用可能
 */

export const TEXT_VALIDATION_RULES = {
  // HTMLタグ文字の検出
  html_tags: {
    name: 'html_tags',
    pattern: /[<>]/g,
    message: 'HTMLタグ文字（< >）',
    description: 'HTML要素の開始・終了タグ文字を検出',
    severity: 'error',
    category: 'security'
  },

  // HTMLエンティティ文字の検出  
  html_entities: {
    name: 'html_entities',
    pattern: /[&]/g,
    message: 'HTMLエンティティ文字（&）',
    description: 'HTML文字実体参照で使用されるアンパサンド文字を検出',
    severity: 'error',
    category: 'security'
  },

  // クォート文字の検出
  quotes: {
    name: 'quotes',
    pattern: /["']/g,
    message: 'クォート文字（" \'）',
    description: 'シングル・ダブルクォート文字を検出',
    severity: 'error',
    category: 'security'
  }
}

/**
 * 特定のカテゴリのルールを取得
 * @param {string} category - カテゴリ名
 * @returns {Object} カテゴリに属するルール群
 */
export function getRulesByCategory(category) {
  return Object.values(TEXT_VALIDATION_RULES).filter(rule => rule.category === category)
}

/**
 * 特定の重要度以上のルールを取得
 * @param {string} minSeverity - 最低重要度 ('info', 'warning', 'error')
 * @returns {Object} 指定重要度以上のルール群
 */
export function getRulesBySeverity(minSeverity = 'error') {
  const severityOrder = { info: 0, warning: 1, error: 2 }
  const minLevel = severityOrder[minSeverity] || 2
  
  return Object.values(TEXT_VALIDATION_RULES).filter(rule => 
    severityOrder[rule.severity] >= minLevel
  )
}

/**
 * 将来のビジョン用のバリデーションプリセット
 * XSS対策を重視したセキュリティルール
 */
export const FUTURE_VISION_RULES = [
  TEXT_VALIDATION_RULES.html_tags,
  TEXT_VALIDATION_RULES.html_entities, 
  TEXT_VALIDATION_RULES.quotes
]

/**
 * カスタムルールを追加する関数
 * @param {string} name - ルール名
 * @param {Object} rule - ルール定義
 */
export function addCustomRule(name, rule) {
  const requiredFields = ['pattern', 'message', 'severity', 'category']
  
  for (const field of requiredFields) {
    if (!rule[field]) {
      throw new Error(`カスタムルールに必須フィールド '${field}' がありません`)
    }
  }

  TEXT_VALIDATION_RULES[name] = {
    name,
    description: '',
    ...rule
  }
}

export default TEXT_VALIDATION_RULES