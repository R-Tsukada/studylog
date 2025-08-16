/**
 * デバウンス機能
 * localStorage 書き込み頻度を制御
 * Issue #62 対応: パフォーマンス最適化
 */
export function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func.apply(this, args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}