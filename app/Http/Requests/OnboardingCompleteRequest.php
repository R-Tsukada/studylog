<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingCompleteRequest extends FormRequest
{
    public function rules(): array
    {
        $totalSteps = config('onboarding.total_steps', 4);
        $maxFeedbackLength = config('onboarding.max_feedback_length', 1000);

        return [
            'completed_steps' => 'nullable|array',
            'completed_steps.*' => [
                'integer',
                'min:1',
                "max:{$totalSteps}",
            ],
            'total_time_spent' => 'nullable|integer|min:0|max:86400', // 最大24時間
            'step_times' => 'nullable|array',
            'step_times.*' => 'integer|min:0|max:3600', // 各ステップ最大1時間
            'feedback' => "nullable|string|max:{$maxFeedbackLength}",
        ];
    }

    public function messages(): array
    {
        return [
            'total_time_spent.max' => '学習時間が長すぎます',
            'feedback.max' => 'フィードバックは'.config('onboarding.max_feedback_length', 1000).'文字以内で入力してください',
        ];
    }
}
