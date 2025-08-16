<?php

namespace Tests\Unit\Config;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ExamConfigTest extends TestCase
{
    /**
     * テストメソッド
     */
    #[Test]
    public function exam_config_has_required_structure()
    {
        $config = config('exams');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('types', $config);
        $this->assertArrayHasKey('subjects', $config);
        $this->assertArrayHasKey('validation', $config);
        $this->assertArrayHasKey('categories', $config);
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function exam_types_have_required_fields()
    {
        $examTypes = config('exams.types');

        $this->assertIsArray($examTypes);
        $this->assertNotEmpty($examTypes);

        foreach ($examTypes as $code => $examType) {
            $this->assertIsString($code);
            $this->assertArrayHasKey('name', $examType);
            $this->assertArrayHasKey('description', $examType);
            $this->assertArrayHasKey('category', $examType);
            $this->assertArrayHasKey('color', $examType);

            $this->assertIsString($examType['name']);
            $this->assertIsString($examType['description']);
            $this->assertIsString($examType['category']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $examType['color']);
        }
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function validation_config_has_required_values()
    {
        $validation = config('exams.validation');

        $this->assertIsArray($validation);
        $this->assertArrayHasKey('exam_name_max_length', $validation);
        $this->assertArrayHasKey('exam_description_max_length', $validation);
        $this->assertArrayHasKey('exam_notes_max_length', $validation);
        $this->assertArrayHasKey('subject_name_max_length', $validation);
        $this->assertArrayHasKey('max_custom_subjects', $validation);
        $this->assertArrayHasKey('exam_code_base_length', $validation);

        // バリデーション値が適切な範囲内であることを確認
        $this->assertGreaterThan(0, $validation['exam_name_max_length']);
        $this->assertGreaterThan(0, $validation['exam_description_max_length']);
        $this->assertGreaterThan(0, $validation['exam_notes_max_length']);
        $this->assertGreaterThan(0, $validation['subject_name_max_length']);
        $this->assertGreaterThan(0, $validation['max_custom_subjects']);
        $this->assertGreaterThan(0, $validation['exam_code_base_length']);

        $this->assertLessThanOrEqual(50, $validation['max_custom_subjects']);
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function subjects_config_matches_exam_types()
    {
        $examTypes = array_keys(config('exams.types'));
        $subjectConfigs = array_keys(config('exams.subjects'));

        // 全ての試験タイプに対応する学習分野設定があることを確認
        foreach ($examTypes as $examType) {
            $this->assertArrayHasKey($examType, config('exams.subjects'),
                "試験タイプ '{$examType}' に対応する学習分野設定が見つかりません");
        }
    }

    /**
     * テストメソッド
     */
    #[Test]
    public function required_exam_types_are_present()
    {
        $examTypes = array_keys(config('exams.types'));

        $requiredTypes = [
            'jstqb_fl',
            'ipa_fe',
            'toeic',
            'fp',
            'aws_clf',
            'aws_foundational',
            'aws_associate',
        ];

        foreach ($requiredTypes as $requiredType) {
            $this->assertArrayHasKey($requiredType, config('exams.types'),
                "必須試験タイプ '{$requiredType}' が設定に含まれていません");
        }
    }
}
