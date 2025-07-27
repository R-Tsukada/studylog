<?php

return [
    /*
    |--------------------------------------------------------------------------
    | オンボーディング設定
    |--------------------------------------------------------------------------
    */

    // 基本設定
    'total_steps' => 4,
    'version' => '1.0',

    // 表示条件
    'show_within_days' => env('ONBOARDING_SHOW_WITHIN_DAYS', 30),
    'max_login_count' => env('ONBOARDING_MAX_LOGIN_COUNT', 5),

    // データ制限
    'max_step_data_size' => env('ONBOARDING_MAX_STEP_DATA_SIZE', 10240), // 10KB
    'max_feedback_length' => env('ONBOARDING_MAX_FEEDBACK_LENGTH', 1000),

    // セッション設定
    'session_timeout_minutes' => env('ONBOARDING_SESSION_TIMEOUT', 30),

    // ログ設定
    'enable_detailed_logging' => env('ONBOARDING_DETAILED_LOGGING', true),

    // キャッシュ設定
    'analytics_cache_duration' => env('ONBOARDING_ANALYTICS_CACHE_MINUTES', 30),

    // 管理者権限
    'admin_role' => env('ONBOARDING_ADMIN_ROLE', 'admin'),
    'analytics_permissions' => [
        'view_analytics',
        'admin',
    ],
];
