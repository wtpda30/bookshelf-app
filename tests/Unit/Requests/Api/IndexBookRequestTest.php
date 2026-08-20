<?php

namespace Tests\Unit\Requests\Api;

use App\Http\Requests\Api\IndexBookRequest;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IndexBookRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正常な検索条件がバリデーションを通過すること
     */
    public function test_valid_index_parameters_pass_validation(): void
    {
        $genre = Genre::factory()->create();

        $data = [
            'keyword' => 'Laravel',
            'genre_id' => $genre->id,
            'page' => 1,
            'per_page' => 20,
        ];

        $request = new IndexBookRequest;

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * 検索条件を何も指定しなくても通過すること
     */
    public function test_empty_parameters_pass_validation(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            [],
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * キーワードが文字列でない場合は失敗すること
     */
    public function test_keyword_must_be_string(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            ['keyword' => ['Laravel']],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('keyword', $validator->errors()->toArray());
    }

    /**
     * キーワードが255文字を超える場合は失敗すること
     */
    public function test_keyword_must_not_exceed_255_characters(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            ['keyword' => str_repeat('あ', 256)],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('keyword', $validator->errors()->toArray());
    }

    /**
     * 存在しないジャンルIDでは失敗すること
     */
    public function test_genre_id_must_exist(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            ['genre_id' => 999999],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('genre_id', $validator->errors()->toArray());
    }

    /**
     * ページ番号が0以下の場合は失敗すること
     */
    public function test_page_must_be_at_least_one(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            ['page' => 0],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('page', $validator->errors()->toArray());
    }

    /**
     * 1ページの件数が0以下の場合は失敗すること
     */
    public function test_per_page_must_be_at_least_one(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            ['per_page' => 0],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }

    /**
     * 1ページの件数が100を超える場合は失敗すること
     */
    public function test_per_page_must_not_exceed_100(): void
    {
        $request = new IndexBookRequest;

        $validator = Validator::make(
            ['per_page' => 101],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }
}
