<?php

namespace Tests\Feature\Favorites;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未認証ユーザーはお気に入り一覧を表示できないこと
     */
    public function test_guest_is_redirected_to_login_from_favorites_index(): void
    {
        $response = $this->get(route('favorites.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 認証済みユーザーがお気に入り一覧画面を表示できること
     */
    public function test_authenticated_user_can_display_favorites_index(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();
        $response->assertViewIs('favorites.index');
        $response->assertViewHas('books');
    }

    /**
     * お気に入り一覧にはログインユーザーのお気に入りだけが表示されること
     */
    public function test_favorites_index_contains_only_authenticated_users_favorite_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $favoriteBook = Book::factory()->create([
            'title' => '自分のお気に入り書籍',
        ]);

        $otherFavoriteBook = Book::factory()->create([
            'title' => '他のユーザーのお気に入り書籍',
        ]);

        $user->favoriteBooks()->attach($favoriteBook->id);
        $otherUser->favoriteBooks()->attach($otherFavoriteBook->id);

        $response = $this
            ->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();

        $response->assertViewHas('books', function ($books) use (
            $favoriteBook,
            $otherFavoriteBook
        ) {
            return $books->contains('id', $favoriteBook->id)
                && !$books->contains('id', $otherFavoriteBook->id);
        });
    }

    /**
     * 認証済みユーザーが書籍をお気に入り登録できること
     */
    public function test_authenticated_user_can_add_book_to_favorites(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * 未認証ユーザーは書籍をお気に入り登録できないこと
     */
    public function test_guest_cannot_toggle_favorite(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseMissing('favorites', [
            'book_id' => $book->id,
        ]);
    }

    /**
     * 登録済みの書籍を再度操作するとお気に入り解除されること
     */
    public function test_authenticated_user_can_remove_book_from_favorites(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->favoriteBooks()->attach($book->id);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * お気に入り解除後に再登録できること
     */
    public function test_authenticated_user_can_add_book_again_after_removing_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 1回目：お気に入り登録
        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', $book));

        // 2回目：お気に入り解除
        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', $book));

        // 3回目：再度お気に入り登録
        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseCount('favorites', 1);
    }

    /**
     * 同じユーザーと書籍の重複レコードが作成されないこと
     */
    public function test_duplicate_favorite_record_is_not_created(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 追加
        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseCount('favorites', 1);

        // 同じ書籍をもう一度操作すると、重複登録ではなく解除される
        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseCount('favorites', 0);

        // 再登録してもレコードは1件だけ
        $this
            ->actingAs($user)
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseCount('favorites', 1);

        $this->assertSame(
            1,
            $user->favoriteBooks()
                ->where('books.id', $book->id)
                ->count()
        );
    }

    /**
     * お気に入り一覧が1ページ10件でページネーションされること
     */
    public function test_favorites_index_is_paginated_by_ten_books(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(11)->create();

        $user->favoriteBooks()->attach($books->pluck('id'));

        $response = $this
            ->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();

        $response->assertViewHas('books', function ($paginatedBooks) {
            return $paginatedBooks->count() === 10
                && $paginatedBooks->total() === 11
                && $paginatedBooks->perPage() === 10;
        });
    }

    /**
     * お気に入り一覧の2ページ目を表示できること
     */
    public function test_authenticated_user_can_display_second_favorites_page(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(11)->create();

        $user->favoriteBooks()->attach($books->pluck('id'));

        $response = $this
            ->actingAs($user)
            ->get(route('favorites.index', ['page' => 2]));

        $response->assertOk();

        $response->assertViewHas('books', function ($paginatedBooks) {
            return $paginatedBooks->currentPage() === 2
                && $paginatedBooks->count() === 1;
        });
    }
}
