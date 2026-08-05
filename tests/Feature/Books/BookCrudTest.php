<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍登録で送信する正常なデータを作成する
     */
    private function validBookData(
        User $user,
        array $genreIds,
        array $overrides = []
    ): array {
        return array_merge([
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-08-03',
            'description' => 'テスト用の書籍説明です。',
            'image_url' => 'https://example.com/book.jpg',
            'genres' => $genreIds,
        ], $overrides);
    }

    /**
     * 認証済みユーザーが書籍登録画面を表示できること
     */
    public function test_authenticated_user_can_display_create_page(): void
    {
        $user = User::factory()->create();

        $genres = Genre::factory()->count(3)->create();

        $response = $this
            ->actingAs($user)
            ->get(route('books.create'));

        $response->assertOk();
        $response->assertViewIs('books.create');
        $response->assertViewHas('genres');

        foreach ($genres as $genre) {
            $response->assertSee($genre->name);
        }
    }

    /**
     * 未認証ユーザーは書籍登録画面を表示できないこと
     */
    public function test_guest_is_redirected_to_login_from_create_page(): void
    {
        $response = $this->get(route('books.create'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 正常な入力値で書籍を登録できること
     */
    public function test_authenticated_user_can_create_book(): void
    {
        $user = User::factory()->create();

        $genres = Genre::factory()->count(2)->create();

        $data = $this->validBookData(
            $user,
            $genres->pluck('id')->all()
        );

        $response = $this
            ->actingAs($user)
            ->post(route('books.store'), $data);

        $book = Book::query()
            ->where('isbn', '9781234567890')
            ->first();

        $this->assertNotNull($book);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-08-03',
            'description' => 'テスト用の書籍説明です。',
            'image_url' => 'https://example.com/book.jpg',
        ]);

        foreach ($genres as $genre) {
            $this->assertDatabaseHas('book_genres', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    /**
     * 不正な入力値では書籍が登録されないこと
     */
    public function test_invalid_data_does_not_create_book(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.create'))
            ->post(route('books.store'), [
                'user_id' => $user->id,
                'title' => '',
                'author' => '',
                'isbn' => '123',
                'published_date' => '出版日ではありません',
                'description' => null,
                'image_url' => 'URLではありません',
                'genres' => [],
            ]);

        $response->assertRedirect(route('books.create'));

        $response->assertSessionHasErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'image_url',
            'genres',
        ]);

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genres', 0);
    }

    /**
     * 書籍登録者本人が編集画面を表示できること
     */
    public function test_owner_can_display_edit_page(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()
            ->for($user)
            ->create([
                'title' => '編集前タイトル',
                'author' => '編集前著者',
                'isbn' => '9781234567890',
            ]);

        $selectedGenres = Genre::factory()->count(2)->create();
        Genre::factory()->create();

        $book->genres()->attach($selectedGenres->pluck('id'));

        $response = $this
            ->actingAs($user)
            ->get(route('books.edit', $book));

        $response->assertOk();
        $response->assertViewIs('books.edit');
        $response->assertViewHas('book');
        $response->assertViewHas('genres');

        $response->assertSee('編集前タイトル');
        $response->assertSee('編集前著者');
        $response->assertSee('9781234567890');
    }

    /**
     * 書籍登録者本人が書籍情報を更新できること
     */
    public function test_owner_can_update_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()
            ->for($user)
            ->create([
                'title' => '更新前タイトル',
                'author' => '更新前著者',
                'isbn' => '9781234567890',
            ]);

        $oldGenres = Genre::factory()->count(2)->create();
        $newGenres = Genre::factory()->count(2)->create();

        $book->genres()->attach($oldGenres->pluck('id'));

        $data = $this->validBookData(
            $user,
            $newGenres->pluck('id')->all(),
            [
                'title' => '更新後タイトル',
                'author' => '更新後著者',
                'isbn' => '9780987654321',
                'published_date' => '2026-07-01',
                'description' => '更新後の説明です。',
                'image_url' => 'https://example.com/updated.jpg',
            ]
        );

        $response = $this
            ->actingAs($user)
            ->put(route('books.update', $book), $data);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => '更新後タイトル',
            'author' => '更新後著者',
            'isbn' => '9780987654321',
            'published_date' => '2026-07-01',
            'description' => '更新後の説明です。',
            'image_url' => 'https://example.com/updated.jpg',
        ]);

        foreach ($newGenres as $genre) {
            $this->assertDatabaseHas('book_genres', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }

        foreach ($oldGenres as $genre) {
            $this->assertDatabaseMissing('book_genres', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
    }

    /**
     * 更新時は現在の書籍と同じISBNを使用できること
     */
    public function test_owner_can_update_book_without_changing_isbn(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()
            ->for($user)
            ->create([
                'isbn' => '9781234567890',
            ]);

        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'title' => 'ISBNを変更しない更新',
                'isbn' => '9781234567890',
            ]
        );

        $response = $this
            ->actingAs($user)
            ->put(route('books.update', $book), $data);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'ISBNを変更しない更新',
            'isbn' => '9781234567890',
        ]);
    }

    /**
     * 別の書籍が使用しているISBNには変更できないこと
     */
    public function test_isbn_used_by_another_book_cannot_be_used_for_update(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()
            ->for($user)
            ->create([
                'title' => '更新対象書籍',
                'isbn' => '9781234567890',
            ]);

        Book::factory()->create([
            'isbn' => '9780987654321',
        ]);

        $genre = Genre::factory()->create();

        $data = $this->validBookData(
            $user,
            [$genre->id],
            [
                'title' => '更新後タイトル',
                'isbn' => '9780987654321',
            ]
        );

        $response = $this
            ->actingAs($user)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), $data);

        $response->assertRedirect(route('books.edit', $book));
        $response->assertSessionHasErrors('isbn');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新対象書籍',
            'isbn' => '9781234567890',
        ]);
    }

    /**
     * 書籍登録者本人が書籍を削除できること
     */
    public function test_owner_can_delete_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()
            ->for($user)
            ->create();

        $genre = Genre::factory()->create();

        $book->genres()->attach($genre->id);

        Review::factory()
            ->for($user)
            ->for($book)
            ->create();

        $favoriteUser = User::factory()->create();
        $favoriteUser->favoriteBooks()->attach($book->id);

        $response = $this
            ->actingAs($user)
            ->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        $this->assertDatabaseMissing('book_genres', [
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseMissing('reviews', [
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseMissing('favorites', [
            'book_id' => $book->id,
        ]);
    }

    /**

     * 他のユーザーは書籍編集画面を表示できないこと

     */

    public function test_other_user_cannot_display_edit_page(): void
    {

        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()
            ->for($owner)
            ->create();

        $response = $this
            ->actingAs($otherUser)
            ->get(route('books.edit', $book));

        $response->assertForbidden();
    }

    /**

     * 他のユーザーは書籍を更新できないこと

     */

    public function test_other_user_cannot_update_book(): void
    {

        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()

            ->for($owner)

            ->create([
                'title' => '変更前タイトル',
                'isbn' => '9781234567890',
            ]);

        $genre = Genre::factory()->create();

        $data = $this->validBookData(

            $otherUser,

            [$genre->id],

            [

                'title' => '他人による変更',

                'isbn' => '9780987654321',

            ]

        );

        $response = $this

            ->actingAs($otherUser)

            ->put(route('books.update', $book), $data);

        $response->assertForbidden();

        $this->assertDatabaseHas('books', [

            'id' => $book->id,

            'user_id' => $owner->id,

            'title' => '変更前タイトル',

            'isbn' => '9781234567890',

        ]);
    }

    /**

     * 他のユーザーは書籍を削除できないこと

     */

    public function test_other_user_cannot_delete_book(): void
    {

        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()

            ->for($owner)

            ->create();

        $response = $this

            ->actingAs($otherUser)

            ->delete(route('books.destroy', $book));

        $response->assertForbidden();

        $this->assertDatabaseHas('books', [

            'id' => $book->id,

            'user_id' => $owner->id,

        ]);
    }

}
