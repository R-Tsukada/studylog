/**
 * 汎用テキストバリデーターユーティリティ
 * 
 * モジュラー設計により、様々なテキスト入力に対して
 * 柔軟で拡張性の高いバリデーション機能を提供
 */

import { TEXT_VALIDATION_RULES } from './validationRules.js'

/**
 * テキストバリデーター クラス
 */
export class TextValidator {
  constructor(rules = []) {
    this.rules = rules
    this.cache = new Map() // パフォーマンス向上のためのキャッシュ
  }

  /**
   * テキストをバリデーションする
   * @param {string} text - 検証対象のテキスト
   * @returns {Object} バリデーション結果
   */
  validate(text) {
    // 入力の前処理
    if (!text || typeof text !== 'string') {
      return {
        isValid: true,
        errors: [],
        summary: {
          totalErrors: 0,
          totalInvalidChars: 0,
          affectedRules: []
        }
      }
    }

    // キャッシュチェック（同じテキストなら再利用）
    const cacheKey = this._generateCacheKey(text)
    if (this.cache.has(cacheKey)) {
      return this.cache.get(cacheKey)
    }

    const errors = []
    let totalInvalidChars = 0

    // 各ルールに対してテキストをチェック
    for (const rule of this.rules) {
      const matches = this._findMatches(text, rule)
      if (matches.length > 0) {
        const error = {
          rule: rule.name,
          message: rule.message,
          description: rule.description,
          severity: rule.severity,
          category: rule.category,
          matches,
          count: matches.length,
          positions: this._getMatchPositions(matches)
        }
        
        errors.push(error)
        totalInvalidChars += matches.length
      }
    }

    const result = {
      isValid: errors.length === 0,
      errors,
      summary: {
        totalErrors: errors.length,
        totalInvalidChars,
        affectedRules: errors.map(err => err.rule),
        severityBreakdown: this._getSeverityBreakdown(errors)
      }
    }

    // 結果をキャッシュ（メモリ使用量制限）
    if (this.cache.size >= 100) {
      const firstKey = this.cache.keys().next().value
      this.cache.delete(firstKey)
    }
    this.cache.set(cacheKey, result)

    return result
  }

  /**
   * ユーザーフレンドリーなエラーメッセージを生成
   * @param {Object} validationResult - validateメソッドの結果
   * @returns {string} 表示用エラーメッセージ
   */
  getDisplayMessage(validationResult) {
    if (validationResult.isValid) {
      return ''
    }

    const { errors } = validationResult
    const errorMessages = [...new Set(errors.map(err => err.message))]

    if (errorMessages.length === 1) {
      return `${errorMessages[0]}は使用できません`
    } else {
      return `以下の文字は使用できません: ${errorMessages.join('、')}`
    }
  }

  /**
   * アクセシビリティ用の説明文を生成
   * @param {Object} validationResult - validateメソッドの結果  
   * @returns {string} スクリーンリーダー用説明文
   */
  getAriaDescription(validationResult) {
    if (validationResult.isValid) {
      return '入力内容に問題ありません'
    }

    const { totalInvalidChars, totalErrors } = validationResult.summary
    return `${totalInvalidChars}個の無効な文字が${totalErrors}種類のルールに違反しています`
  }

  /**
   * 無効な文字を除去したテキストを返す
   * @param {string} text - 元のテキスト
   * @returns {string} サニタイズされたテキスト
   */
  sanitize(text) {
    if (!text || typeof text !== 'string') {
      return ''
    }

    let sanitized = text
    for (const rule of this.rules) {
      sanitized = sanitized.replace(rule.pattern, '')
    }

    return sanitized
  }

  /**
   * 特定の文字が入力された場合のブロック用配列を生成
   * @returns {Array} ブロック対象文字の配列
   */
  getBlockedCharacters() {
    const chars = new Set()
    
    for (const rule of this.rules) {
      // 単純な文字パターンから文字を抽出
      const pattern = rule.pattern.source || rule.pattern.toString()
      const simpleChars = pattern.match(/[\w\s<>&"']/g)
      
      if (simpleChars) {
        simpleChars.forEach(char => chars.add(char))
      }
    }

    return Array.from(chars)
  }

  /**
   * プライベートメソッド: テキストとルールのマッチングを実行
   */
  _findMatches(text, rule) {
    const matches = []
    let match

    // グローバルフラグを強制的に設定
    const globalPattern = new RegExp(rule.pattern.source, 'g')
    
    while ((match = globalPattern.exec(text)) !== null) {
      matches.push({
        value: match[0],
        index: match.index,
        length: match[0].length
      })
    }

    return matches
  }

  /**
   * プライベートメソッド: マッチ位置の取得
   */
  _getMatchPositions(matches) {
    return matches.map(match => ({
      start: match.index,
      end: match.index + match.length - 1,
      character: match.value
    }))
  }

  /**
   * プライベートメソッド: 重要度別の集計
   */
  _getSeverityBreakdown(errors) {
    const breakdown = { info: 0, warning: 0, error: 0 }
    errors.forEach(error => {
      breakdown[error.severity] = (breakdown[error.severity] || 0) + 1
    })
    return breakdown
  }

  /**
   * プライベートメソッド: キャッシュキー生成
   */
  _generateCacheKey(text) {
    return `${text}_${this.rules.map(r => r.name).join('_')}`
  }
}

/**
 * 将来のビジョン用バリデーターのファクトリー関数
 * @returns {TextValidator} 将来のビジョン専用バリデーター
 */
export function createFutureVisionValidator() {
  const rules = [
    TEXT_VALIDATION_RULES.html_tags,
    TEXT_VALIDATION_RULES.html_entities,
    TEXT_VALIDATION_RULES.quotes
  ]
  
  return new TextValidator(rules)
}

/**
 * カスタムルールセットでバリデーターを作成
 * @param {Array} ruleNames - 使用するルール名の配列
 * @returns {TextValidator} カスタムバリデーター
 */
export function createCustomValidator(ruleNames) {
  const rules = ruleNames.map(name => {
    const rule = TEXT_VALIDATION_RULES[name]
    if (!rule) {
      throw new Error(`不明なバリデーションルール: ${name}`)
    }
    return rule
  })
  
  return new TextValidator(rules)
}

export default TextValidator