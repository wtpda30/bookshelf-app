<?php

namespace Tests\Feature\Reviews;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証済みユーザーがレビューを投稿できること
     */
    public function test_authenticated_user_can_create_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $data = [
            'rating' => 5,
            'comment' => 'とても面白い書籍でした。',
        ];

        $response = $this
            ->actingAs($user)
            ->post(route('reviews.store', $book), $data);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', 'レビューを投稿しました');

        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'とても面白い書籍でした。',
        ]);
    }

    /**
     * 未ログインユーザーはレビューを投稿できないこと
     */
    public function test_guest_is_redirected_to_login_when_creating_review(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => 'レビュー本文',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * 評価が未入力の場合はレビューが保存されないこと
     */
    public function test_rating_is_required(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => null,
                'comment' => 'レビュー本文',
            ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * 評価が1から5の範囲外の場合は保存されないこと
     */
    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 6,
                'comment' => 'レビュー本文',
            ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * 評価が整数でない場合は保存されないこと
     */
    public function test_rating_must_be_integer(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 'abc',
                'comment' => 'レビュー本文',
            ]);

        $response->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * コメントが未入力の場合は保存されないこと
     */
    public function test_comment_is_required(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 4,
                'comment' => '',
            ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * コメントが1000文字を超える場合は保存されないこと
     */
    public function test_comment_must_not_exceed_1000_characters(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 4,
                'comment' => str_repeat('あ', 1001),
            ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * 投稿者本人がレビュー編集画面を表示できること
     */
    public function test_owner_can_display_review_edit_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '編集前のコメント',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reviews.edit', $review));

        $response->assertStatus(200);
        $response->assertViewIs('reviews.edit');
        $response->assertViewHas('review');

        $response->assertSee('編集前のコメント');
        $response->assertSee('4');
    }

    /**
     * 投稿者本人がレビューを更新できること
     */
    public function test_owner_can_update_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('reviews.update', $review), [
                'rating' => 5,
                'comment' => '更新後のコメント',
            ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', 'レビューを更新しました');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '更新後のコメント',
        ]);
    }

    /**
     * 更新データが不正な場合はレビューが更新されないこと
     */
    public function test_invalid_data_does_not_update_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), [
                'rating' => 6,
                'comment' => '',
            ]);

        $response->assertRedirect(route('reviews.edit', $review));
        $response->assertSessionHasErrors([
            'rating',
            'comment',
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 3,
            'comment' => '更新前のコメント',
        ]);
    }

    /**
     * 投稿者本人がレビューを削除できること
     */
    public function test_owner_can_delete_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas('success', 'レビューを削除しました');

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);
    }

    /**
     * レビュー削除時に関連するいいねも削除されること
     */
    public function test_review_likes_are_deleted_when_review_is_deleted(): void
    {
        $reviewOwner = User::factory()->create();
        $likingUser = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $reviewOwner->id,
            'book_id' => $book->id,
        ]);

        $review->likedByUsers()->attach($likingUser->id);

        $this->assertDatabaseHas('review_likes', [
            'review_id' => $review->id,
            'user_id' => $likingUser->id,
        ]);

        $response = $this
            ->actingAs($reviewOwner)
            ->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
            'user_id' => $likingUser->id,
        ]);

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);
    }

    /**
     * 他のユーザーはレビュー編集画面を表示できないこと
     */
    public function test_other_user_cannot_display_review_edit_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->get(route('reviews.edit', $review));

        $response->assertForbidden();
    }

    /**

     * 他のユーザーはレビューを更新できないこと

     */

    public function test_other_user_cannot_update_review(): void

    {

        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()->create();

        $review = Review::factory()->create([

            'user_id' => $owner->id,

            'book_id' => $book->id,

            'rating' => 3,

            'comment' => '元のコメント',

        ]);

        $response = $this

            ->actingAs($otherUser)

            ->put(route('reviews.update', $review), [

                'rating' => 5,

                'comment' => '不正に変更したコメント',

            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('reviews', [

            'id' => $review->id,

            'rating' => 3,

            'comment' => '元のコメント',

        ]);

    }

    /**

     * 他のユーザーはレビューを削除できないこと

     */

    public function test_other_user_cannot_delete_review(): void

    {

        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()->create();

        $review = Review::factory()->create([

            'user_id' => $owner->id,

            'book_id' => $book->id,

        ]);

        $response = $this

            ->actingAs($otherUser)

            ->delete(route('reviews.destroy', $review));

        $response->assertForbidden();

        $this->assertDatabaseHas('reviews', [

            'id' => $review->id,

        ]);

    }
}
