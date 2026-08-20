<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザーは複数の書籍を登録できる
     */
    public function test_user_has_many_books(): void
    {
        // ユーザーを1人作成
        $user = User::factory()->create();

        // そのユーザーが登録した書籍を3冊作成
        Book::factory()
            ->count(3)
            ->for($user)
            ->create();

        // Userモデルのbooksリレーションから3冊取得できることを確認
        $this->assertCount(3, $user->books);

        // 取得したものがBookモデルであることを確認
        $this->assertInstanceOf(Book::class, $user->books->first());
    }

    /**
     * 書籍は登録者であるユーザーに属する
     */
    public function test_book_belongs_to_user(): void
    {
        // ユーザーを1人作成
        $user = User::factory()->create();

        // そのユーザーに紐づく書籍を1冊作成
        $book = Book::factory()
            ->for($user)
            ->create();

        // Bookモデルのuserリレーションから登録者を取得できることを確認
        $this->assertInstanceOf(User::class, $book->user);

        // 作成したユーザーと書籍の登録者が同じことを確認
        $this->assertTrue($user->is($book->user));
    }

    public function test_book_belongs_to_many_genres(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book->genres()->attach($genres->pluck('id'));

        $book->refresh();

        $this->assertCount(2, $book->genres);
        $this->assertTrue($book->genres->contains($genres[0]));
        $this->assertTrue($book->genres->contains($genres[1]));
    }

    public function test_genre_belongs_to_many_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(2)->create();

        $genre->books()->attach($books->pluck('id'));

        $genre->refresh();

        $this->assertCount(2, $genre->books);
        $this->assertTrue($genre->books->contains($books[0]));
        $this->assertTrue($genre->books->contains($books[1]));
    }

    public function test_book_has_many_reviews(): void
    {
        $book = Book::factory()->create();

        Review::factory()
            ->count(2)
            ->for($book)
            ->create();

        $book->refresh();

        $this->assertCount(2, $book->reviews);
        $this->assertInstanceOf(Review::class, $book->reviews->first());
    }

    public function test_review_belongs_to_book(): void
    {
        $book = Book::factory()->create();

        $review = Review::factory()
            ->for($book)
            ->create();

        $this->assertTrue($review->book->is($book));
        $this->assertEquals($book->id, $review->book_id);
    }

    public function test_user_has_many_reviews(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Review::factory()
            ->count(3)
            ->for($user)
            ->for($book)
            ->create();

        $user->refresh();

        $this->assertCount(3, $user->reviews);
        $this->assertInstanceOf(Review::class, $user->reviews->first());
    }

    public function test_review_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()
            ->for($user)
            ->for($book)
            ->create();

        $this->assertTrue($review->user->is($user));
        $this->assertEquals($user->id, $review->user_id);
    }

    public function test_user_belongs_to_many_favorite_books(): void
    {
        $user = User::factory()->create();

        $books = Book::factory()
            ->count(3)
            ->create();

        $user->favoriteBooks()->attach($books->pluck('id'));

        $user->refresh();

        $this->assertCount(3, $user->favoriteBooks);
        $this->assertInstanceOf(Book::class, $user->favoriteBooks->first());
    }

    public function test_book_belongs_to_many_favorited_users(): void
    {
        $book = Book::factory()->create();

        $users = User::factory()
            ->count(3)
            ->create();

        $book->favoriteUsers()->attach($users->pluck('id'));

        $book->refresh();

        $this->assertCount(3, $book->favoriteUsers);
        $this->assertInstanceOf(User::class, $book->favoriteUsers->first());
    }

    public function test_user_belongs_to_many_liked_reviews(): void
    {
        $user = User::factory()->create();

        $reviews = Review::factory()
            ->count(3)
            ->create();

        $user->likedReviews()->attach($reviews->pluck('id'));

        $user->refresh();

        $this->assertCount(3, $user->likedReviews);

        $this->assertInstanceOf(
            Review::class,
            $user->likedReviews->first()
        );
    }

    public function test_review_belongs_to_many_liked_users(): void
    {
        $review = Review::factory()->create();

        $users = User::factory()
            ->count(3)
            ->create();

        $review->likedByUsers()->attach($users->pluck('id'));

        $review->refresh();

        $this->assertCount(3, $review->likedByUsers);

        $this->assertInstanceOf(
            User::class,
            $review->likedByUsers->first()
        );
    }
}
