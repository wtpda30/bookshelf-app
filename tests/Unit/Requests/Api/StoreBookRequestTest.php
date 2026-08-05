<?php

namespace Tests\Unit\Requests\Api;

use App\Http\Requests\Api\StoreBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreBookRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正常なデータがバリデーションを通過すること
     */
    public function test_valid_book_data_passes_validation(): void
    {
        $user = User::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $data = $this->validData($user, $genres->pluck('id')->all());

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * 任意項目がnullでも通過すること
     */
    public function test_nullable_fields_can_be_null(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData($user, [$genre->id]);

        $data['description'] = null;
        $data['image_url'] = null;

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * 必須項目が未入力の場合は失敗すること
     */
    public function test_required_fields_are_required(): void
    {
        $request = new StoreBookRequest();

        $validator = Validator::make(
            [],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('user_id', $errors);
        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('author', $errors);
        $this->assertArrayHasKey('isbn', $errors);
        $this->assertArrayHasKey('published_date', $errors);
        $this->assertArrayHasKey('genre_ids', $errors);
    }

    /**
     * 存在しないユーザーIDでは失敗すること
     */
    public function test_user_id_must_exist(): void
    {
        $genre = Genre::factory()->create();

        $data = [
            'user_id' => 999999,
            'title' => 'Laravel入門',
            'author' => '山田太郎',
            'isbn' => '9781234567890',
            'published_date' => '2026-08-01',
            'description' => null,
            'image_url' => null,
            'genre_ids' => [$genre->id],
        ];

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user_id', $validator->errors()->toArray());
    }

    /**
     * ISBNが13桁でない場合は失敗すること
     */
    public function test_isbn_must_be_13_digits(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData($user, [$genre->id]);
        $data['isbn'] = '123456789012';

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('isbn', $validator->errors()->toArray());
    }

    /**
     * ISBNに数字以外が含まれる場合は失敗すること
     */
    public function test_isbn_must_contain_only_digits(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData($user, [$genre->id]);
        $data['isbn'] = '978123456789A';

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('isbn', $validator->errors()->toArray());
    }

    /**
     * 既に登録されているISBNでは失敗すること
     */
    public function test_isbn_must_be_unique(): void
    {
        $existingBook = Book::factory()->create([
            'isbn' => '9781234567890',
        ]);

        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData($user, [$genre->id]);
        $data['isbn'] = $existingBook->isbn;

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('isbn', $validator->errors()->toArray());
    }

    /**
     * 出版日が日付形式でない場合は失敗すること
     */
    public function test_published_date_must_be_valid_date(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData($user, [$genre->id]);
        $data['published_date'] = '日付ではありません';

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'published_date',
            $validator->errors()->toArray()
        );
    }

    /**
     * 画像URLがURL形式でない場合は失敗すること
     */
    public function test_image_url_must_be_valid_url(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData($user, [$genre->id]);
        $data['image_url'] = 'invalid-url';

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'image_url',
            $validator->errors()->toArray()
        );
    }

    /**
     * ジャンルが1件も選択されていない場合は失敗すること
     */
    public function test_at_least_one_genre_is_required(): void
    {
        $user = User::factory()->create();

        $data = $this->validData($user, []);
        $data['genre_ids'] = [];

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'genre_ids',
            $validator->errors()->toArray()
        );
    }

    /**
     * 存在しないジャンルIDでは失敗すること
     */
    public function test_each_genre_id_must_exist(): void
    {
        $user = User::factory()->create();

        $data = $this->validData($user, [999999]);

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'genre_ids.0',
            $validator->errors()->toArray()
        );
    }

    /**
     * 同じジャンルIDが重複している場合は失敗すること
     */
    public function test_genre_ids_must_not_contain_duplicates(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData(
            $user,
            [$genre->id, $genre->id]
        );

        $request = new StoreBookRequest();

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'genre_ids.0',
            $validator->errors()->toArray()
        );
    }

    /**
     * 正常な書籍データを作成する
     */
    private function validData(User $user, array $genreIds): array
    {
        return [
            'user_id' => $user->id,
            'title' => 'Laravel入門',
            'author' => '山田太郎',
            'isbn' => '9781234567890',
            'published_date' => '2026-08-01',
            'description' => 'Laravelについて解説した書籍です。',
            'image_url' => 'https://example.com/book.jpg',
            'genre_ids' => $genreIds,
        ];
    }
}
