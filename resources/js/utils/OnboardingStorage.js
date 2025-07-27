/**
 * オンボーディング ローカルストレージ管理
 */
class OnboardingStorage {
    constructor() {
        this.STORAGE_KEYS = {
            STATE: 'onboarding_state',
            STEP_DATA: 'onboarding_step_data',
            SESSION: 'onboarding_session'
        };
        this.SESSION_TIMEOUT = 30 * 60 * 1000; // 30分
    }

    /**
     * オンボーディング状態を保存
     */
    saveState(state) {
        try {
            const stateData = {
                ...state,
                timestamp: Date.now(),
                sessionId: this.getSessionId()
            };
            
            localStorage.setItem(
                this.STORAGE_KEYS.STATE, 
                JSON.stringify(stateData)
            );
        } catch (error) {
            console.error('状態保存エラー:', error);
        }
    }

    /**
     * オンボーディング状態を取得
     */
    getState() {
        try {
            const stored = localStorage.getItem(this.STORAGE_KEYS.STATE);
            if (!stored) return null;

            const stateData = JSON.parse(stored);
            
            // セッション有効性チェック
            if (!this.isValidSession(stateData.timestamp)) {
                this.clearState();
                return null;
            }

            return stateData;
        } catch (error) {
            console.error('状態取得エラー:', error);
            return null;
        }
    }

    /**
     * ステップデータを保存
     */
    saveStepData(step, data) {
        try {
            const allStepData = this.getAllStepData();
            allStepData[step] = {
                ...data,
                timestamp: Date.now()
            };
            
            localStorage.setItem(
                this.STORAGE_KEYS.STEP_DATA,
                JSON.stringify(allStepData)
            );
        } catch (error) {
            console.error('ステップデータ保存エラー:', error);
        }
    }

    /**
     * 特定ステップのデータを取得
     */
    getStepData(step) {
        try {
            const allStepData = this.getAllStepData();
            return allStepData[step] || null;
        } catch (error) {
            console.error('ステップデータ取得エラー:', error);
            return null;
        }
    }

    /**
     * 全ステップデータを取得
     */
    getAllStepData() {
        try {
            const stored = localStorage.getItem(this.STORAGE_KEYS.STEP_DATA);
            return stored ? JSON.parse(stored) : {};
        } catch (error) {
            console.error('全ステップデータ取得エラー:', error);
            return {};
        }
    }

    /**
     * ステップデータを復元
     */
    restoreStepData(stepDataObject) {
        try {
            localStorage.setItem(
                this.STORAGE_KEYS.STEP_DATA,
                JSON.stringify(stepDataObject)
            );
        } catch (error) {
            console.error('ステップデータ復元エラー:', error);
        }
    }

    /**
     * セッションIDを取得または生成
     */
    getSessionId() {
        try {
            let sessionData = localStorage.getItem(this.STORAGE_KEYS.SESSION);
            
            if (sessionData) {
                sessionData = JSON.parse(sessionData);
                
                // セッション有効期限チェック
                if (Date.now() - sessionData.created < this.SESSION_TIMEOUT) {
                    return sessionData.id;
                }
            }

            // 新しいセッションを作成
            const newSession = {
                id: this.generateSessionId(),
                created: Date.now()
            };

            localStorage.setItem(
                this.STORAGE_KEYS.SESSION,
                JSON.stringify(newSession)
            );

            return newSession.id;
        } catch (error) {
            console.error('セッション取得エラー:', error);
            return this.generateSessionId();
        }
    }

    /**
     * セッションIDを生成
     */
    generateSessionId() {
        const timestamp = Date.now();
        
        // 暗号学的に安全な乱数生成を試行
        try {
            const randomValue = crypto.getRandomValues(new Uint32Array(1))[0].toString(36);
            return `onboarding_${timestamp}_${randomValue}`;
        } catch (error) {
            // フォールバック: crypto.getRandomValuesが利用できない場合
            console.warn('crypto.getRandomValues not available, falling back to Math.random:', error);
            const fallbackRandom = Math.random().toString(36).substr(2, 9);
            return `onboarding_${timestamp}_${fallbackRandom}`;
        }
    }

    /**
     * セッション有効性チェック
     */
    isValidSession(timestamp = null) {
        if (!timestamp) {
            try {
                const stored = localStorage.getItem(this.STORAGE_KEYS.STATE);
                if (!stored) return false;
                const stateData = JSON.parse(stored);
                timestamp = stateData.timestamp;
            } catch (error) {
                return false;
            }
        }

        if (!timestamp) return false;

        return (Date.now() - timestamp) < this.SESSION_TIMEOUT;
    }

    /**
     * 状態をクリア
     */
    clearState() {
        try {
            localStorage.removeItem(this.STORAGE_KEYS.STATE);
        } catch (error) {
            console.error('状態クリアエラー:', error);
        }
    }

    /**
     * ステップデータをクリア
     */
    clearStepData() {
        try {
            localStorage.removeItem(this.STORAGE_KEYS.STEP_DATA);
        } catch (error) {
            console.error('ステップデータクリアエラー:', error);
        }
    }

    /**
     * セッションをクリア
     */
    clearSession() {
        try {
            localStorage.removeItem(this.STORAGE_KEYS.SESSION);
        } catch (error) {
            console.error('セッションクリアエラー:', error);
        }
    }

    /**
     * 全データをクリア
     */
    clearAll() {
        this.clearState();
        this.clearStepData();
        this.clearSession();
    }

    /**
     * ストレージ使用量をチェック
     */
    getStorageInfo() {
        try {
            const state = localStorage.getItem(this.STORAGE_KEYS.STATE);
            const stepData = localStorage.getItem(this.STORAGE_KEYS.STEP_DATA);
            const session = localStorage.getItem(this.STORAGE_KEYS.SESSION);

            return {
                stateSize: state ? state.length : 0,
                stepDataSize: stepData ? stepData.length : 0,
                sessionSize: session ? session.length : 0,
                totalSize: (state?.length || 0) + (stepData?.length || 0) + (session?.length || 0)
            };
        } catch (error) {
            console.error('ストレージ情報取得エラー:', error);
            return { stateSize: 0, stepDataSize: 0, sessionSize: 0, totalSize: 0 };
        }
    }

    /**
     * デバッグ情報を出力
     */
    debug() {
        console.group('🔍 Onboarding Storage Debug');
        console.log('State:', this.getState());
        console.log('Step Data:', this.getAllStepData());
        console.log('Session ID:', this.getSessionId());
        console.log('Storage Info:', this.getStorageInfo());
        console.log('Session Valid:', this.isValidSession());
        console.groupEnd();
    }
}

// シングルトンインスタンスをエクスポート
export default new OnboardingStorage();