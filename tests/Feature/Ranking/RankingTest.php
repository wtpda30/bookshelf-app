<?php

namespace Tests\Feature\Ranking;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ランキング画面が正常に表示されること
     */
    public function test_ranking_page_is_displayed(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
        $response->assertViewHas('rankedBooks');
    }

    /**
     * ゲストでもランキング画面を表示できること
     */
    public function test_guest_can_display_ranking_page(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
    }

    /**
     * レビューがある書籍が平均評価の高い順で表示されること
     */
    public function test_books_are_ordered_by_average_rating_descending(): void
    {
        $user = User::factory()->create();

        $highRatedBook = Book::factory()->create();
        $middleRatedBook = Book::factory()->create();
        $lowRatedBook = Book::factory()->create();

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $highRatedBook->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $middleRatedBook->id,
            'rating' => 3,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $lowRatedBook->id,
            'rating' => 1,
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($rankedBooks) use (
            $highRatedBook,
            $middleRatedBook,
            $lowRatedBook
        ) {
            return $rankedBooks->pluck('id')->values()->all() === [
                $highRatedBook->id,
                $middleRatedBook->id,
                $lowRatedBook->id,
            ];
        });
    }

    /**
     * 複数レビューの平均評価が正しく計算されること
     */
    public function test_average_rating_is_calculated_correctly(): void
    {
        $users = User::factory()->count(2)->create();
        $book = Book::factory()->create();

        Review::factory()->create([
            'user_id' => $users[0]->id,
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $users[1]->id,
            'book_id' => $book->id,
            'rating' => 3,
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($book) {
            $rankedBook = $rankedBooks->firstWhere('id', $book->id);

            return $rankedBook !== null
                && (float) $rankedBook->reviews_avg_rating === 4.0;
        });
    }

    /**
     * レビュー件数が正しく取得されること
     */
    public function test_review_count_is_correct(): void
    {
        $users = User::factory()->count(3)->create();
        $book = Book::factory()->create();

        foreach ($users as $user) {
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => 4,
            ]);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($rankedBooks) use ($book) {
            $rankedBook = $rankedBooks->firstWhere('id', $book->id);

            return $rankedBook !== null
                && $rankedBook->reviews_count === 3;
        });
    }

    /**
     * レビューがない書籍はランキングに表示されないこと
     */
    public function test_book_without_reviews_is_not_displayed(): void
    {
        $user = User::factory()->create();

        $reviewedBook = Book::factory()->create();
        $bookWithoutReviews = Book::factory()->create();

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $reviewedBook->id,
            'rating' => 5,
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($rankedBooks) use (
            $reviewedBook,
            $bookWithoutReviews
        ) {
            return $rankedBooks->contains('id', $reviewedBook->id)
                && ! $rankedBooks->contains('id', $bookWithoutReviews->id);
        });
    }

    /**
     * 対象書籍が10件を超えても上位10件のみ表示されること
     */
    public function test_only_top_ten_books_are_displayed(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 11; $i++) {
            $book = Book::factory()->create();

            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => ($i % 5) + 1,
            ]);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();

        $response->assertViewHas('rankedBooks', function ($rankedBooks) {
            return $rankedBooks->count() === 10;
        });
    }
}
