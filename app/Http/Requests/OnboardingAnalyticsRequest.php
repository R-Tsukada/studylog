<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'start_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after:'.now()->subYear()->toDateString(), // 1年以内
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                'before_or_equal:today',
            ],
            'group_by' => [
                'nullable',
                'string',
                Rule::in(['day', 'week', 'month']),
            ],
            'limit' => 'nullable|integer|min:1|max:1000', // 結果数制限
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.after' => '開始日は1年以内で指定してください',
            'end_date.before_or_equal' => '終了日は今日以前で指定してください',
            'limit.max' => '結果数は1000件以下で指定してください',
        ];
    }

    protected function failedAuthorization()
    {
        abort(403, 'このリソースにアクセスする権限がありません');
    }
}
