/**
 * オンボーディングAPI クライアント
 */
import axios from 'axios';

class OnboardingAPI {
    /**
     * オンボーディング状態を取得
     */
    async getStatus() {
        try {
            const response = await axios.get('/api/onboarding/status');
            return response.data;
        } catch (error) {
            console.error('オンボーディング状態取得エラー:', error);
            throw this.handleError(error);
        }
    }

    /**
     * オンボーディング進捗を更新
     */
    async updateProgress(progressData) {
        try {
            const response = await axios.post('/api/onboarding/progress', progressData);
            return response.data;
        } catch (error) {
            console.error('オンボーディング進捗更新エラー:', error);
            throw this.handleError(error);
        }
    }

    /**
     * オンボーディング完了
     */
    async complete(completionData = {}) {
        try {
            const response = await axios.post('/api/onboarding/complete', completionData);
            return response.data;
        } catch (error) {
            console.error('オンボーディング完了エラー:', error);
            throw this.handleError(error);
        }
    }

    /**
     * オンボーディングスキップ
     */
    async skip(skipData = {}) {
        try {
            const response = await axios.post('/api/onboarding/skip', skipData);
            return response.data;
        } catch (error) {
            console.error('オンボーディングスキップエラー:', error);
            throw this.handleError(error);
        }
    }

    /**
     * オンボーディング統計取得（管理者用）
     */
    async getAnalytics(params = {}) {
        try {
            const response = await axios.get('/api/onboarding/analytics', { params });
            return response.data;
        } catch (error) {
            console.error('オンボーディング統計取得エラー:', error);
            throw this.handleError(error);
        }
    }

    /**
     * エラーハンドリング
     */
    handleError(error) {
        if (error.response) {
            // サーバーからのエラーレスポンス
            const { status, data } = error.response;
            
            if (status === 401) {
                // 認証エラー
                return new Error('認証が必要です。ログインしてください。');
            } else if (status === 422) {
                // バリデーションエラー
                const validationErrors = data.errors || {};
                const errorMessages = Object.values(validationErrors).flat();
                return new Error(errorMessages.join(', ') || 'バリデーションエラーが発生しました。');
            } else if (status >= 500) {
                // サーバーエラー
                return new Error(data.message || 'サーバーエラーが発生しました。');
            } else {
                // その他のエラー
                return new Error(data.message || 'エラーが発生しました。');
            }
        } else if (error.request) {
            // ネットワークエラー
            return new Error('ネットワークエラーが発生しました。接続を確認してください。');
        } else {
            // その他のエラー
            return new Error('予期しないエラーが発生しました。');
        }
    }

    /**
     * リトライ機能付きAPIコール
     */
    async withRetry(apiCall, maxRetries = 3, delay = 1000) {
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                return await apiCall();
            } catch (error) {
                if (attempt === maxRetries) {
                    throw error;
                }

                // 指数バックオフでリトライ
                await new Promise(resolve => 
                    setTimeout(resolve, delay * Math.pow(2, attempt - 1))
                );
            }
        }
    }
}

// シングルトンインスタンスをエクスポート
export default new OnboardingAPI();