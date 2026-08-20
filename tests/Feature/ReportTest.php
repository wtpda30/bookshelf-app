<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証ユーザーはマイ読書レポート画面を表示できる
     */
    public function test_authenticated_user_can_view_report_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
    }

    /**
     * 未認証ユーザーはログイン画面へリダイレクトされる
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('reports.index'));

        $response->assertRedirect('/login');
    }

    /**
     * 自分のレビュー件数だけが集計される
     */
    public function test_total_reviews_counts_only_logged_in_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Review::factory()->count(3)->create([
            'user_id' => $user->id,
        ]);

        Review::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertStatus(200);

        $response->assertViewHas('stats', function ($stats) {
            return $stats['summary']['total_reviews'] === 3;
        });
    }

    /**
     * 読了済みの読書計画だけが読了冊数として集計される
     */
    public function test_completed_reading_plans_are_counted_as_books_read(): void
    {
        $user = User::factory()->create();

        ReadingPlan::factory()->count(3)->completed()->create([
            'user_id' => $user->id,
        ]);

        ReadingPlan::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertStatus(200);

        $response->assertViewHas('stats', function ($stats) {
            return $stats['summary']['books_read'] === 3;
        });
    }

    /**
     * 他ユーザーの読書計画は読了冊数に含まれない
     */
    public function test_other_users_reading_plans_are_not_counted(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ReadingPlan::factory()->count(2)->completed()->create([
            'user_id' => $user->id,
        ]);

        ReadingPlan::factory()->count(4)->completed()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['summary']['books_read'] === 2;
        });
    }

    /**
     * レビューの平均評価が正しく集計される
     */
    public function test_average_rating_is_calculated_correctly(): void
    {
        $user = User::factory()->create();

        Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 3,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            return (float) $stats['summary']['average_rating'] === 4.0;
        });
    }

    /**
     * 評価分布が正しく集計される
     */
    public function test_rating_distribution_is_calculated_correctly(): void
    {
        $user = User::factory()->create();

        Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'rating' => 3,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['rating_distribution'][1] === 0
                && $stats['rating_distribution'][2] === 0
                && $stats['rating_distribution'][3] === 1
                && $stats['rating_distribution'][4] === 0
                && $stats['rating_distribution'][5] === 2;
        });
    }

    /**
     * ジャンル別評価が正しく集計される
     */
    public function test_genre_ratings_are_calculated_correctly(): void
    {
        $user = User::factory()->create();

        $novel = Genre::factory()->create([
            'name' => '小説',
        ]);

        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        $book1->genres()->attach($novel->id);
        $book2->genres()->attach($novel->id);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'rating' => 3,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertStatus(200);

        $response->assertViewHas('stats', function ($stats) {
            $genre = $stats['genre_ratings']->first();

            return $genre['name'] === '小説'
                && $genre['count'] === 2
                && (float) $genre['average_rating'] === 4.0;
        });
    }

    /**
     * データが0件でもエラーにならず表示できる
     */
    public function test_report_page_can_be_displayed_when_user_has_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertStatus(200);

        $response->assertViewHas('stats', function ($stats) {
            return $stats['summary']['total_reviews'] === 0
                && $stats['summary']['books_read'] === 0
                && (float) $stats['summary']['average_rating'] === 0.0
                && $stats['top_rated_books']->isEmpty()
                && $stats['genre_ratings']->isEmpty();
        });
    }
}
