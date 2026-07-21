<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
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
        $genre = $this->route('genre');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres', 'name')->ignore($genre),
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名を入力してください',
            'name.string'   => 'ジャンル名は文字列で入力してください',
            'name.max'      => 'ジャンル名は255文字以内で入力してください',
            'name.unique'   => 'ジャンル名は既に登録されています',
        ];
    }
}
