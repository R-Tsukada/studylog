import axios from 'axios';

class ApiClient {
    constructor() {
        this.client = axios.create({
            baseURL: '/api',
            timeout: 30000,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        this.setupInterceptors();
    }

    setupInterceptors() {
        // リクエストインターセプター（認証トークン付与）
        this.client.interceptors.request.use(
            (config) => {
                const token = localStorage.getItem('auth_token');
                if (token) {
                    config.headers.Authorization = `Bearer ${token}`;
                }
                return config;
            },
            (error) => {
                return Promise.reject(error);
            }
        );

        // レスポンスインターセプター（エラーハンドリング）
        this.client.interceptors.response.use(
            (response) => {
                return response;
            },
            (error) => {
                if (error.response?.status === 401) {
                    // 認証エラーの場合、トークンをクリアしてログインページにリダイレクト
                    localStorage.removeItem('auth_token');
                    window.location.href = '/login';
                }
                return Promise.reject(error);
            }
        );
    }

    async get(url, params = {}) {
        const response = await this.client.get(url, { params });
        return response.data;
    }

    async post(url, data = {}) {
        const response = await this.client.post(url, data);
        return response.data;
    }

    async put(url, data = {}) {
        const response = await this.client.put(url, data);
        return response.data;
    }

    async delete(url) {
        const response = await this.client.delete(url);
        return response.data;
    }

    // 草表示機能専用メソッド
    async getGrassData(startDate = null, endDate = null) {
        return this.get('/analytics/grass-data', {
            start_date: startDate,
            end_date: endDate
        });
    }

    async getMonthlyStats(year, month) {
        return this.get('/analytics/monthly-stats', { year, month });
    }

    async getDayDetail(date) {
        return this.get('/analytics/day-detail', { date });
    }

    async clearGrassCache() {
        return this.post('/analytics/clear-grass-cache');
    }
}

export default new ApiClient();