class ErrorHandler {
    /**
     * API エラーを統一的に処理
     */
    static handleApiError(error, context = '') {
        console.error(`API Error ${context}:`, error);

        let message = 'エラーが発生しました。';
        let details = null;

        if (error.response) {
            // サーバーからのレスポンスエラー
            const { status, data } = error.response;
            
            switch (status) {
                case 400:
                    message = 'リクエストが正しくありません。';
                    break;
                case 401:
                    message = '認証が必要です。ログインしてください。';
                    break;
                case 403:
                    message = 'アクセス権限がありません。';
                    break;
                case 404:
                    message = 'データが見つかりません。';
                    break;
                case 422:
                    message = data.message || '入力データが正しくありません。';
                    details = data.errors;
                    break;
                case 429:
                    message = 'リクエストが多すぎます。しばらく待ってから再試行してください。';
                    break;
                case 500:
                    message = 'サーバーエラーが発生しました。';
                    break;
                case 503:
                    message = 'サービスが一時的に利用できません。';
                    break;
                default:
                    message = data.message || 'サーバーエラーが発生しました。';
            }
        } else if (error.request) {
            // ネットワークエラー
            message = 'ネットワークエラーが発生しました。接続を確認してください。';
        } else {
            // その他のエラー
            message = error.message || '予期しないエラーが発生しました。';
        }

        return {
            message,
            details,
            status: error.response?.status || null,
            originalError: error
        };
    }

    /**
     * エラーをユーザーに表示
     */
    static showError(error, context = '') {
        const errorInfo = this.handleApiError(error, context);
        
        // Vue のtoast やnotification ライブラリを使用する場合
        // this.showToast(errorInfo.message, 'error');
        
        // 単純なalert の場合
        alert(errorInfo.message);
        
        return errorInfo;
    }

    /**
     * バリデーションエラーの表示
     */
    static formatValidationErrors(errors) {
        if (!errors || typeof errors !== 'object') {
            return [];
        }

        const formattedErrors = [];
        
        Object.keys(errors).forEach(field => {
            const fieldErrors = Array.isArray(errors[field]) ? errors[field] : [errors[field]];
            fieldErrors.forEach(error => {
                formattedErrors.push({
                    field,
                    message: error
                });
            });
        });

        return formattedErrors;
    }

    /**
     * 草表示機能用のエラーハンドリング
     */
    static handleGrassError(error, operation = '') {
        const errorInfo = this.handleApiError(error, `草表示 ${operation}`);
        
        // 草表示機能特有のエラーメッセージ
        if (error.response?.status === 404) {
            errorInfo.message = '学習データが見つかりません。';
        } else if (error.response?.status === 422) {
            errorInfo.message = '日付の指定が正しくありません。';
        }
        
        return errorInfo;
    }

    /**
     * リトライ可能なエラーかどうかを判定
     */
    static isRetryableError(error) {
        if (!error.response) {
            return true; // ネットワークエラーはリトライ可能
        }

        const status = error.response.status;
        return status >= 500 || status === 429; // サーバーエラーやレート制限はリトライ可能
    }

    /**
     * ログ出力用のエラー情報を整形
     */
    static formatErrorForLogging(error, context = '') {
        return {
            timestamp: new Date().toISOString(),
            context,
            message: error.message,
            status: error.response?.status,
            url: error.response?.config?.url,
            method: error.response?.config?.method,
            data: error.response?.config?.data,
            responseData: error.response?.data,
            stack: error.stack
        };
    }
}

export default ErrorHandler;