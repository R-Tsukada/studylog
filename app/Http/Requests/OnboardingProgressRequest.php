<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingProgressRequest extends FormRequest
{
    public function rules(): array
    {
        $totalSteps = config('onboarding.total_steps', 4);
        $maxStepDataSize = config('onboarding.max_step_data_size', 10240);

        return [
            'current_step' => [
                'required',
                'integer',
                'min:1',
                "max:{$totalSteps}",
            ],
            'completed_steps' => 'nullable|array',
            'completed_steps.*' => [
                'integer',
                'min:1',
                "max:{$totalSteps}",
            ],
            'step_data' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($maxStepDataSize) {
                    if (json_encode($value) && strlen(json_encode($value)) > $maxStepDataSize) {
                        $fail('The step data is too large.');
                    }
                },
            ],
            'timestamp' => 'nullable|string|date_format:Y-m-d\TH:i:s\Z',
        ];
    }

    public function messages(): array
    {
        return [
            'current_step.required' => '現在のステップは必須です',
            'current_step.integer' => 'ステップは数値で指定してください',
            'current_step.min' => 'ステップは1以上で指定してください',
            'current_step.max' => 'ステップは'.config('onboarding.total_steps', 4).'以下で指定してください',
            'completed_steps.array' => '完了ステップは配列で指定してください',
        ];
    }
}
