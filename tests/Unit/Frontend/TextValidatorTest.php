<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

/**
 * フロントエンドのテキストバリデーターのテストケース
 * 
 * JavaScriptのTextValidatorクラスの動作をPHPでシミュレートしてテスト
 * 主要なバリデーションロジックの正確性を検証
 */
class TextValidatorTest extends TestCase
{
    /**
     * 基本的なバリデーションルールをテスト
     */
    public function test_basic_validation_rules()
    {
        // HTMLタグ文字の検出
        $this->assertTrue($this->hasDisallowedCharacters('<script>'));
        $this->assertTrue($this->hasDisallowedCharacters('Hello > World'));
        $this->assertTrue($this->hasDisallowedCharacters('数学では a < b です'));

        // HTMLエンティティ文字の検出
        $this->assertTrue($this->hasDisallowedCharacters('Tom & Jerry'));
        $this->assertTrue($this->hasDisallowedCharacters('A&B'));

        // クォート文字の検出
        $this->assertTrue($this->hasDisallowedCharacters('He said "Hello"'));
        $this->assertTrue($this->hasDisallowedCharacters("It's working"));

        // 正常なテキスト
        $this->assertFalse($this->hasDisallowedCharacters('正常なテキストです'));
        $this->assertFalse($this->hasDisallowedCharacters('This is normal text.'));
        $this->assertFalse($this->hasDisallowedCharacters('日本語も大丈夫です！'));
    }

    /**
     * エッジケースのテスト
     */
    public function test_edge_cases()
    {
        // 空文字列
        $this->assertFalse($this->hasDisallowedCharacters(''));
        
        // null値
        $this->assertFalse($this->hasDisallowedCharacters(null));
        
        // 空白のみ
        $this->assertFalse($this->hasDisallowedCharacters('   '));
        
        // 改行文字
        $this->assertFalse($this->hasDisallowedCharacters("テスト\nテキスト"));
        
        // 長いテキスト
        $longText = str_repeat('あ', 2000);
        $this->assertFalse($this->hasDisallowedCharacters($longText));
        
        // 無効文字を含む長いテキスト
        $longTextWithInvalid = str_repeat('あ', 1000) . '<script>' . str_repeat('い', 1000);
        $this->assertTrue($this->hasDisallowedCharacters($longTextWithInvalid));
    }

    /**
     * 複数の無効文字パターンのテスト
     */
    public function test_multiple_invalid_patterns()
    {
        $this->assertTrue($this->hasDisallowedCharacters('<div>Hello & "World"</div>'));
        $this->assertTrue($this->hasDisallowedCharacters("Tom's & Jerry's"));
        $this->assertTrue($this->hasDisallowedCharacters('複数<>の&"無効\'文字'));
    }

    /**
     * 日本語特有のケースのテスト
     */
    public function test_japanese_specific_cases()
    {
        // 日本語の引用符（全角）は許可
        $this->assertFalse($this->hasDisallowedCharacters('彼は「こんにちは」と言いました'));
        
        // 日本語の感嘆符・疑問符は許可
        $this->assertFalse($this->hasDisallowedCharacters('素晴らしい！本当ですか？'));
        
        // 日本語と無効文字の混在
        $this->assertTrue($this->hasDisallowedCharacters('日本語<script>テスト'));
        $this->assertTrue($this->hasDisallowedCharacters('彼は"Hello"と言った'));
    }

    /**
     * サニタイゼーション機能のテスト
     */
    public function test_sanitization()
    {
        $this->assertEquals('Hello  World  Test', $this->sanitizeText('Hello < World > Test'));
        $this->assertEquals('Tom  Jerry', $this->sanitizeText('Tom & Jerry'));
        $this->assertEquals('He said Hello', $this->sanitizeText('He said "Hello"'));
        $this->assertEquals('Its working', $this->sanitizeText("It's working"));
        
        // 複数の無効文字
        $this->assertEquals('Clean  text', $this->sanitizeText('<Clean> & "text"'));
        
        // 日本語テキストのサニタイゼーション
        $this->assertEquals('日本語scriptテスト', $this->sanitizeText('日本語<script>テスト'));
    }

    /**
     * パフォーマンステスト（大量データ）
     */
    public function test_performance_with_large_data()
    {
        $largeText = str_repeat('この文章は長いテストデータです。', 1000);
        
        $startTime = microtime(true);
        $this->hasDisallowedCharacters($largeText);
        $endTime = microtime(true);
        
        // 実行時間が1秒を超えないことを確認
        $this->assertLessThan(1.0, $endTime - $startTime, 'バリデーションが1秒以内に完了すること');
    }

    /**
     * ヘルパーメソッド: 無効文字チェック（JavaScriptロジックをPHPで再現）
     */
    private function hasDisallowedCharacters($text): bool
    {
        if (!$text || !is_string($text)) {
            return false;
        }

        // JavaScriptのTextValidatorと同じルールを適用
        $patterns = [
            '/[<>]/',      // html_tags
            '/[&]/',       // html_entities  
            '/["\']/',     // quotes
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ヘルパーメソッド: テキストサニタイゼーション
     */
    private function sanitizeText($text): string
    {
        if (!$text || !is_string($text)) {
            return '';
        }

        // 無効文字を除去
        return preg_replace('/[<>&"\']/', '', $text);
    }
}