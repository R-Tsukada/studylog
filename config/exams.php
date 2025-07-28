<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exam Types Configuration
    |--------------------------------------------------------------------------
    |
    | 資格・試験タイプの一元管理設定
    | フロントエンドとバックエンドで共通使用
    |
    */

    'types' => [
        'jstqb_fl' => [
            'name' => 'JSTQB Foundation Level',
            'description' => 'ソフトウェアテスト技術者資格試験',
            'category' => 'software_testing',
            'color' => '#4CAF50',
        ],
        'ipa_fe' => [
            'name' => '基本情報技術者試験',
            'description' => 'IPA基本情報技術者試験',
            'category' => 'information_processing',
            'color' => '#2196F3',
        ],
        'toeic' => [
            'name' => 'TOEIC',
            'description' => 'TOEIC Listening & Reading Test',
            'category' => 'language',
            'color' => '#FF9800',
        ],
        'fp' => [
            'name' => 'ファイナンシャルプランナー',
            'description' => 'ファイナンシャル・プランニング技能検定',
            'category' => 'finance',
            'color' => '#9C27B0',
        ],
        'aws_foundational' => [
            'name' => 'AWS Foundational',
            'description' => 'AWS認定基礎レベル',
            'category' => 'cloud',
            'color' => '#FF5722',
        ],
        'aws_associate' => [
            'name' => 'AWS Associate',
            'description' => 'AWS認定アソシエイトレベル',
            'category' => 'cloud',
            'color' => '#F44336',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subject Areas Configuration
    |--------------------------------------------------------------------------
    |
    | 各試験タイプの学習分野定義
    |
    */

    'subjects' => [
        'jstqb_fl' => [
            'testing_fundamentals' => 'テストの基礎',
            'test_design_techniques' => 'テスト設計技法',
            'test_management' => 'テスト管理',
            'tool_support' => 'ツールサポート',
        ],
        'ipa_fe' => [
            'technology_fe' => 'テクノロジ系',
            'management_fe' => 'マネジメント系',
            'strategy_fe' => 'ストラテジ系',
        ],
        'toeic' => [
            'listening' => 'リスニング',
            'reading' => 'リーディング',
            'grammar' => '文法',
            'vocabulary' => '語彙',
        ],
        'fp' => [
            'life_planning' => 'ライフプランニングと資金計画',
            'risk_management' => 'リスク管理',
            'financial_planning' => '金融資産運用',
            'tax_planning' => 'タックスプランニング',
            'real_estate' => '不動産',
            'inheritance' => '相続・事業承継',
        ],
        'aws_foundational' => [
            'cloud_concepts' => 'クラウドの概念',
            'security_compliance' => 'セキュリティとコンプライアンス',
            'technology' => 'テクノロジー',
            'billing_pricing' => '請求と料金',
        ],
        'aws_associate' => [
            'design_resilient_architectures' => '復元力のあるアーキテクチャの設計',
            'design_high_performing_architectures' => '高性能アーキテクチャの設計',
            'design_secure_applications' => 'セキュアなアプリケーションの設計',
            'design_cost_optimized_architectures' => 'コスト最適化アーキテクチャの設計',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | 試験・学習分野のバリデーション設定
    |
    */

    'validation' => [
        'exam_name_max_length' => 255,
        'exam_description_max_length' => 1000,
        'exam_notes_max_length' => 2000,
        'subject_name_max_length' => 255,
        'max_custom_subjects' => 10,
        'exam_code_base_length' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | 試験カテゴリの定義
    |
    */

    'categories' => [
        'software_testing' => 'ソフトウェアテスト',
        'information_processing' => '情報処理',
        'language' => '語学',
        'finance' => '金融',
        'cloud' => 'クラウド',
    ],
];