import { describe, test, expect, beforeEach, jest } from '@jest/globals'

// グローバルfetchのモック
global.fetch = jest.fn()

describe('SubjectAreaService 統合テスト', () => {
  let SubjectAreaService

  beforeEach(() => {
    fetch.mockClear()
    jest.resetModules()
    
    try {
      SubjectAreaService = require('../../../resources/js/services/SubjectAreaService.js').default
    } catch (error) {
      SubjectAreaService = undefined
    }
  })

  describe('Dashboard.vue との統合', () => {
    test('Dashboard用フォーマットが期待通りの構造になる', async () => {
      // 実際のAPIレスポンスに近いモックデータ
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB Foundation Level',
            is_system: true,
            user_id: null
          },
          {
            id: 2,
            name: 'テスト設計技法',
            exam_type_id: 1,
            exam_type_name: 'JSTQB Foundation Level',
            is_system: false,
            user_id: 1
          },
          {
            id: 3,
            name: 'EC2基礎',
            exam_type_id: 2,
            exam_type_name: 'AWS Solutions Architect Associate',
            is_system: true,
            user_id: null
          },
          {
            id: 4,
            name: 'VPC設計',
            exam_type_id: 2,
            exam_type_name: 'AWS Solutions Architect Associate',
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

      expect(result.success).toBe(true)
      expect(result.data).toHaveLength(2) // 2つの試験タイプ
      
      // JSTQB Foundation Levelグループの確認
      const jstqbGroup = result.data.find(examType => examType.name === 'JSTQB Foundation Level')
      expect(jstqbGroup).toBeDefined()
      expect(jstqbGroup.id).toBe(1)
      expect(jstqbGroup.subject_areas).toHaveLength(2)
      expect(jstqbGroup.subject_areas[0]).toEqual({
        id: 1,
        name: 'データベース基礎'
      })
      expect(jstqbGroup.subject_areas[1]).toEqual({
        id: 2,
        name: 'テスト設計技法'
      })

      // AWS SAAグループの確認
      const awsGroup = result.data.find(examType => examType.name === 'AWS Solutions Architect Associate')
      expect(awsGroup).toBeDefined()
      expect(awsGroup.id).toBe(2)
      expect(awsGroup.subject_areas).toHaveLength(2)
      expect(awsGroup.subject_areas[0]).toEqual({
        id: 3,
        name: 'EC2基礎'
      })
      expect(awsGroup.subject_areas[1]).toEqual({
        id: 4,
        name: 'VPC設計'
      })
    })

    test('空のデータでもDashboard用フォーマットが正しく処理される', async () => {
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
      const result = await service.getSubjectAreasForDashboard()

      expect(result.success).toBe(true)
      expect(result.data).toEqual([])
    })
  })

  describe('PomodoroTimer.vue との統合', () => {
    test('Pomodoro用フォーマットが期待通りの構造になる', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB Foundation Level',
            is_system: true,
            user_id: null
          },
          {
            id: 2,
            name: 'テスト設計技法',
            exam_type_id: 1,
            exam_type_name: 'JSTQB Foundation Level',
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

      expect(result.success).toBe(true)
      expect(result.data).toEqual([
        {
          id: 1,
          name: 'データベース基礎'
        },
        {
          id: 2,
          name: 'テスト設計技法'
        }
      ])
    })

    test('空のデータでもPomodoro用フォーマットが正しく処理される', async () => {
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
      const result = await service.getSubjectAreasForPomodoro()

      expect(result.success).toBe(true)
      expect(result.data).toEqual([])
    })
  })

  describe('データ整合性テスト', () => {
    test('同じサービスインスタンスから取得したDashboardとPomodoroのデータに整合性がある', async () => {
      const mockApiResponse = {
        success: true,
        subject_areas: [
          {
            id: 1,
            name: 'データベース基礎',
            exam_type_id: 1,
            exam_type_name: 'JSTQB Foundation Level',
            is_system: true,
            user_id: null
          },
          {
            id: 2,
            name: 'EC2基礎',
            exam_type_id: 2,
            exam_type_name: 'AWS SAA',
            is_system: true,
            user_id: null
          }
        ]
      }

      // 2回のAPIコール（キャッシュなし）をモック
      fetch
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: jest.fn().mockResolvedValueOnce(mockApiResponse)
        })
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: jest.fn().mockResolvedValueOnce(mockApiResponse)
        })

      const service = new SubjectAreaService()
      
      // キャッシュをクリアして別々にAPIコールさせる
      service.clearCache()
      const dashboardResult = await service.getSubjectAreasForDashboard()
      service.clearCache()
      const pomodoroResult = await service.getSubjectAreasForPomodoro()

      // 両方とも成功
      expect(dashboardResult.success).toBe(true)
      expect(pomodoroResult.success).toBe(true)

      // Dashboard形式からsubject_areasを抽出
      const dashboardSubjects = dashboardResult.data.flatMap(examType => 
        examType.subject_areas.map(subject => ({
          id: subject.id,
          name: subject.name
        }))
      )

      // Pomodoro形式と比較
      expect(dashboardSubjects).toEqual(pomodoroResult.data)
      
      // 具体的なデータも確認
      expect(dashboardSubjects).toEqual([
        { id: 1, name: 'データベース基礎' },
        { id: 2, name: 'EC2基礎' }
      ])
    })
  })

  describe('エラー伝播テスト', () => {
    test('APIエラーが両フォーマットメソッドで一貫して処理される', async () => {
      fetch.mockResolvedValue({
        ok: false,
        status: 401,
        json: jest.fn().mockResolvedValue({
          success: false,
          message: '認証が必要です'
        })
      })

      const service = new SubjectAreaService()
      
      const dashboardResult = await service.getSubjectAreasForDashboard()
      service.clearCache() // キャッシュをクリアして再度APIコール
      const pomodoroResult = await service.getSubjectAreasForPomodoro()

      // 両方とも同じエラー構造
      expect(dashboardResult.success).toBe(false)
      expect(pomodoroResult.success).toBe(false)
      
      expect(dashboardResult.error.code).toBe('AUTHENTICATION_REQUIRED')
      expect(pomodoroResult.error.code).toBe('AUTHENTICATION_REQUIRED')
      
      expect(dashboardResult.error.message).toBe('認証が必要です')
      expect(pomodoroResult.error.message).toBe('認証が必要です')
    })
  })

  describe('パフォーマンステスト', () => {
    test('大量データでもフォーマット変換が適切に処理される', async () => {
      // 大量のモックデータを生成
      const largeSubjectAreas = []
      for (let i = 1; i <= 100; i++) {
        largeSubjectAreas.push({
          id: i,
          name: `学習分野${i}`,
          exam_type_id: Math.floor((i - 1) / 10) + 1, // 10個ずつ異なる試験タイプ
          exam_type_name: `試験タイプ${Math.floor((i - 1) / 10) + 1}`,
          is_system: i % 2 === 0, // 偶数はシステム、奇数はユーザー作成
          user_id: i % 2 === 0 ? null : 1
        })
      }

      const mockApiResponse = {
        success: true,
        subject_areas: largeSubjectAreas
      }

      fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: jest.fn().mockResolvedValueOnce(mockApiResponse)
      })

      const service = new SubjectAreaService()
      const startTime = Date.now()
      const result = await service.getSubjectAreasForDashboard()
      const endTime = Date.now()

      // 処理が成功し、適切な時間内に完了
      expect(result.success).toBe(true)
      expect(endTime - startTime).toBeLessThan(100) // 100ms以内
      
      // データ構造の確認
      expect(result.data).toHaveLength(10) // 10の試験タイプ
      expect(result.data[0].subject_areas).toHaveLength(10) // 各試験タイプに10の学習分野
      
      // 合計学習分野数の確認
      const totalSubjects = result.data.reduce((sum, examType) => sum + examType.subject_areas.length, 0)
      expect(totalSubjects).toBe(100)
    })
  })
})