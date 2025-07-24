import ErrorHandler from './ErrorHandler.js';

class ApiRetry {
    constructor(maxRetries = 3, baseDelay = 1000) {
        this.maxRetries = maxRetries;
        this.baseDelay = baseDelay;
    }

    /**
     * 指数バックオフでAPIコールをリトライ
     */
    async retry(apiCall, context = '') {
        let lastError;
        
        for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
            try {
                return await apiCall();
            } catch (error) {
                lastError = error;
                
                // リトライ可能なエラーかチェック
                if (!ErrorHandler.isRetryableError(error)) {
                    throw error;
                }
                
                // 最後の試行の場合はエラーを投げる
                if (attempt === this.maxRetries) {
                    console.error(`API リトライ失敗 (${attempt + 1}回目): ${context}`, error);
                    throw error;
                }
                
                // 待機時間を計算（指数バックオフ + ジッター）
                const delay = this.calculateDelay(attempt);
                console.warn(`API リトライ中 (${attempt + 1}/${this.maxRetries + 1}回目): ${context}, ${delay}ms後に再試行`, error);
                
                await this.sleep(delay);
            }
        }
        
        throw lastError;
    }

    /**
     * 指数バックオフ + ランダムジッターで待機時間を計算
     */
    calculateDelay(attempt) {
        const exponentialDelay = this.baseDelay * Math.pow(2, attempt);
        const jitter = Math.random() * 0.1 * exponentialDelay; // 10%のジッター
        return Math.floor(exponentialDelay + jitter);
    }

    /**
     * 指定時間待機
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * 草表示データ取得（リトライ付き）
     */
    async getGrassDataWithRetry(apiClient, startDate = null, endDate = null) {
        return this.retry(
            () => apiClient.getGrassData(startDate, endDate),
            `草表示データ取得 (${startDate} - ${endDate})`
        );
    }

    /**
     * 月別統計取得（リトライ付き）
     */
    async getMonthlyStatsWithRetry(apiClient, year, month) {
        return this.retry(
            () => apiClient.getMonthlyStats(year, month),
            `月別統計取得 (${year}年${month}月)`
        );
    }

    /**
     * 日別詳細取得（リトライ付き）
     */
    async getDayDetailWithRetry(apiClient, date) {
        return this.retry(
            () => apiClient.getDayDetail(date),
            `日別詳細取得 (${date})`
        );
    }

    /**
     * 複数のAPIコールを並行実行（個別にリトライ）
     */
    async retryParallel(apiCalls) {
        const promises = apiCalls.map(({ call, context }) => 
            this.retry(call, context)
        );
        
        try {
            return await Promise.all(promises);
        } catch (error) {
            // 一つでも失敗した場合はエラーを投げる
            throw error;
        }
    }

    /**
     * 複数のAPIコールを並行実行（一部失敗を許容）
     */
    async retryParallelSettled(apiCalls) {
        const promises = apiCalls.map(async ({ call, context }) => {
            try {
                const result = await this.retry(call, context);
                return { status: 'fulfilled', value: result, context };
            } catch (error) {
                return { status: 'rejected', reason: error, context };
            }
        });
        
        return await Promise.all(promises);
    }

    /**
     * キャッシュ機能付きAPIコール
     */
    async retryWithCache(apiCall, cacheKey, cacheDuration = 5 * 60 * 1000, context = '') {
        // キャッシュから取得を試行
        const cached = this.getCache(cacheKey);
        if (cached && Date.now() - cached.timestamp < cacheDuration) {
            console.log(`キャッシュからデータ取得: ${context}`);
            return cached.data;
        }

        // APIコールをリトライ
        try {
            const result = await this.retry(apiCall, context);
            this.setCache(cacheKey, result);
            return result;
        } catch (error) {
            // APIが失敗した場合、古いキャッシュがあれば返す
            if (cached) {
                console.warn(`API失敗のため古いキャッシュを使用: ${context}`);
                return cached.data;
            }
            throw error;
        }
    }

    /**
     * シンプルなメモリキャッシュ（実際のプロダクションではより堅牢な実装を推奨）
     */
    getCache(key) {
        if (typeof window !== 'undefined' && window.apiCache) {
            return window.apiCache[key];
        }
        return null;
    }

    setCache(key, data) {
        if (typeof window !== 'undefined') {
            if (!window.apiCache) {
                window.apiCache = {};
            }
            window.apiCache[key] = {
                data,
                timestamp: Date.now()
            };
        }
    }
}

export default ApiRetry;