<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'url', 'max:255'],

            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];

    }

    public function messages(): array
    {
        return [
            // タイトル
            'title.required' => 'タイトルを入力してください',
            'title.string' => 'タイトルは文字列で入力してください',
            'title.max' => 'タイトルは255文字以内で入力してください',

            // 著者
            'author.required' => '著者名を入力してください',
            'author.string' => '著者名は文字列で入力してください',
            'author.max' => '著者名は255文字以内で入力してください',

            // ISBN
            'isbn.required' => 'ISBNを入力してください',
            'isbn.digits' => 'ISBNは13桁で入力してください',
            'isbn.unique' => 'ISBNは既に登録されています',

            // 出版日
            'published_date.required' => '出版日を入力してください',
            'published_date.date' => '出版日は正しい日付で入力してください',

            // 説明
            'description.string' => '説明は文字列で入力してください',

            // 画像URL
            'image_url.string' => '画像URLは文字列で入力してください',
            'image_url.url' => '画像URLは正しいURL形式で入力してください',
            'image_url.max' => '画像URLは255文字以内で入力してください',

            // ジャンル
            'genres.required' => 'ジャンルを1つ以上選択してください',
            'genres.array' => 'ジャンルは配列形式で指定してください',
            'genres.min' => 'ジャンルを1つ以上選択してください',
            'genres.*.integer' => 'ジャンルIDは整数で指定してください',
            'genres.*.exists' => '選択されたジャンルは存在しません',
        ];
    }
}
