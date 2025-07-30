<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class UserRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => $this->getNicknameRules(),
            'email' => $this->getEmailRules(),
            'password' => $this->getPasswordRules(),
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'ニックネームは必須です',
            'nickname.min' => 'ニックネームは2文字以上で入力してください',
            'nickname.max' => 'ニックネームは50文字以内で入力してください',
            'nickname.regex' => 'ニックネームは英数字、ひらがな、カタカナ、漢字のみ使用できます',
            'email.required' => 'メールアドレスは必須です',
            'email.email' => '正しいメールアドレス形式で入力してください',
            'email.unique' => 'このメールアドレスは既に登録されています',
            'email.ends_with' => '有効なドメインのメールアドレスを入力してください（.com, .net, .org, .jp, .edu, .gov）',
            'password.required' => 'パスワードは必須です',
            'password.confirmed' => 'パスワード確認が一致しません',
        ];
    }

    /**
     * Get nickname validation rules.
     */
    private function getNicknameRules(): array
    {
        return [
            'required',
            'string',
            'min:2',
            'max:50',
            'regex:/^[a-zA-Z0-9ぁ-んァ-ンー一-龠]+$/u',
        ];
    }

    /**
     * Get email validation rules.
     */
    private function getEmailRules(): array
    {
        return [
            'required',
            'string',
            'email:rfc',
            'max:255',
            'unique:users',
            'ends_with:.com,.net,.org,.jp,.edu,.gov',
        ];
    }

    /**
     * Get password validation rules.
     */
    private function getPasswordRules(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(8)->letters()->numbers()->symbols(),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'バリデーションエラー',
            'errors' => $validator->errors(),
        ], 422));
    }
}
