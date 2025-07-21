// Vercel本番環境用のモックAPIサービス

export class MockApiService {
  // 遅延を模擬するためのヘルパー関数
  static delay(ms = 1000) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  // ローカルストレージからユーザーデータを管理
  static getCurrentUser() {
    return JSON.parse(localStorage.getItem('mockUser') || 'null');
  }

  static setCurrentUser(user) {
    localStorage.setItem('mockUser', JSON.stringify(user));
  }

  static removeCurrentUser() {
    localStorage.removeItem('mockUser');
  }

  // 認証API
  static async register(userData) {
    await this.delay(1500);

    // 簡単なバリデーション
    if (!userData.email || !userData.password || !userData.name) {
      throw new Error('必須項目を入力してください');
    }

    if (userData.password !== userData.password_confirmation) {
      throw new Error('パスワードが一致しません');
    }

    // モックユーザーデータを作成
    const user = {
      id: Date.now(),
      name: userData.name,
      email: userData.email,
      avatar_url: `https://ui-avatars.com/api/?name=${encodeURIComponent(userData.name)}&color=3B82F6&background=E5F3FF`,
      created_at: new Date().toISOString()
    };

    const token = 'mock-token-' + Date.now();
    
    this.setCurrentUser({ ...user, token });

    return {
      data: {
        success: true,
        message: 'アカウントが作成されました',
        user: user,
        token: token
      }
    };
  }

  static async login(credentials) {
    await this.delay(1000);

    if (!credentials.email || !credentials.password) {
      throw new Error('メールアドレスとパスワードを入力してください');
    }

    // デモ用ユーザー
    const user = {
      id: 1,
      name: 'デモユーザー',
      email: credentials.email,
      avatar_url: 'https://ui-avatars.com/api/?name=デモユーザー&color=3B82F6&background=E5F3FF',
      created_at: new Date().toISOString()
    };

    const token = 'mock-token-demo';
    
    this.setCurrentUser({ ...user, token });

    return {
      data: {
        success: true,
        message: 'ログインしました',
        user: user,
        token: token
      }
    };
  }

  static async logout() {
    await this.delay(500);
    this.removeCurrentUser();
    return {
      data: {
        success: true,
        message: 'ログアウトしました'
      }
    };
  }

  // ダッシュボードAPI
  static async getDashboardData() {
    await this.delay(800);
    return {
      data: {
        success: true,
        data: {
          continuous_days: 7,
          today_study_time: '2時間30分',
          today_session_count: 3,
          achievement_rate: 75.5
        }
      }
    };
  }

  static async getStudyCalendar() {
    await this.delay(1000);
    
    // 過去1年分のモックデータを生成
    const calendarData = [];
    const startDate = new Date();
    startDate.setFullYear(startDate.getFullYear() - 1);
    
    for (let d = new Date(startDate); d <= new Date(); d.setDate(d.getDate() + 1)) {
      const level = Math.random() < 0.7 ? Math.floor(Math.random() * 5) : 0;
      const minutes = level * 30 + Math.floor(Math.random() * 60);
      
      calendarData.push({
        date: d.toISOString().split('T')[0],
        month: d.getMonth() + 1,
        level: level,
        minutes: minutes,
        session_count: level > 0 ? Math.floor(Math.random() * 3) + 1 : 0,
        formatted_time: minutes > 0 ? `${Math.floor(minutes / 60)}時間${minutes % 60}分` : '0分'
      });
    }

    return {
      data: {
        success: true,
        data: {
          calendar_data: calendarData,
          total_study_days: calendarData.filter(d => d.level > 0).length,
          max_study_minutes: Math.max(...calendarData.map(d => d.minutes))
        }
      }
    };
  }

  // 学習履歴API
  static async getStudyHistory(params = {}) {
    await this.delay(600);
    
    const mockHistory = [
      {
        id: 1,
        subject_area_name: 'テスト基礎',
        exam_type_name: 'JSTQB Foundation Level',
        duration_minutes: 90,
        study_comment: 'テストプロセスについて学習',
        date: '2025-01-20'
      },
      {
        id: 2,
        subject_area_name: 'AWS EC2',
        exam_type_name: 'AWS Solutions Architect',
        duration_minutes: 120,
        study_comment: 'インスタンスタイプと料金の復習',
        date: '2025-01-20'
      },
      {
        id: 3,
        subject_area_name: 'Java基本文法',
        exam_type_name: 'Java SE 11 認定',
        duration_minutes: 75,
        study_comment: 'ジェネリクスとコレクション',
        date: '2025-01-19'
      }
    ];

    return {
      data: {
        success: true,
        history: mockHistory.slice(0, params.limit || 10)
      }
    };
  }

  // 試験タイプAPI
  static async getExamTypes() {
    await this.delay(400);
    return {
      data: [
        {
          id: 1,
          name: 'JSTQB Foundation Level',
          description: 'ソフトウェアテスト技術者資格',
          color: '#3B82F6',
          subject_areas: [
            { id: 1, name: 'テスト基礎' },
            { id: 2, name: 'テスト技法' },
            { id: 3, name: 'テスト管理' }
          ]
        },
        {
          id: 2,
          name: 'AWS Solutions Architect',
          description: 'Amazon Web Services認定試験',
          color: '#F59E0B',
          subject_areas: [
            { id: 4, name: 'EC2・コンピューティング' },
            { id: 5, name: 'S3・ストレージ' },
            { id: 6, name: 'VPC・ネットワーク' }
          ]
        }
      ]
    };
  }

  // 現在のセッション状態
  static async getCurrentSession() {
    await this.delay(300);
    return {
      data: {
        success: true,
        session: null // モック環境では学習セッションなし
      }
    };
  }
}