<?php

namespace Tests\Feature\ReviewLikes;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用のレビューを作成する
     */
    private function createReview(): Review
    {
        $bookOwner = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $bookOwner->id,
        ]);

        $reviewUser = User::factory()->create();

        return Review::create([
            'user_id' => $reviewUser->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'テストレビューです。',
        ]);
    }

    /**
     * 認証済みユーザーがレビューにいいねできること
     */
    public function test_authenticated_user_can_like_review(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $response->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    /**
     * 未認証ユーザーはレビューにいいねできず、
     * ログイン画面へリダイレクトされること
     */
    public function test_guest_cannot_like_review(): void
    {
        $review = $this->createReview();

        $response = $this->post(route('reviews.like', $review));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
        ]);
    }

    /**
     * いいね済みのレビューを再度操作すると、
     * いいねが解除されること
     */
    public function test_authenticated_user_can_remove_review_like(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        $user->likedReviews()->attach($review->id);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $response->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    /**
     * いいね解除後に同じレビューへ再度いいねできること
     */
    public function test_authenticated_user_can_like_review_again_after_removing_like(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        // 1回目：いいね登録
        $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        // 2回目：いいね解除
        $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        // 3回目：再度いいね登録
        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $response->assertRedirect(route('books.show', $review->book));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    /**
     * 同じユーザーとレビューの組み合わせで、
     * 複数のいいねレコードが作成されないこと
     */
    public function test_duplicate_review_like_record_is_not_created(): void
    {
        $user = User::factory()->create();
        $review = $this->createReview();

        // いいね登録
        $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $this->assertDatabaseCount('review_likes', 1);

        // 同じボタンを再度押すとtoggleにより解除される
        $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $this->assertDatabaseCount('review_likes', 0);

        // 再び押しても作成されるレコードは1件のみ
        $this
            ->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $this->assertDatabaseCount('review_likes', 1);

        $this->assertSame(
            1,
            $user->likedReviews()
                ->where('reviews.id', $review->id)
                ->count()
        );
    }

    /**
     * 自分のいいねを解除しても、
     * 他のユーザーのいいねは削除されないこと
     */
    public function test_removing_like_does_not_remove_other_users_like(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = $this->createReview();

        $user->likedReviews()->attach($review->id);
        $otherUser->likedReviews()->attach($review->id);

        $this->actingAs($user)
            ->from(route('books.show', $review->book))
            ->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $otherUser->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 1);
    }

    /**
     * レビューに紐付くいいね件数を正しく取得できること
     */
    public function test_review_like_count_is_correct(): void
    {
        $review = $this->createReview();

        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $user->likedReviews()->attach($review->id);
        }

        $review->refresh();

        $this->assertSame(3, $review->likedByUsers()->count());

        $this->assertDatabaseCount('review_likes', 3);
    }
}
