/**
 * 学習分野データの統一管理サービス
 * ダッシュボードとポモドーロタイマーで共通利用
 */
export default class SubjectAreaService {
  constructor() {
    this.cache = new Map()
    this.cacheTimeout = 5 * 60 * 1000 // 5分間キャッシュ
  }

  /**
   * 統一されたフォーマットで学習分野を取得
   */
  async getAllSubjectAreas() {
    try {
      const cacheKey = 'all_subject_areas'
      const cached = this._getFromCache(cacheKey)
      if (cached) {
        return cached
      }

      const response = await fetch('/api/user/subject-areas', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Accept': 'application/json'
        }
      })

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        return this._createErrorResponse(response.status, errorData.message || 'API request failed')
      }

      const data = await response.json()
      
      if (!data.success) {
        return this._createErrorResponse(500, data.message || 'API returned error')
      }

      const subjectAreas = data.subject_areas || []
      
      // 統一フォーマットに変換
      const normalizedData = subjectAreas.map(area => ({
        id: area.id,
        name: area.name,
        examTypeId: area.exam_type_id,
        examTypeName: area.exam_type_name,
        isSystem: area.is_system,
        userId: area.user_id
      }))

      // 試験タイプ別にグループ化
      const groupedByExamType = normalizedData.reduce((groups, area) => {
        const examTypeName = area.examTypeName
        if (!groups[examTypeName]) {
          groups[examTypeName] = []
        }
        groups[examTypeName].push(area)
        return groups
      }, {})

      const result = {
        success: true,
        data: {
          subjectAreas: normalizedData,
          groupedByExamType: groupedByExamType
        }
      }

      this._setCache(cacheKey, result)
      return result

    } catch (error) {
      return this._createErrorResponse('NETWORK_ERROR', 'ネットワークエラーが発生しました', error.message)
    }
  }

  /**
   * Dashboard用の階層構造フォーマットで取得
   */
  async getSubjectAreasForDashboard() {
    try {
      const result = await this.getAllSubjectAreas()
      
      if (!result.success) {
        return result
      }

      const grouped = result.data.groupedByExamType
      const dashboardFormat = Object.keys(grouped).map((examTypeName, index) => {
        const subjects = grouped[examTypeName]
        const examTypeId = subjects.length > 0 ? subjects[0].examTypeId : index + 1

        return {
          id: examTypeId,
          name: examTypeName,
          subject_areas: subjects.map(subject => ({
            id: subject.id,
            name: subject.name
          }))
        }
      })

      return {
        success: true,
        data: dashboardFormat
      }

    } catch (error) {
      return this._createErrorResponse('PROCESSING_ERROR', 'データ変換中にエラーが発生しました', error.message)
    }
  }

  /**
   * Pomodoro用のフラットリストフォーマットで取得
   */
  async getSubjectAreasForPomodoro() {
    try {
      const result = await this.getAllSubjectAreas()
      
      if (!result.success) {
        return result
      }

      const pomodoroFormat = result.data.subjectAreas.map(subject => ({
        id: subject.id,
        name: subject.name
      }))

      return {
        success: true,
        data: pomodoroFormat
      }

    } catch (error) {
      return this._createErrorResponse('PROCESSING_ERROR', 'データ変換中にエラーが発生しました', error.message)
    }
  }

  /**
   * キャッシュをクリア
   */
  clearCache() {
    this.cache.clear()
  }

  /**
   * キャッシュから取得
   */
  _getFromCache(key) {
    const cached = this.cache.get(key)
    if (!cached) {
      return null
    }

    const now = Date.now()
    if (now - cached.timestamp > this.cacheTimeout) {
      this.cache.delete(key)
      return null
    }

    return cached.data
  }

  /**
   * キャッシュに保存
   */
  _setCache(key, data) {
    this.cache.set(key, {
      data: data,
      timestamp: Date.now()
    })
  }

  /**
   * エラーレスポンス生成
   */
  _createErrorResponse(status, message, originalError = null) {
    let errorCode = 'UNKNOWN_ERROR'
    
    if (typeof status === 'number') {
      if (status === 401) {
        errorCode = 'AUTHENTICATION_REQUIRED'
      } else if (status === 403) {
        errorCode = 'ACCESS_FORBIDDEN'
      } else if (status === 404) {
        errorCode = 'NOT_FOUND'
      } else if (status >= 500) {
        errorCode = 'SERVER_ERROR'
      }
    } else {
      errorCode = status // 文字列の場合はそのまま使用
    }

    const errorResponse = {
      success: false,
      error: {
        code: errorCode,
        message: message
      },
      data: null
    }

    if (typeof status === 'number') {
      errorResponse.error.statusCode = status
    }

    if (originalError) {
      errorResponse.error.originalError = originalError
    }

    // JSONパースエラーの場合
    if (originalError && originalError.includes('JSON')) {
      errorResponse.error.code = 'PARSE_ERROR'
    }

    return errorResponse
  }
}