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
            'step_data.setup_step.custom_exam_subjects' => 'nullable|array|max:10',
            'step_data.setup_step.custom_exam_subjects.*.name' => 'required|string|max:255',
            'step_data.setup_step.custom_subjects' => 'nullable|array|max:10',
            'step_data.setup_step.custom_subjects.*.name' => 'required|string|max:255',
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
