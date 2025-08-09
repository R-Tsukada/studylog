<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserFutureVisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // 認証はミドルウェアで行う
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vision_text' => [
                'required',
                'string',
                'min:10',                    // 意味ある文章として最低限の長さ
                'max:2000',                  // ツイートの約10倍程度
                'regex:/^[^<>]*$/',          // HTMLタグのみを除外（XSS対策）
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vision_text.required' => '将来のビジョンを入力してください。',
            'vision_text.string' => '将来のビジョンは文字列で入力してください。',
            'vision_text.min' => '将来のビジョンは10文字以上で入力してください。',
            'vision_text.max' => '将来のビジョンは2000文字以内で入力してください。',
            'vision_text.regex' => '将来のビジョンに使用できない文字が含まれています。',
        ];
    }
}
