import { describe, test, expect, beforeEach, jest } from '@jest/globals'

// グローバルfetchのモック
global.fetch = jest.fn()

describe('SubjectAreaService TDD実装テスト', () => {
  let SubjectAreaService

  beforeEach(() => {
    // 各テスト前にモックをクリア
    fetch.mockClear()
    
    // テストごとにモジュールを新規取得（静的な状態を防ぐため）
    jest.resetModules()
    
    // SubjectAreaServiceは実装後にimportする
    try {
      SubjectAreaService = require('../../../resources/js/services/SubjectAreaService.js').default
    } catch (error) {
      // まだ実装されていない場合はundefinedのまま
      SubjectAreaService = undefined
    }
  })

  describe('クラスの基本構造', () => {
    test('SubjectAreaServiceクラスが存在する', () => {
      expect(SubjectAreaService).toBeDefined()
      expect(typeof SubjectAreaService).toBe('function')
    })

    test('インスタンスが作成できる', () => {
      const service = new SubjectAreaService()
      expect(service).toBeInstanceOf(SubjectAreaService)
    })
  })

  describe('統一されたデータ取得 - getAllSubjectAreas', () => {
    test('正常系: 統一されたフォーマットで学習分野を取得する', async () => {
      // API応答のモック（/api/user/subject-areas）
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: true,
            user_id: null
          },
          {
            id: 2,
            name: 'テスト設計技法',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: false,
            user_id: 1
          },
          {
            id: 3,
            name: 'EC2基礎',
            exam_type_id: 2,
            exam_type_name: 'AWS SAA',
            is_system: true,
            user_id: null
          }
        ]
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValueOnce(mockApiResponse)
      })

      const service = new SubjectAreaService()
      const result = await service.getAllSubjectAreas()

      // 期待される統一フォーマット
      expect(result).toEqual({
        success: true,
        data: {
          subjectAreas: [
            {
              id: 1,
              name: 'データベース基礎',
              examTypeId: 1,
              examTypeName: 'JSTQB',
              isSystem: true,
              userId: null
            },
            {
              id: 2,
              name: 'テスト設計技法',
              examTypeId: 1,
              examTypeName: 'JSTQB',
              isSystem: false,
              userId: 1
            },
            {
              id: 3,
              name: 'EC2基礎',
              examTypeId: 2,
              examTypeName: 'AWS SAA',
              isSystem: true,
              userId: null
            }
          ],
          groupedByExamType: {
            'JSTQB': [
              {
                id: 1,
                name: 'データベース基礎',
                examTypeId: 1,
                examTypeName: 'JSTQB',
                isSystem: true,
                userId: null
              },
              {
                id: 2,
                name: 'テスト設計技法',
                examTypeId: 1,
                examTypeName: 'JSTQB',
                isSystem: false,
                userId: 1
              }
            ],
            'AWS SAA': [
              {
                id: 3,
                name: 'EC2基礎',
                examTypeId: 2,
                examTypeName: 'AWS SAA',
                isSystem: true,
                userId: null
              }
            ]
          }
        }
      })

      // APIが正しく呼ばれたかチェック
      expect(fetch).toHaveBeenCalledTimes(1)
      expect(fetch).toHaveBeenCalledWith('/api/user/subject-areas', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Accept': 'application/json'
        }
      })
    })

    test('異常系: API認証エラーの処理', async () => {
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: jest.fn().mockResolvedValueOnce({
          success: false,
          message: '認証が必要です'
        })
      })

      const service = new SubjectAreaService()
      const result = await service.getAllSubjectAreas()

      expect(result).toEqual({
        success: false,
        error: {
          code: 'AUTHENTICATION_REQUIRED',
          message: '認証が必要です',
          statusCode: 401
        },
        data: null
      })
    })

    test('異常系: ネットワークエラーの処理', async () => {
      fetch.mockRejectedValueOnce(new Error('Network Error'))

      const service = new SubjectAreaService()
      const result = await service.getAllSubjectAreas()

      expect(result).toEqual({
        success: false,
        error: {
          code: 'NETWORK_ERROR',
          message: 'ネットワークエラーが発生しました',
          originalError: 'Network Error'
        },
        data: null
      })
    })

    test('異常系: 空のレスポンスの処理', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: []
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValueOnce(mockApiResponse)
      })

      const service = new SubjectAreaService()
      const result = await service.getAllSubjectAreas()

      expect(result).toEqual({
        success: true,
        data: {
          subjectAreas: [],
          groupedByExamType: {}
        }
      })
    })
  })

  describe('Dashboardフォーマット変換 - getSubjectAreasForDashboard', () => {
    test('Dashboard用の階層構造にフォーマット変換する', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: true,
            user_id: null
          },
          {
            id: 2,
            name: 'テスト設計技法',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: false,
            user_id: 1
          }
        ]
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValueOnce(mockApiResponse)
      })

      const service = new SubjectAreaService()
      const result = await service.getSubjectAreasForDashboard()

      // Dashboard.vueで期待される形式
      expect(result).toEqual({
        success: true,
        data: [
          {
            id: 1,
            name: 'JSTQB',
            subject_areas: [
              {
                id: 1,
                name: 'データベース基礎'
              },
              {
                id: 2,
                name: 'テスト設計技法'
              }
            ]
          }
        ]
      })
    })
  })

  describe('Pomodoroフォーマット変換 - getSubjectAreasForPomodoro', () => {
    test('Pomodoro用のフラットリストにフォーマット変換する', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: true,
            user_id: null
          },
          {
            id: 2,
            name: 'テスト設計技法',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: false,
            user_id: 1
          }
        ]
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValueOnce(mockApiResponse)
      })

      const service = new SubjectAreaService()
      const result = await service.getSubjectAreasForPomodoro()

      // PomodoroTimer.vueで期待される形式
      expect(result).toEqual({
        success: true,
        data: [
          {
            id: 1,
            name: 'データベース基礎'
          },
          {
            id: 2,
            name: 'テスト設計技法'
          }
        ]
      })
    })
  })

  describe('キャッシュ機能', () => {
    test('同一リクエストが連続した場合はキャッシュを使用する', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB',
            is_system: true,
            user_id: null
          }
        ]
      }

      fetch.mockResolvedValue({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValue(mockApiResponse)
      })

      const service = new SubjectAreaService()
      
      // 1回目のリクエスト
      await service.getAllSubjectAreas()
      
      // 2回目のリクエスト（キャッシュを使用すべき）
      await service.getAllSubjectAreas()

      // fetchは1回だけ呼ばれるはず
      expect(fetch).toHaveBeenCalledTimes(1)
    })

    test('キャッシュを明示的にクリアできる', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: []
      }

      fetch.mockResolvedValue({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValue(mockApiResponse)
      })

      const service = new SubjectAreaService()
      
      // 1回目のリクエスト
      await service.getAllSubjectAreas()
      
      // キャッシュクリア
      service.clearCache()
      
      // 2回目のリクエスト（新規APIコール）
      await service.getAllSubjectAreas()

      // fetchは2回呼ばれるはず
      expect(fetch).toHaveBeenCalledTimes(2)
    })
  })

  describe('エラーハンドリングの詳細', () => {
    test('500 Internal Server Errorの処理', async () => {
      fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: jest.fn().mockResolvedValueOnce({
          success: false,
          message: 'サーバーエラー'
        })
      })

      const service = new SubjectAreaService()
      const result = await service.getAllSubjectAreas()

      expect(result.success).toBe(false)
      expect(result.error.code).toBe('SERVER_ERROR')
      expect(result.error.statusCode).toBe(500)
    })

    test('不正なJSONレスポンスの処理', async () => {
      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: jest.fn().mockRejectedValueOnce(new Error('Invalid JSON'))
      })

      const service = new SubjectAreaService()
      const result = await service.getAllSubjectAreas()

      expect(result.success).toBe(false)
      expect(result.error.code).toBe('PARSE_ERROR')
    })
  })
})