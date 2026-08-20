<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    /**
     * 基礎編では認証・認可を行わないのでtrue
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
        $book = $this->route('book');

        return [

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'author' => [
                'required',
                'string',
                'max:255',
            ],

            'isbn' => [
                'required',
                'string',
                'digits:13',
                // 更新対象自身のISBNは重複扱いにしない
                Rule::unique('books', 'isbn')->ignore($book),
            ],

            'published_date' => [
                'required',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'image_url' => [
                'nullable',
                'string',
                'url',
                'max:255',
            ],

            'genre_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'genre_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:genres,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'title.required' => 'タイトルを入力してください。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',

            'author.required' => '著者名を入力してください。',
            'author.string' => '著者名は文字列で入力してください。',
            'author.max' => '著者名は255文字以内で入力してください。',

            'isbn.required' => 'ISBNを入力してください。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'ISBNは既に登録されています。',

            'published_date.required' => '出版日を入力してください。',
            'published_date.date' => '出版日は正しい日付で入力してください。',

            'description.string' => '説明文は文字列で入力してください。',

            'image_url.string' => '画像URLは文字列で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',

            'genre_ids.required' => 'ジャンルを1つ以上選択してください。',
            'genre_ids.array' => 'ジャンルは配列形式で指定してください。',
            'genre_ids.min' => 'ジャンルを1つ以上選択してください。',

            'genre_ids.*.required' => 'ジャンルIDを指定してください。',
            'genre_ids.*.integer' => 'ジャンルIDは整数で指定してください。',
            'genre_ids.*.distinct' => '同じジャンルを重複して指定することはできません。',
            'genre_ids.*.exists' => '選択されたジャンルは存在しません。',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'author' => '著者名',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明文',
            'image_url' => '画像URL',
            'genre_ids' => 'ジャンル',
            'genre_ids.*' => 'ジャンルID',
        ];
    }
}
