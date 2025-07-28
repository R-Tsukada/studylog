// 試験・資格マスターデータ設定ファイル
// 試験タイプ、科目データの一元管理

export const examTypeNames = {
  'jstqb_fl': 'JSTQB Foundation Level',
  'ipa_fe': '基本情報技術者試験',
  'toeic': 'TOEIC',
  'fp': 'ファイナンシャルプランナー',
  'aws_foundational': 'AWS Foundational',
  'aws_associate': 'AWS Associate'
}

export const subjectNames = {
  // JSTQB Foundation Level
  'testing_fundamentals': 'テストの基礎',
  'test_design_techniques': 'テスト設計技法',
  'test_management': 'テスト管理',
  'tool_support': 'ツールサポート',
  
  // 基本情報技術者試験
  'technology_fe': 'テクノロジ系',
  'management_fe': 'マネジメント系',
  'strategy_fe': 'ストラテジ系',
  
  // TOEIC
  'listening': 'リスニング',
  'reading': 'リーディング',
  'grammar': '文法',
  'vocabulary': '語彙',
  
  // ファイナンシャルプランナー
  'life_planning': 'ライフプランニングと資金計画',
  'risk_management': 'リスク管理',
  'financial_planning': '金融資産運用',
  'tax_planning': 'タックスプランニング',
  'real_estate': '不動産',
  'inheritance': '相続・事業承継',
  
  // AWS Foundational
  'cloud_concepts': 'クラウドの概念',
  'security_compliance': 'セキュリティとコンプライアンス',
  'technology': 'テクノロジー',
  'billing_pricing': '請求と料金',
  
  // AWS Associate
  'design_resilient_architectures': '復元力のあるアーキテクチャの設計',
  'design_high_performing_architectures': '高性能アーキテクチャの設計',
  'design_secure_applications': 'セキュアなアプリケーションの設計',
  'design_cost_optimized_architectures': 'コスト最適化アーキテクチャの設計'
}

// 試験タイプ一覧（SetupStepで使用）
export const examTypes = [
  { value: 'jstqb_fl', label: examTypeNames.jstqb_fl },
  { value: 'ipa_fe', label: examTypeNames.ipa_fe },
  { value: 'toeic', label: examTypeNames.toeic },
  { value: 'fp', label: examTypeNames.fp },
  { value: 'aws_foundational', label: examTypeNames.aws_foundational },
  { value: 'aws_associate', label: examTypeNames.aws_associate }
]

// 試験別学習分野マッピング（SetupStepで使用）
export const subjectsByExam = {
  jstqb_fl: [
    { value: 'testing_fundamentals', label: subjectNames.testing_fundamentals },
    { value: 'test_design_techniques', label: subjectNames.test_design_techniques },
    { value: 'test_management', label: subjectNames.test_management },
    { value: 'tool_support', label: subjectNames.tool_support }
  ],
  ipa_fe: [
    { value: 'technology_fe', label: subjectNames.technology_fe },
    { value: 'management_fe', label: subjectNames.management_fe },
    { value: 'strategy_fe', label: subjectNames.strategy_fe }
  ],
  toeic: [
    { value: 'listening', label: subjectNames.listening },
    { value: 'reading', label: subjectNames.reading },
    { value: 'grammar', label: subjectNames.grammar },
    { value: 'vocabulary', label: subjectNames.vocabulary }
  ],
  fp: [
    { value: 'life_planning', label: subjectNames.life_planning },
    { value: 'risk_management', label: subjectNames.risk_management },
    { value: 'financial_planning', label: subjectNames.financial_planning },
    { value: 'tax_planning', label: subjectNames.tax_planning },
    { value: 'real_estate', label: subjectNames.real_estate },
    { value: 'inheritance', label: subjectNames.inheritance }
  ],
  aws_foundational: [
    { value: 'cloud_concepts', label: subjectNames.cloud_concepts },
    { value: 'security_compliance', label: subjectNames.security_compliance },
    { value: 'technology', label: subjectNames.technology },
    { value: 'billing_pricing', label: subjectNames.billing_pricing }
  ],
  aws_associate: [
    { value: 'design_resilient_architectures', label: subjectNames.design_resilient_architectures },
    { value: 'design_high_performing_architectures', label: subjectNames.design_high_performing_architectures },
    { value: 'design_secure_applications', label: subjectNames.design_secure_applications },
    { value: 'design_cost_optimized_architectures', label: subjectNames.design_cost_optimized_architectures }
  ]
}

// ヘルパー関数：試験タイプ名を取得
export const getExamTypeName = (examType) => {
  return examTypeNames[examType] || examType
}

// ヘルパー関数：科目名を取得
export const getSubjectName = (subjectKey) => {
  return subjectNames[subjectKey] || subjectKey
}