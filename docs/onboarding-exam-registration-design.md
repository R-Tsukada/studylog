# オンボーディングモーダル資格登録機能 設計書（修正版）

## 1. 概要

### 1.1 目的
初回ログインモーダルで受験予定の資格を登録できる機能を追加し、設定画面との**完全な一貫性**を保証する。オンボーディングで登録したデータが設定画面に適切に反映される設計を実現する。

### 1.2 スコープ
- OnboardingModal の SetupStep における資格登録フローの拡張
- **OnboardingController.complete() の拡張**（重要）
- 設定画面への自動反映機能
- ユーザビリティ、セキュリティ、パフォーマンスを考慮した実装

## 2. 現状分析と重要な問題発見

### 2.1 既存のオンボーディングフロー
- **WelcomeStep**: アプリ紹介
- **SetupStep**: 基本設定（資格選択、試験日、目標学習時間、プロフィール）
- **FeatureStep**: 機能紹介
- **CompletionStep**: 完了表示

### 2.2 既存の資格関連機能
- **examConfig.js**: 固定の試験タイプ定義（19種類）
- **Settings.vue**: カスタム試験タイプの作成・編集機能
- **UserExamTypeController**: 試験タイプのCRUD API
- **StudyGoalController**: 学習目標のCRUD API

### 2.3 ⚠️ 現在の重大な問題点
1. **データの断絶**: OnboardingControllerの`complete()`メソッドは`onboarding_progress`のみ更新
2. **実データ未作成**: SetupStepで入力されたデータが`exam_types`、`study_goals`テーブルに保存されない
3. **設定画面への未反映**: オンボーディング完了後、設定画面が空の状態になる
4. **データフローの不整合**: step_dataが一時保存のみで実際のリソース作成に使われない

## 3. 要件定義

### 3.1 機能要件
1. **カスタム試験タイプの登録**
   - 試験名の入力
   - 試験予定日の設定
   - 説明・メモの入力
   - テーマカラーの選択

2. **既存リストとの統合**
   - examConfig.js の既存試験タイプとの併用
   - 「その他」選択時にカスタム入力フォーム表示

3. **データ整合性**
   - 設定画面と同じデータ構造を使用
   - 重複チェック
   - バリデーション

### 3.2 非機能要件
1. **保守性**: 既存コードとの一貫性
2. **可読性**: 明確なコンポーネント分離
3. **脆弱性**: 入力値検証、SQLインジェクション対策
4. **パフォーマンス**: 最小限のAPI呼び出し
5. **影響範囲**: 既存機能への影響最小化

## 4. 修正版アーキテクチャ設計

### 4.1 データフロー全体像（修正版）

```
【オンボーディング開始】
1. SetupStep でフォーム入力
   ├── 既存試験選択 OR カスタム試験作成
   ├── 試験予定日設定
   ├── 目標学習時間設定
   └── プロフィール情報
   ↓
2. step_data として一時保存
   ↓
3. 【重要】OnboardingController.complete() 拡張
   ├── step_data から試験情報を抽出
   ├── ExamType作成（カスタム試験の場合）
   ├── StudyGoal作成（必須）
   └── onboarding_progress更新
   ↓
4. 【結果】設定画面で確認
   ├── 試験予定日の管理 → 作成済みExamType表示
   └── 学習目標設定 → 作成済みStudyGoal表示
```

### 4.2 コンポーネント構成（修正版）

```
OnboardingModal
├── SetupStep (拡張)
│   ├── 既存の試験選択セクション
│   └── CustomExamForm (新規コンポーネント)
│       ├── 試験名入力
│       ├── 試験日設定
│       ├── 説明入力
│       └── カラー選択
├── OnboardingController (重要な拡張)
│   └── complete() メソッド拡張
│       ├── ExamType自動作成
│       └── StudyGoal自動作成
├── WelcomeStep (変更なし)
├── FeatureStep (変更なし)
└── CompletionStep (表示内容の拡張)
```

### 4.3 データ整合性保証の仕組み

```
【オンボーディング完了時の処理】
OnboardingController.complete() {
  DB::transaction(() => {
    // 1. step_dataからSetupStep情報を取得
    $setupData = $validated['step_data']['setup_step'] ?? [];
    
    // 2. カスタム試験タイプ作成（必要時）
    if ($setupData['exam_type'] === 'custom') {
      $examType = ExamType::create([
        'user_id' => $user->id,
        'name' => $setupData['custom_exam_name'],
        'exam_date' => $setupData['exam_date'],
        'description' => $setupData['custom_exam_description'],
        'color' => $setupData['custom_exam_color']
      ]);
      $examTypeId = $examType->id;
    } else {
      $examTypeId = $setupData['exam_type_id'];
    }
    
    // 3. 学習目標作成（必須）
    StudyGoal::create([
      'user_id' => $user->id,
      'exam_type_id' => $examTypeId,
      'daily_minutes_goal' => $setupData['daily_goal_minutes'],
      'exam_date' => $setupData['exam_date'],
      'is_active' => true
    ]);
    
    // 4. オンボーディング完了処理
    $user->completeOnboarding($validated);
  });
}
```

## 5. 詳細設計（修正版）

### 5.1 OnboardingController.php の拡張（最重要）

#### 5.1.1 complete() メソッドの修正
```php
public function complete(OnboardingCompleteRequest $request): JsonResponse
{
    try {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $user, $request) {
            // 1. SetupStepのデータを取得
            $setupData = $this->extractSetupStepData($validated);
            
            // 2. 試験タイプの処理
            $examTypeId = $this->processExamType($setupData, $user);
            
            // 3. 学習目標の作成
            $this->createStudyGoal($setupData, $examTypeId, $user);
            
            // 4. 従来のオンボーディング完了処理
            $user->completeOnboarding([
                'total_time_spent' => $validated['total_time_spent'] ?? 0,
                'step_times' => $validated['step_times'] ?? [],
                'feedback' => $validated['feedback'] ?? null,
                'completion_source' => 'web_app',
                'created_resources' => [
                    'exam_type_id' => $examTypeId,
                    'has_custom_exam' => isset($setupData['custom_exam_name'])
                ]
            ], $request->userAgent(), $request->ip());
        });

        $user->refresh();
        
        return $this->successResponse([
            'completed_at' => $user->onboarding_completed_at->toISOString(),
            'stats' => $user->getOnboardingStats(),
            'setup_complete' => true
        ], $request, 'オンボーディングが完了し、設定が作成されました');

    } catch (\Exception $e) {
        return $this->errorResponse('完了処理中にエラーが発生しました', $e, $request);
    }
}

private function extractSetupStepData(array $validated): array
{
    $progressData = $validated['step_data'] ?? [];
    return $progressData['setup_step'] ?? [];
}

private function processExamType(array $setupData, User $user): ?int
{
    // カスタム試験の場合
    if (isset($setupData['custom_exam_name'])) {
        $examType = ExamType::create([
            'user_id' => $user->id,
            'code' => $this->generateExamCode($setupData['custom_exam_name'], $user->id),
            'name' => $setupData['custom_exam_name'],
            'description' => $setupData['custom_exam_description'] ?? '',
            'exam_date' => $setupData['exam_date'] ?? null,
            'exam_notes' => $setupData['custom_exam_notes'] ?? null,
            'color' => $setupData['custom_exam_color'] ?? '#3B82F6',
            'is_system' => false,
            'is_active' => true
        ]);
        return $examType->id;
    }
    
    // 既存試験の場合（examConfig.jsから選択）
    if (isset($setupData['exam_type']) && $setupData['exam_type'] !== 'other') {
        // システム試験タイプの場合は、ユーザー固有のインスタンスを作成
        $examTypeName = examTypeNames[$setupData['exam_type']] ?? $setupData['exam_type'];
        $examType = ExamType::create([
            'user_id' => $user->id,
            'code' => $setupData['exam_type'],
            'name' => $examTypeName,
            'description' => "システム標準試験: {$examTypeName}",
            'exam_date' => $setupData['exam_date'] ?? null,
            'is_system' => false, // ユーザー固有インスタンス
            'is_active' => true
        ]);
        return $examType->id;
    }
    
    return null;
}

private function createStudyGoal(array $setupData, ?int $examTypeId, User $user): void
{
    // 既存のアクティブ目標を無効化
    StudyGoal::forUser($user->id)->active()->update(['is_active' => false]);
    
    // 新しい目標を作成
    StudyGoal::create([
        'user_id' => $user->id,
        'exam_type_id' => $examTypeId,
        'daily_minutes_goal' => $setupData['daily_goal_minutes'] ?? 60,
        'weekly_minutes_goal' => null, // オンボーディングでは日次のみ
        'exam_date' => $setupData['exam_date'] ?? null,
        'is_active' => true
    ]);
}

private function generateExamCode(string $name, int $userId): string
{
    // UserExamTypeControllerと同じロジックを使用
    $baseCode = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    if (empty($baseCode)) {
        $baseCode = 'exam';
    }
    $baseCode = strtolower(substr($baseCode, 0, 10));
    return $baseCode . '_' . $userId . '_' . time();
}
```

### 5.2 OnboardingCompleteRequest.php の拡張

#### 5.2.1 バリデーションルールの追加
```php
public function rules(): array
{
    $totalSteps = config('onboarding.total_steps', 4);
    $maxFeedbackLength = config('onboarding.max_feedback_length', 1000);

    return [
        'completed_steps' => 'nullable|array',
        'completed_steps.*' => ['integer', 'min:1', "max:{$totalSteps}"],
        'total_time_spent' => 'nullable|integer|min:0|max:86400',
        'step_times' => 'nullable|array',
        'step_times.*' => 'integer|min:0|max:3600',
        'feedback' => "nullable|string|max:{$maxFeedbackLength}",
        
        // step_data のバリデーション追加
        'step_data' => 'nullable|array',
        'step_data.setup_step' => 'nullable|array',
        'step_data.setup_step.exam_type' => 'nullable|string|max:255',
        'step_data.setup_step.exam_date' => 'nullable|date|after:today',
        'step_data.setup_step.daily_goal_minutes' => 'nullable|integer|min:1|max:1440',
        'step_data.setup_step.custom_exam_name' => 'nullable|string|max:255',
        'step_data.setup_step.custom_exam_description' => 'nullable|string|max:1000',
        'step_data.setup_step.custom_exam_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'step_data.setup_step.custom_exam_notes' => 'nullable|string|max:2000',
    ];
}
```

### 5.3 SetupStep.vue の修正

#### 5.3.1 emitStepData() メソッドの修正
```javascript
const emitStepData = () => {
  const data = {
    examType: form.examType,
    examDate: form.examDate || null,
    subjects: form.subjects,
    dailyGoalMinutes: form.dailyGoalMinutes,
    displayName: form.displayName || null,
    occupation: form.occupation || null,
    
    // カスタム試験データの追加
    ...(showCustomExamForm.value && {
      custom_exam_name: customExamForm.value.name,
      custom_exam_description: customExamForm.value.description,
      custom_exam_color: customExamForm.value.color,
      custom_exam_notes: customExamForm.value.exam_notes
    })
  }
  
  emit('step-data', data)
}
```

### 5.2 CustomExamForm.vue (新規コンポーネント)

#### 5.2.1 基本構造
```vue
<template>
  <div class="border-t border-gray-200 pt-6 mt-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">
      カスタム試験の登録
    </h3>
    <!-- フォームフィールド -->
  </div>
</template>
```

#### 5.2.2 バリデーション仕様
- **試験名**: 必須、1-255文字、重複チェック
- **試験日**: 任意、今日以降の日付
- **説明**: 任意、最大1000文字
- **カラー**: 必須、16進数カラーコード
- **メモ**: 任意、最大2000文字

### 5.3 API連携

#### 5.3.1 既存API活用
- **POST /api/user/exam-types**: 試験タイプ作成
- **GET /api/user/exam-types**: 作成済み試験タイプ取得
- バリデーション・セキュリティは既存実装を活用

#### 5.3.2 エラーハンドリング
```javascript
const createCustomExam = async () => {
  try {
    const response = await axios.post('/api/user/exam-types', customExamForm.value)
    if (response.data.success) {
      customExam.value = response.data.exam_type
      form.examType = `custom_${response.data.exam_type.id}`
      showCustomExamForm.value = false
    }
  } catch (error) {
    handleApiError(error)
  }
}
```

## 6. セキュリティ考慮事項

### 6.1 入力値検証
- **フロントエンド**: Vue.js バリデーション
- **バックエンド**: Laravel Request バリデーション
- **SQLインジェクション**: Eloquent ORM による自動エスケープ

### 6.2 認証・認可
- **認証**: Laravel Sanctum による API 認証
- **認可**: ユーザー所有リソースのみアクセス可能
- **CSRF**: SPA のため不要、代わりに Bearer token

### 6.3 データ整合性
- **重複防止**: データベース制約 + アプリケーションレベルチェック
- **文字数制限**: フロント・バック両方で検証
- **XSS対策**: Vue.js の自動エスケープ

## 7. パフォーマンス考慮事項

### 7.1 API最適化
- **リクエスト最小化**: 必要時のみ API 呼び出し
- **キャッシュ**: 作成済み試験タイプの一時保存
- **楽観的更新**: UI の即座更新

### 7.2 レンダリング最適化
- **v-if/v-show**: 適切な条件分岐
- **computed**: 依存関係の最適化
- **遅延ローディング**: 大量データの段階的表示

## 8. 影響範囲分析（修正版）

### 8.1 変更対象ファイル

#### 新規作成
- `resources/js/components/onboarding/CustomExamForm.vue`

#### 重要な修正対象
- **`app/Http/Controllers/Api/OnboardingController.php`** （最重要）
  - `complete()` メソッドの大幅拡張
  - ExamType, StudyGoal作成ロジック追加
- **`app/Http/Requests/OnboardingCompleteRequest.php`**
  - step_data バリデーション追加
- **`resources/js/components/onboarding/steps/SetupStep.vue`**
  - カスタム試験フォーム統合
  - step_data 送信形式修正

#### 軽微な修正
- `resources/js/components/onboarding/steps/CompletionStep.vue` (表示改善)
- `resources/js/components/onboarding/OnboardingModal.vue` (step_data収集の改善)

#### 影響を受けない
- `app/Models/*` (既存モデルをそのまま使用)
- `app/Http/Controllers/Api/UserExamTypeController.php` (既存APIを活用)
- `app/Http/Controllers/Api/StudyGoalController.php` (既存APIを活用)
- `resources/js/pages/Settings.vue` (設定画面は無変更)

### 8.2 データ整合性の保証

#### 8.2.1 オンボーディング→設定画面の連携確認
```
【確認項目】
1. オンボーディング完了後に設定画面の「試験予定日の管理」に作成済みExamTypeが表示される
2. オンボーディング完了後に設定画面の「学習目標設定」に作成済みStudyGoalが表示される
3. 試験日がExamTypeとStudyGoalの両方で一致している
4. 学習目標がアクティブ状態で作成されている
```

#### 8.2.2 既存機能への影響
- **ゼロ影響**: 既存のオンボーディング統計機能
- **ゼロ影響**: 既存のSettings.vue機能
- **拡張のみ**: OnboardingControllerは既存機能を維持しつつ拡張
- **後方互換性**: 既存の examConfig.js は継続利用

## 9. テスト戦略

### 9.1 ユニットテスト
- CustomExamForm.vue のバリデーション
- SetupStep.vue の状態管理
- API レスポンスのハンドリング

### 9.2 統合テスト
- オンボーディングフロー全体
- 設定画面との連携
- API エラー時の挙動

### 9.3 E2E テスト
- 新規ユーザーのオンボーディング体験
- カスタム試験登録から学習開始まで

## 10. 実装順序（修正版）

### Phase 1: バックエンド基盤（最優先）
1. **OnboardingCompleteRequest.php の拡張**
   - step_data バリデーション追加
2. **OnboardingController.php の拡張**
   - `complete()` メソッドの大幅修正
   - ExamType/StudyGoal作成ロジック実装
3. **基本動作確認**
   - 既存オンボーディングが壊れていないか確認

### Phase 2: フロントエンド拡張
1. **CustomExamForm.vue の作成**
   - 基本的なフォームコンポーネント
2. **SetupStep.vue の拡張**
   - CustomExamForm統合
   - step_data送信形式の修正
3. **OnboardingModal.vue の軽微修正**
   - step_data収集の改善

### Phase 3: 統合テスト・検証
1. **データフロー全体の確認**
   - オンボーディング→設定画面の連携
2. **エラーハンドリングの強化**
3. **パフォーマンス・セキュリティチェック**

### ⚠️ 重要な実装注意点
- **Phase 1完了後、必ず設定画面での表示確認を行う**
- **既存オンボーディングユーザーへの影響を最小化**
- **トランザクション処理でデータ整合性を保証**

## 11. リスク分析

### 11.1 技術リスク
- **低**: 既存API活用により技術的新規性は最小限
- **対策**: 段階的実装と十分なテスト

### 11.2 UXリスク
- **中**: オンボーディングの複雑化
- **対策**: 直感的なUI設計と適切な導線

### 11.3 保守リスク
- **低**: 既存パターンに準拠した実装
- **対策**: 明確なコメントとドキュメント

## 12. 今後の拡張性

### 12.1 想定される追加機能
- 試験タイプのテンプレート機能
- 他ユーザーとの試験情報共有
- 試験カテゴリーの階層化

### 12.2 アーキテクチャの拡張性
- コンポーネントの再利用性を重視
- API設計の一貫性維持
- 将来的な機能追加に対応可能な設計

## 13. 検証シナリオ

### 13.1 データ整合性の確認手順
```
【シナリオ1: カスタム試験登録】
1. オンボーディング開始
2. SetupStepで「その他」選択 → カスタム試験フォーム表示
3. 試験名「情報セキュリティマネジメント試験」、試験日「2025-09-01」入力
4. 日次目標「90分」設定
5. オンボーディング完了
6. 設定画面確認:
   - 試験予定日の管理: 「情報セキュリティマネジメント試験」が表示される
   - 学習目標設定: 日次90分、試験日2025-09-01が設定済み

【シナリオ2: 既存試験選択】
1. SetupStepで「AWS Cloud Practitioner」選択
2. 試験日「2025-08-15」、日次目標「60分」設定
3. オンボーディング完了
4. 設定画面確認:
   - 試験予定日の管理: 「AWS Cloud Practitioner」が表示される
   - 学習目標設定: 日次60分、試験日2025-08-15が設定済み
```

### 13.2 失敗パターンとその対策
```
【失敗ケース1】オンボーディング完了後、設定画面が空
→ 原因: OnboardingController.complete()でリソース作成していない
→ 対策: 修正版設計の通りExamType/StudyGoal作成を必須化

【失敗ケース2】試験日がExamTypeとStudyGoalで不一致
→ 原因: データ複製時の不整合
→ 対策: 単一の$setupData['exam_date']から両方に設定

【失敗ケース3】複数の学習目標がアクティブになる
→ 原因: 既存目標の無効化処理なし
→ 対策: createStudyGoal()で既存をis_active=falseに更新
```

---

## 📋 要約

この修正版設計書の**最重要ポイント**:

1. **OnboardingController.complete()の拡張が必須**
   - 現在は統計のみ保存、実際のリソースを作成していない
   - ExamType・StudyGoal作成ロジックを追加

2. **データ整合性の保証**
   - オンボーディング→設定画面の完全連携
   - 試験日、学習目標の一貫性確保

3. **実装優先順位**
   - バックエンド修正が最優先
   - フロントエンド拡張は後から

4. **検証必須項目**
   - オンボーディング完了後、設定画面で登録済みデータが表示される

この設計書は、保守性・可読性・セキュリティ・パフォーマンス・影響範囲を総合的に考慮し、**オンボーディング→設定画面のデータ整合性を完全に保証**することを目的としています。