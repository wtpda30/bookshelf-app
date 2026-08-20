<?php

namespace Tests\Unit\Requests\Api;

use App\Http\Requests\Api\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class UpdateBookRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正常な更新データがバリデーションを通過すること
     */
    public function test_valid_update_data_passes_validation(): void
    {
        $book = Book::factory()->create([
            'isbn' => '9781234567890',
        ]);

        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $request = $this->createRequest($book);

        $validator = Validator::make(
            $this->validData(
                $user,
                [$genre->id],
                $book->isbn
            ),
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * 更新対象自身と同じISBNを使用できること
     */
    public function test_current_book_isbn_is_allowed(): void
    {
        $book = Book::factory()->create([
            'isbn' => '9781234567890',
        ]);

        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData(
            $user,
            [$genre->id],
            $book->isbn
        );

        $request = $this->createRequest($book);

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertFalse($validator->fails());
    }

    /**
     * 別の書籍が使用しているISBNには変更できないこと
     */
    public function test_isbn_used_by_another_book_is_rejected(): void
    {
        $updateTarget = Book::factory()->create([
            'isbn' => '9781234567890',
        ]);

        $anotherBook = Book::factory()->create([
            'isbn' => '9780987654321',
        ]);

        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData(
            $user,
            [$genre->id],
            $anotherBook->isbn
        );

        $request = $this->createRequest($updateTarget);

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'isbn',
            $validator->errors()->toArray()
        );
    }

    /**
     * 必須項目が未入力の場合は失敗すること
     */
    public function test_required_fields_are_required(): void
    {
        $book = Book::factory()->create();

        $request = $this->createRequest($book);

        $validator = Validator::make(
            [],
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('author', $errors);
        $this->assertArrayHasKey('isbn', $errors);
        $this->assertArrayHasKey('published_date', $errors);
        $this->assertArrayHasKey('genre_ids', $errors);
    }

    /**
     * ISBNが13桁でない場合は失敗すること
     */
    public function test_isbn_must_be_13_digits(): void
    {
        $book = Book::factory()->create();

        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $data = $this->validData(
            $user,
            [$genre->id],
            '123456789012'
        );

        $request = $this->createRequest($book);

        $validator = Validator::make(
            $data,
            $request->rules(),
            $request->messages()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'isbn',
            $validator->errors()->toArray()
        );
    }

    /**
     * 存在しないジャンルIDでは失敗すること
     */
    public function test_each_genre_id_must_exist(): void
    {
        $book = Book::factory()->create();
        $user = User::factory()->create();

        $data = $this->validData(
            $user,
            [999999],
            $book->isbn
        );

        $request = $this->createRequest($book);

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
     * UpdateBookRequestに更新対象書籍を設定する
     */
    private function createRequest(Book $book): UpdateBookRequest
    {
        $request = Mockery::mock(UpdateBookRequest::class)
            ->makePartial();

        $request
            ->shouldReceive('route')
            ->with('book')
            ->andReturn($book);

        return $request;
    }

    /**
     * 正常な更新データを作成する
     */
    private function validData(
        User $user,
        array $genreIds,
        string $isbn
    ): array {
        return [
            'user_id' => $user->id,
            'title' => '更新後タイトル',
            'author' => '更新後著者',
            'isbn' => $isbn,
            'published_date' => '2026-08-01',
            'description' => '更新後の説明です。',
            'image_url' => 'https://example.com/updated.jpg',
            'genre_ids' => $genreIds,
        ];
    }
}
