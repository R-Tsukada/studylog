class GrassCalendarUtils {
    /**
     * 草表示用のカラーマッピング
     */
    static getGrassColors() {
        return {
            0: '#ebedf0', // レベル0: なし（薄いグレー）
            1: '#9be9a8', // レベル1: 薄い緑
            2: '#40c463', // レベル2: 中間の緑
            3: '#30a14e', // レベル3: 濃い緑
        };
    }

    /**
     * 学習レベルに応じたCSS クラス名を取得
     */
    static getGrassLevelClass(level) {
        const classes = {
            0: 'grass-level-0',
            1: 'grass-level-1', 
            2: 'grass-level-2',
            3: 'grass-level-3'
        };
        return classes[level] || classes[0];
    }

    /**
     * 年間表示用のデータを生成（GitHub風）
     */
    static generateYearData(grassData, year) {
        const startDate = new Date(year, 0, 1);
        const endDate = new Date(year, 11, 31);
        
        // 年の最初の日曜日から開始
        const firstSunday = new Date(startDate);
        firstSunday.setDate(startDate.getDate() - startDate.getDay());
        
        // 年の最後の土曜日まで
        const lastSaturday = new Date(endDate);
        lastSaturday.setDate(endDate.getDate() + (6 - endDate.getDay()));
        
        const weeks = [];
        const dataMap = new Map();
        
        // 草データをMapに変換
        grassData.data?.forEach(day => {
            dataMap.set(day.date, day);
        });
        
        let currentDate = new Date(firstSunday);
        
        while (currentDate <= lastSaturday) {
            const week = [];
            
            for (let i = 0; i < 7; i++) {
                const dateStr = this.formatDate(currentDate);
                const dayData = dataMap.get(dateStr) || {
                    date: dateStr,
                    total_minutes: 0,
                    level: 0,
                    study_session_minutes: 0,
                    pomodoro_minutes: 0,
                    session_count: 0,
                    focus_sessions: 0
                };
                
                // 指定年以外の日付はグレーアウト
                const isCurrentYear = currentDate.getFullYear() === year;
                
                week.push({
                    ...dayData,
                    date: dateStr,
                    dayOfWeek: currentDate.getDay(),
                    isCurrentYear,
                    isEmpty: false // 全ての日付にはデータがある（0分の場合もある）
                });
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            weeks.push(week);
        }
        
        return {
            weeks,
            year,
            stats: this.calculateYearStats(grassData.data || [], year)
        };
    }

    /**
     * 月別表示用のデータを生成
     */
    static generateMonthData(grassData, year, month) {
        const startDate = new Date(year, month - 1, 1);
        const endDate = new Date(year, month, 0); // 月の最終日
        
        const firstDay = startDate.getDay(); // 月初の曜日
        const daysInMonth = endDate.getDate();
        
        const dataMap = new Map();
        grassData.data?.forEach(day => {
            dataMap.set(day.date, day);
        });
        
        const weeks = [];
        let week = [];
        
        // 月初の空白セルを追加
        for (let i = 0; i < firstDay; i++) {
            week.push({ isEmpty: true });
        }
        
        // 月の日付を追加
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDate = new Date(year, month - 1, day);
            const dateStr = this.formatDate(currentDate);
            const dayData = dataMap.get(dateStr) || {
                date: dateStr,
                total_minutes: 0,
                level: 0,
                study_session_minutes: 0,
                pomodoro_minutes: 0,
                session_count: 0,
                focus_sessions: 0
            };
            
            week.push({
                ...dayData,
                date: dateStr,
                day,
                dayOfWeek: currentDate.getDay(),
                isEmpty: false
            });
            
            // 週が満了したら新しい週を開始
            if (week.length === 7) {
                weeks.push(week);
                week = [];
            }
        }
        
        // 最後の週の空白セルを追加
        while (week.length > 0 && week.length < 7) {
            week.push({ isEmpty: true });
        }
        if (week.length > 0) {
            weeks.push(week);
        }
        
        return {
            weeks,
            year,
            month,
            monthName: this.getMonthName(month),
            stats: this.calculateMonthStats(grassData.data || [], year, month)
        };
    }

    /**
     * 日付を YYYY-MM-DD 形式でフォーマット
     */
    static formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    /**
     * 月名を取得
     */
    static getMonthName(month) {
        const monthNames = [
            '1月', '2月', '3月', '4月', '5月', '6月',
            '7月', '8月', '9月', '10月', '11月', '12月'
        ];
        return monthNames[month - 1];
    }

    /**
     * 曜日名を取得
     */
    static getDayNames() {
        return ['日', '月', '火', '水', '木', '金', '土'];
    }

    /**
     * 年間統計を計算
     */
    static calculateYearStats(data, year) {
        const yearData = data.filter(day => {
            return new Date(day.date).getFullYear() === year;
        });
        
        return this.calculateStatsFromData(yearData);
    }

    /**
     * 月間統計を計算
     */
    static calculateMonthStats(data, year, month) {
        const monthData = data.filter(day => {
            const date = new Date(day.date);
            return date.getFullYear() === year && date.getMonth() === month - 1;
        });
        
        return this.calculateStatsFromData(monthData);
    }

    /**
     * データから統計を計算
     */
    static calculateStatsFromData(data) {
        const totalDays = data.length;
        const studyDays = data.filter(day => day.total_minutes > 0).length;
        const totalMinutes = data.reduce((sum, day) => sum + day.total_minutes, 0);
        const totalStudySessions = data.reduce((sum, day) => sum + (day.session_count || 0), 0);
        const totalPomodoroSessions = data.reduce((sum, day) => sum + (day.focus_sessions || 0), 0);
        
        const levelCounts = {
            0: data.filter(day => day.level === 0).length,
            1: data.filter(day => day.level === 1).length,
            2: data.filter(day => day.level === 2).length,
            3: data.filter(day => day.level === 3).length
        };
        
        return {
            totalDays,
            studyDays,
            studyRate: totalDays > 0 ? Math.round((studyDays / totalDays) * 100) : 0,
            totalMinutes,
            totalHours: Math.round(totalMinutes / 60 * 10) / 10,
            averageDailyMinutes: studyDays > 0 ? Math.round(totalMinutes / studyDays) : 0,
            totalStudySessions,
            totalPomodoroSessions,
            levelDistribution: levelCounts,
            longestStreak: this.calculateLongestStreak(data),
            currentStreak: this.calculateCurrentStreak(data)
        };
    }

    /**
     * 最長連続学習日数を計算
     */
    static calculateLongestStreak(data) {
        let maxStreak = 0;
        let currentStreak = 0;
        
        // 日付順にソート
        const sortedData = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));
        
        for (const day of sortedData) {
            if (day.total_minutes > 0) {
                currentStreak++;
                maxStreak = Math.max(maxStreak, currentStreak);
            } else {
                currentStreak = 0;
            }
        }
        
        return maxStreak;
    }

    /**
     * 現在の連続学習日数を計算
     */
    static calculateCurrentStreak(data) {
        let streak = 0;
        
        // 日付順にソート（新しい順）
        const sortedData = [...data].sort((a, b) => new Date(b.date) - new Date(a.date));
        
        for (const day of sortedData) {
            if (day.total_minutes > 0) {
                streak++;
            } else {
                break;
            }
        }
        
        return streak;
    }

    /**
     * ツールチップ用のテキストを生成
     */
    static generateTooltipText(dayData) {
        if (!dayData || dayData.isEmpty) {
            return '';
        }
        
        const date = new Date(dayData.date).toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'long'
        });
        
        if (dayData.total_minutes === 0) {
            return `${date}\n学習時間なし`;
        }
        
        const hours = Math.floor(dayData.total_minutes / 60);
        const minutes = dayData.total_minutes % 60;
        const timeText = hours > 0 ? `${hours}時間${minutes}分` : `${minutes}分`;
        
        const details = [];
        if (dayData.study_session_minutes > 0) {
            details.push(`自由計測: ${dayData.study_session_minutes}分`);
        }
        if (dayData.pomodoro_minutes > 0) {
            details.push(`ポモドーロ: ${dayData.pomodoro_minutes}分`);
        }
        if (dayData.focus_sessions > 0) {
            details.push(`フォーカスセッション: ${dayData.focus_sessions}回`);
        }
        
        return `${date}\n学習時間: ${timeText}\n${details.join('\n')}`;
    }

    /**
     * 学習レベルの説明テキストを取得
     */
    static getLevelDescription(level) {
        const descriptions = {
            0: '学習なし',
            1: '軽い学習 (1時間以下)',
            2: '中程度の学習 (1-2時間)',
            3: '集中学習 (2時間以上)'
        };
        return descriptions[level] || descriptions[0];
    }

    /**
     * パフォーマンス最適化: 大きなデータセットのバッチ処理
     */
    static processLargeDataset(data, batchSize = 100) {
        const batches = [];
        for (let i = 0; i < data.length; i += batchSize) {
            batches.push(data.slice(i, i + batchSize));
        }
        return batches;
    }
}

export default GrassCalendarUtils;