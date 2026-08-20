<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSortTest extends TestCase
{
    use RefreshDatabase;

    /**
     * latest を指定すると、新しい登録順に並ぶこと
     */
    public function test_books_are_sorted_by_latest(): void
    {
        $oldBook = Book::factory()->create([
            'title' => '古い本',
            'created_at' => now()->subDays(2),
        ]);

        $newBook = Book::factory()->create([
            'title' => '新しい本',
            'created_at' => now(),
        ]);

        $response = $this->get(route('books.index', [
            'sort' => 'latest',
        ]));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $newBook->title,
            $oldBook->title,
        ]);
    }

    /**
     * oldest を指定すると、古い登録順に並ぶこと
     */
    public function test_books_are_sorted_by_oldest(): void
    {
        $oldBook = Book::factory()->create([
            'title' => '古い本',
            'created_at' => now()->subDays(2),
        ]);

        $newBook = Book::factory()->create([
            'title' => '新しい本',
            'created_at' => now(),
        ]);

        $response = $this->get(route('books.index', [
            'sort' => 'oldest',
        ]));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $oldBook->title,
            $newBook->title,
        ]);
    }

    /**
     * title を指定すると、タイトル昇順に並ぶこと
     */
    public function test_books_are_sorted_by_title(): void
    {
        $bookC = Book::factory()->create([
            'title' => 'Cの本',
        ]);

        $bookA = Book::factory()->create([
            'title' => 'Aの本',
        ]);

        $bookB = Book::factory()->create([
            'title' => 'Bの本',
        ]);

        $response = $this->get(route('books.index', [
            'sort' => 'title',
        ]));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $bookA->title,
            $bookB->title,
            $bookC->title,
        ]);
    }

    /**
     * rating を指定すると、平均評価が高い順に並ぶこと
     */
    public function test_books_are_sorted_by_rating(): void
    {
        $highRatedBook = Book::factory()->create([
            'title' => '高評価の本',
        ]);

        $lowRatedBook = Book::factory()->create([
            'title' => '低評価の本',
        ]);

        Review::factory()->create([
            'book_id' => $highRatedBook->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'book_id' => $highRatedBook->id,
            'rating' => 4,
        ]);

        Review::factory()->create([
            'book_id' => $lowRatedBook->id,
            'rating' => 2,
        ]);

        $response = $this->get(route('books.index', [
            'sort' => 'rating',
        ]));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $highRatedBook->title,
            $lowRatedBook->title,
        ]);
    }

    /**
     * sort を指定しない場合は、デフォルトの新しい順になること
     */
    public function test_default_sort_is_latest(): void
    {
        $oldBook = Book::factory()->create([
            'title' => '古い本',
            'created_at' => now()->subDays(2),
        ]);

        $newBook = Book::factory()->create([
            'title' => '新しい本',
            'created_at' => now(),
        ]);

        $response = $this->get(route('books.index'));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $newBook->title,
            $oldBook->title,
        ]);
    }

    /**
     * キーワード検索とソートを同時指定しても両方適用されること
     */
    public function test_keyword_search_and_sort_are_both_applied(): void
    {
        $oldMatchedBook = Book::factory()->create([
            'title' => '猫の古い本',
            'created_at' => now()->subDays(2),
        ]);

        $newMatchedBook = Book::factory()->create([
            'title' => '猫の新しい本',
            'created_at' => now(),
        ]);

        $unmatchedBook = Book::factory()->create([
            'title' => '犬の本',
        ]);

        $response = $this->get(route('books.index', [
            'keyword' => '猫',
            'sort' => 'oldest',
        ]));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $oldMatchedBook->title,
            $newMatchedBook->title,
        ]);

        $response->assertDontSee($unmatchedBook->title);
    }

    /**
     * ジャンル絞り込みとソートを同時指定しても両方適用されること
     */
    public function test_genre_filter_and_sort_are_both_applied(): void
    {
        $novel = Genre::factory()->create([
            'name' => '小説',
        ]);

        $oldNovel = Book::factory()->create([
            'title' => '古い小説',
            'created_at' => now()->subDays(2),
        ]);

        $newNovel = Book::factory()->create([
            'title' => '新しい小説',
            'created_at' => now(),
        ]);

        $otherBook = Book::factory()->create([
            'title' => '技術書',
        ]);

        $oldNovel->genres()->attach($novel->id);
        $newNovel->genres()->attach($novel->id);

        $response = $this->get(route('books.index', [
            'genre' => $novel->id,
            'sort' => 'oldest',
        ]));

        $response->assertStatus(200);

        $response->assertSeeInOrder([
            $oldNovel->title,
            $newNovel->title,
        ]);

        $response->assertDontSee($otherBook->title);
    }

    /**
     * ページネーション後もソート条件が維持されること
     */
    public function test_sort_condition_is_kept_in_pagination_links(): void
    {
        Book::factory()->count(15)->create();

        $response = $this->get(route('books.index', [
            'sort' => 'oldest',
        ]));

        $response->assertStatus(200);

        $response->assertSee('sort=oldest');
    }
}
