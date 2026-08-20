<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍登録・更新で使用する正常なデータを作る
     */
    private function validBookData(
        User $user,
        array $genreIds,
        array $override = []
    ): array {
        return array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-01-01',
            'description' => 'テスト用の説明文です。',
            'image_url' => 'https://example.com/book.jpg',
            'genre_ids' => $genreIds,
        ], $override);
    }

    /**
     * 書籍一覧APIが正常に取得できること
     */
    public function test_books_index_can_be_retrieved(): void
    {
        $books = Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books');

        $response
            ->assertOk()
            ->assertJsonPath('message', '取得成功')
            ->assertJsonCount(3, 'data');

        $response->assertJsonStructure([
            'message',

            'data' => [
                '*' => [
                    'id',
                    'title',
                    'author',
                    'isbn',
                    'published_date',
                    'description',
                    'image_url',
                    'genres',
                    'average_rating',
                    'reviews_count',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);

        $this->assertSame(
            $books->sortBy('id')->pluck('id')->values()->all(),
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    /**
     * 書籍一覧がID昇順で返ること
     */
    public function test_books_are_returned_in_id_ascending_order(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/books');

        $ids = collect($response->json('data.data'))
            ->pluck('id')
            ->all();

        $sortedIds = $ids;
        sort($sortedIds);

        $this->assertSame($sortedIds, $ids);
    }

    /**
     * 一覧にジャンル、平均評価、レビュー件数が含まれること
     */
    public function test_books_index_contains_genres_average_rating_and_review_count(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book->genres()->attach($genres->pluck('id'));

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 3,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 4,
        ]);

        $response = $this->getJson('/api/v1/books');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.average_rating', 3.5)
            ->assertJsonPath('data.0.reviews_count', 2)
            ->assertJsonCount(2, 'data.0.genres');
    }

    /**
     * タイトルの一部分で検索できること
     */
    public function test_books_can_be_searched_by_partial_title(): void
    {
        $matchedBook = Book::factory()->create([
            'title' => 'Laravel入門',
            'author' => '山田太郎',
        ]);

        Book::factory()->create([
            'title' => 'PHP実践',
            'author' => '佐藤花子',
        ]);

        $response = $this->getJson(
            '/api/v1/books?keyword=Laravel'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchedBook->id);
    }

    /**
     * 著者名の一部分で検索できること
     */
    public function test_books_can_be_searched_by_partial_author(): void
    {
        $matchedBook = Book::factory()->create([
            'title' => 'テスト書籍A',
            'author' => '山田太郎',
        ]);

        Book::factory()->create([
            'title' => 'テスト書籍B',
            'author' => '佐藤花子',
        ]);

        $response = $this->getJson(
            '/api/v1/books?keyword=山田'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchedBook->id);
    }

    /**
     * 空文字のキーワードでは全件が対象になること
     */
    public function test_empty_keyword_does_not_filter_books(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->getJson(
            '/api/v1/books?keyword='
        );

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * ジャンルで書籍を絞り込めること
     */
    public function test_books_can_be_filtered_by_genre(): void
    {
        $targetGenre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();

        $matchedBook = Book::factory()->create();
        $otherBook = Book::factory()->create();

        $matchedBook->genres()->attach($targetGenre->id);
        $otherBook->genres()->attach($otherGenre->id);

        $response = $this->getJson(
            "/api/v1/books?genre_id={$targetGenre->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchedBook->id);
    }

    /**
     * 一覧がデフォルト20件でページネーションされること
     */
    public function test_books_index_is_paginated_by_twenty_by_default(): void
    {
        Book::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/books');

        $response
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25);
    }

    /**
     * per_pageで1ページあたりの件数を変更できること
     */
    public function test_per_page_can_be_changed(): void
    {
        Book::factory()->count(12)->create();

        $response = $this->getJson(
            '/api/v1/books?per_page=5'
        );

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 12);
    }

    /**
     * 検索条件を付けてもページネーションされること
     */
    public function test_search_results_are_paginated(): void
    {
        Book::factory()->count(7)->create([
            'title' => 'Laravelテスト',
        ]);

        Book::factory()->count(3)->create([
            'title' => 'PHPテスト',
        ]);

        $response = $this->getJson(
            '/api/v1/books?keyword=Laravel&per_page=3&page=2'
        );

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 7);
    }

    /**
     * pageが0以下の場合は422が返ること
     */
    public function test_page_must_be_at_least_one(): void
    {
        $response = $this->getJson(
            '/api/v1/books?page=0'
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    /**
     * per_pageが0以下の場合は422が返ること
     */
    public function test_per_page_must_be_at_least_one(): void
    {
        $response = $this->getJson(
            '/api/v1/books?per_page=0'
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * per_pageが101以上の場合は422が返ること
     */
    public function test_per_page_must_not_exceed_one_hundred(): void
    {
        $response = $this->getJson(
            '/api/v1/books?per_page=101'
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * 存在しないジャンルIDで422が返ること
     */
    public function test_nonexistent_genre_id_returns_422(): void
    {
        $response = $this->getJson(
            '/api/v1/books?genre_id=999999'
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genre_id');
    }

    /**
     * 書籍詳細APIが正常に取得できること
     */
    public function test_book_detail_can_be_retrieved(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book->genres()->attach($genres->pluck('id'));

        $review = Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '読みやすかったです。',
        ]);

        $response = $this->getJson(
            "/api/v1/books/{$book->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', '取得成功')
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.reviews.0.id', $review->id)
            ->assertJsonPath(
                'data.reviews.0.user_id',
                $review->user_id
            )
            ->assertJsonPath('data.average_rating', 4)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonCount(2, 'data.genres');
    }

    /**
     * 詳細APIにはレビューのいいね件数を含めないこと
     */
    public function test_book_detail_does_not_contain_review_like_count(): void
    {
        $book = Book::factory()->create();

        Review::factory()->create([
            'book_id' => $book->id,
        ]);

        $response = $this->getJson(
            "/api/v1/books/{$book->id}"
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.reviews.0.likes_count'
            );
    }

    /**
     * 存在しない書籍詳細で404が返ること
     */
    public function test_nonexistent_book_detail_returns_404(): void
    {
        $response = $this->getJson(
            '/api/v1/books/999999'
        );

        $response->assertNotFound();
    }

    /**
     * 正常なデータで書籍を登録できること
     */
    public function test_book_can_be_created(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $genres = Genre::factory()->count(2)->create();

        $data = $this->validBookData(
            $user,
            $genres->pluck('id')->all()
        );

        $response = $this->postJson('/api/v1/books', $data);

        $response
            ->assertCreated()
            ->assertJsonPath('message', '登録成功')
            ->assertJsonPath('data.title', 'テスト書籍')
            ->assertJsonPath('data.author', 'テスト著者')
            ->assertJsonPath('data.isbn', '9781234567890')
            ->assertJsonCount(2, 'data.genres');

        $this->assertDatabaseHas('books', [
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
        ]);

        foreach ($genres as $genre) {

            $this->assertDatabaseHas('book_genres', [
                'book_id' => $response->json('data.id'),
                'genre_id' => $genre->id,
            ]);
        }
    }

    /**
     * 書籍登録時の不正な値で422が返ること
     */
    public function test_book_creation_validation_error_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->postJson(
            '/api/v1/books',
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'author',
                'isbn',
                'published_date',
                'genre_ids',
            ]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * ISBNが13桁でない場合は422が返ること
     */
    public function test_isbn_must_be_thirteen_digits_when_creating_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'isbn' => '123456789012',
            ]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    /**
     * ISBNが数字以外を含む場合は422が返ること
     */
    public function test_isbn_must_contain_only_digits_when_creating_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'isbn' => '978123456789A',
            ]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    /**
     * 出版日が日付形式でない場合は422が返ること
     */
    public function test_published_date_must_be_valid_date_when_creating_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'published_date' => '日付ではありません',
            ]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('published_date');
    }

    /**
     * 画像URLがURL形式でない場合は422が返ること
     */
    public function test_image_url_must_be_valid_url_when_creating_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'image_url' => 'URLではありません',
            ]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image_url');
    }

    /**
     * 画像URLが255文字を超える場合は422が返ること
     */
    public function test_image_url_must_not_exceed_255_characters_when_creating_book(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'image_url' => 'https://example.com/'.str_repeat('a', 240),
            ]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image_url');
    }

    /**
     * 重複したISBNでは登録できないこと
     */
    public function test_duplicate_isbn_cannot_be_created(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        Book::factory()->create([
            'isbn' => '9781234567890',
        ]);

        $data = $this->validBookData(
            $user,
            [$genre->id]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    /**
     * 存在しないジャンルIDでは登録できないこと
     */
    public function test_book_cannot_be_created_with_nonexistent_genre(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $data = $this->validBookData(
            $user,
            [999999]
        );

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genre_ids.0');
    }

    /**
     * 正常なデータで書籍とジャンルを更新できること
     */
    public function test_book_can_be_updated(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $book = Book::factory()->create([

            'user_id' => $user->id,

            'isbn' => '9781111111111',

        ]);

        $oldGenre = Genre::factory()->create();

        $newGenres = Genre::factory()->count(2)->create();

        $book->genres()->attach($oldGenre->id);

        $data = $this->validBookData(
            $user,
            $newGenres->pluck('id')->all(),
            [
                'title' => '更新後の書籍',
                'author' => '更新後の著者',
                'isbn' => '9782222222222',
            ]
        );

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            $data
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', '更新成功')
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', '更新後の書籍')
            ->assertJsonPath('data.author', '更新後の著者')
            ->assertJsonPath('data.isbn', '9782222222222')
            ->assertJsonCount(2, 'data.genres');

        $this->assertDatabaseHas('books', [

            'id' => $book->id,
            'title' => '更新後の書籍',
            'author' => '更新後の著者',
            'isbn' => '9782222222222',
        ]);

        $this->assertDatabaseMissing('book_genres', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);

        foreach ($newGenres as $genre) {

            $this->assertDatabaseHas('book_genres', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    /**
     * 更新対象自身のISBNはそのまま使用できること
     */
    public function test_current_book_isbn_is_allowed_when_updating(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
            'isbn' => '9781234567890',
        ]);

        $book->genres()->attach($genre->id);

        $data = $this->validBookData(

            $user,
            [$genre->id],
            [
                'title' => 'タイトルのみ変更',
                'isbn' => $book->isbn,
            ]
        );

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            $data
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.isbn',
                '9781234567890'
            );

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'タイトルのみ変更',
            'isbn' => '9781234567890',
        ]);
    }

    /**
     * 別の書籍が使用しているISBNでは更新できないこと
     */
    public function test_isbn_used_by_another_book_cannot_be_used_for_update(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
            'isbn' => '9781111111111',

        ]);

        $otherBook = Book::factory()->create([
            'isbn' => '9782222222222',
        ]);

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'isbn' => $otherBook->isbn,
            ]

        );

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            $data
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'isbn' => '9781111111111',
        ]);
    }

    /**
     * 存在しない書籍の更新で404が返ること
     */
    public function test_updating_nonexistent_book_returns_404(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id]
        );

        $response = $this->putJson(
            '/api/v1/books/999999',
            $data
        );

        $response->assertNotFound();
    }

    /**
     * 書籍を削除できること
     */
    public function test_book_can_be_deleted(): void
    {

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/books/{$book->id}"
        );

        $response
            ->assertNoContent()
            ->assertContent('');

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }

    /**
     * 書籍削除時に関連データも削除されること
     */
    public function test_related_data_is_deleted_when_book_is_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $genre = Genre::factory()->create();

        $favoriteUser = User::factory()->create();

        $book->genres()->attach($genre->id);

        $book->favoriteUsers()->attach($favoriteUser->id);

        $review = Review::factory()->create([
            'book_id' => $book->id,
        ]);

        $this->deleteJson(
            "/api/v1/books/{$book->id}"
        )->assertNoContent();

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        $this->assertDatabaseMissing('book_genres', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);

        $this->assertDatabaseMissing('favorites', [
            'book_id' => $book->id,
            'user_id' => $favoriteUser->id,
        ]);
    }

    /**
     * 存在しない書籍の削除で404が返ること
     */
    public function test_deleting_nonexistent_book_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->deleteJson(
            '/api/v1/books/999999'
        );

        $response->assertNotFound();
    }
}
