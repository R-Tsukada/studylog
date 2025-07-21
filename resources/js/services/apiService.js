import axios from 'axios';
import { MockApiService } from './mockApi.js';

// 本番環境（Vercel）を検出
const isProduction = window.location.hostname.includes('vercel.app') || 
                    window.location.hostname.includes('vercel.com') ||
                    window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

export class ApiService {
  constructor() {
    this.isMockMode = isProduction;
    
    // 本番環境検出をコンソールに表示
    if (this.isMockMode) {
      console.log('🎭 モック API モードで動作しています (本番環境)');
      console.log('💡 フル機能を体験するには、ローカル環境でLaravelサーバーを起動してください');
    } else {
      console.log('🚀 Laravel API サーバーに接続します (開発環境)');
    }
  }

  // 環境に応じてAPIまたはモックを切り替え
  async callApi(method, endpoint, data = null, config = {}) {
    if (this.isMockMode) {
      return this.callMockApi(method, endpoint, data);
    } else {
      return this.callRealApi(method, endpoint, data, config);
    }
  }

  // 実際のLaravel APIを呼び出し
  async callRealApi(method, endpoint, data = null, config = {}) {
    try {
      const response = await axios({
        method,
        url: endpoint,
        data,
        ...config
      });
      return response;
    } catch (error) {
      // APIサーバーエラーの場合はモックにフォールバック
      if (error.code === 'ERR_NETWORK' || error.response?.status >= 500) {
        console.warn('🔄 APIエラーのため、モックモードにフォールバックします');
        return this.callMockApi(method, endpoint, data);
      }
      throw error;
    }
  }

  // モックAPIを呼び出し
  async callMockApi(method, endpoint, data = null) {
    // エンドポイントに応じてモック関数をルーティング
    const mockHandlers = {
      'POST:/api/auth/register': () => MockApiService.register(data),
      'POST:/api/auth/login': () => MockApiService.login(data),
      'POST:/api/auth/logout': () => MockApiService.logout(),
      'GET:/api/dashboard': () => MockApiService.getDashboardData(),
      'GET:/api/dashboard/study-calendar': () => MockApiService.getStudyCalendar(),
      'GET:/api/study-sessions/history': () => MockApiService.getStudyHistory(data),
      'GET:/api/study-sessions/current': () => MockApiService.getCurrentSession(),
      'GET:/api/exam-types': () => MockApiService.getExamTypes(),
    };

    const routeKey = `${method.toUpperCase()}:${endpoint}`;
    const handler = mockHandlers[routeKey];

    if (handler) {
      return await handler();
    } else {
      // 未実装のエンドポイント
      console.warn(`🚧 モック未実装: ${routeKey}`);
      throw new Error(`モック環境では ${endpoint} はまだサポートされていません`);
    }
  }

  // 認証関連
  async register(userData) {
    return this.callApi('POST', '/api/auth/register', userData);
  }

  async login(credentials) {
    return this.callApi('POST', '/api/auth/login', credentials);
  }

  async logout() {
    return this.callApi('POST', '/api/auth/logout');
  }

  // ダッシュボード関連
  async getDashboardData() {
    return this.callApi('GET', '/api/dashboard');
  }

  async getStudyCalendar() {
    return this.callApi('GET', '/api/dashboard/study-calendar');
  }

  // 学習履歴関連
  async getStudyHistory(params = {}) {
    const queryString = Object.keys(params).length > 0 ? 
      '?' + new URLSearchParams(params).toString() : '';
    return this.callApi('GET', `/api/study-sessions/history${queryString}`, params);
  }

  async getCurrentSession() {
    return this.callApi('GET', '/api/study-sessions/current');
  }

  // 試験タイプ関連
  async getExamTypes() {
    return this.callApi('GET', '/api/exam-types');
  }

  // モードの取得
  get mockMode() {
    return this.isMockMode;
  }
}

// シングルトンインスタンス
export const apiService = new ApiService();