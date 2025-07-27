<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingSkipRequest extends FormRequest
{
    public function rules(): array
    {
        $totalSteps = config('onboarding.total_steps', 4);

        return [
            'current_step' => [
                'nullable',
                'integer',
                'min:1',
                "max:{$totalSteps}",
            ],
            'reason' => [
                'nullable',
                'string',
                'max:100',
                Rule::in([
                    'user_choice',
                    'too_complex',
                    'already_familiar',
                    'time_constraint',
                    'technical_issue',
                ]),
            ],
            'completed_steps' => 'nullable|array',
            'completed_steps.*' => [
                'integer',
                'min:1',
                "max:{$totalSteps}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.in' => '無効なスキップ理由です',
        ];
    }
}
