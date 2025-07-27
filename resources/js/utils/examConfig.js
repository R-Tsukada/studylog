// 試験・資格マスターデータ設定ファイル
// 試験タイプ、科目データの一元管理

export const examTypeNames = {
  'jstqb_fl': 'JSTQB Foundation Level',
  'jstqb_al': 'JSTQB Advanced Level',
  'aws_clf': 'AWS Cloud Practitioner',
  'aws_saa': 'AWS Solutions Architect Associate',
  'aws_sap': 'AWS Solutions Architect Professional',
  'aws_dva': 'AWS Developer Associate',
  'oracle_bronze': 'Oracle Database Bronze',
  'oracle_silver': 'Oracle Database Silver',
  'oracle_gold': 'Oracle Database Gold',
  'ccna': 'Cisco CCNA',
  'lpic1': 'LPIC Level 1',
  'lpic2': 'LPIC Level 2',
  'ipa_fe': '基本情報技術者試験',
  'ipa_ap': '応用情報技術者試験',
  'other': 'その他'
}

export const subjectNames = {
  // JSTQB Foundation Level
  'testing_fundamentals': 'テストの基礎',
  'test_design_techniques': 'テスト設計技法',
  'test_management': 'テスト管理',
  'tool_support': 'ツールサポート',
  
  // AWS Cloud Practitioner
  'cloud_concepts': 'クラウドの概念',
  'security_compliance': 'セキュリティとコンプライアンス',
  'technology': 'テクノロジー',
  'billing_pricing': '請求と料金',
  
  // AWS Solutions Architect Associate
  'design_resilient_architectures': '復元力のあるアーキテクチャの設計',
  'design_high_performing_architectures': '高性能アーキテクチャの設計',
  'design_secure_applications': 'セキュアなアプリケーションの設計',
  'design_cost_optimized_architectures': 'コスト最適化アーキテクチャの設計',
  
  // 情報処理技術者試験（基本・応用共通）
  'management': 'マネジメント系',
  'strategy': 'ストラテジ系'
}

// 試験タイプ一覧（SetupStepで使用）
export const examTypes = [
  { value: 'jstqb_fl', label: examTypeNames.jstqb_fl },
  { value: 'jstqb_al', label: examTypeNames.jstqb_al },
  { value: 'aws_clf', label: examTypeNames.aws_clf },
  { value: 'aws_saa', label: examTypeNames.aws_saa },
  { value: 'aws_sap', label: examTypeNames.aws_sap },
  { value: 'aws_dva', label: examTypeNames.aws_dva },
  { value: 'oracle_bronze', label: examTypeNames.oracle_bronze },
  { value: 'oracle_silver', label: examTypeNames.oracle_silver },
  { value: 'oracle_gold', label: examTypeNames.oracle_gold },
  { value: 'ccna', label: examTypeNames.ccna },
  { value: 'lpic1', label: examTypeNames.lpic1 },
  { value: 'lpic2', label: examTypeNames.lpic2 },
  { value: 'ipa_fe', label: examTypeNames.ipa_fe },
  { value: 'ipa_ap', label: examTypeNames.ipa_ap },
  { value: 'other', label: examTypeNames.other }
]

// 試験別学習分野マッピング（SetupStepで使用）
export const subjectsByExam = {
  jstqb_fl: [
    { value: 'testing_fundamentals', label: subjectNames.testing_fundamentals },
    { value: 'test_design_techniques', label: subjectNames.test_design_techniques },
    { value: 'test_management', label: subjectNames.test_management },
    { value: 'tool_support', label: subjectNames.tool_support }
  ],
  aws_clf: [
    { value: 'cloud_concepts', label: subjectNames.cloud_concepts },
    { value: 'security_compliance', label: subjectNames.security_compliance },
    { value: 'technology', label: subjectNames.technology },
    { value: 'billing_pricing', label: subjectNames.billing_pricing }
  ],
  aws_saa: [
    { value: 'design_resilient_architectures', label: subjectNames.design_resilient_architectures },
    { value: 'design_high_performing_architectures', label: subjectNames.design_high_performing_architectures },
    { value: 'design_secure_applications', label: subjectNames.design_secure_applications },
    { value: 'design_cost_optimized_architectures', label: subjectNames.design_cost_optimized_architectures }
  ],
  ipa_fe: [
    { value: 'technology', label: subjectNames.technology },
    { value: 'management', label: subjectNames.management },
    { value: 'strategy', label: subjectNames.strategy }
  ],
  ipa_ap: [
    { value: 'technology', label: subjectNames.technology },
    { value: 'management', label: subjectNames.management },
    { value: 'strategy', label: subjectNames.strategy }
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