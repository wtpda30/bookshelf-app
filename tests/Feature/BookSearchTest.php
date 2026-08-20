<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * キーワードを指定すると、
     * タイトルに部分一致する書籍だけ表示されること
     */
    public function test_keyword_search_matches_book_title(): void
    {
        $matchedBook = Book::factory()->create([
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
        ]);

        $otherBook = Book::factory()->create([
            'title' => '羅生門',
            'author' => '芥川龍之介',
        ]);

        $response = $this->get(route('books.index', [
            'keyword' => '猫',
        ]));

        $response->assertStatus(200);
        $response->assertSee($matchedBook->title);
        $response->assertDontSee($otherBook->title);
    }

    /**
     * キーワードを指定すると、
     * 著者名に部分一致する書籍だけ表示されること
     */
    public function test_keyword_search_matches_book_author(): void
    {
        $matchedBook = Book::factory()->create([
            'title' => '坊っちゃん',
            'author' => '夏目漱石',
        ]);

        $otherBook = Book::factory()->create([
            'title' => '羅生門',
            'author' => '芥川龍之介',
        ]);

        $response = $this->get(route('books.index', [
            'keyword' => '夏目',
        ]));

        $response->assertStatus(200);
        $response->assertSee($matchedBook->title);
        $response->assertDontSee($otherBook->title);
    }

    /**
     * 存在しないキーワードを指定すると、
     * 該当書籍が表示されないこと
     */
    public function test_nonexistent_keyword_returns_no_books(): void
    {
        Book::factory()->create([
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
        ]);

        $response = $this->get(route('books.index', [
            'keyword' => '存在しないキーワード',
        ]));

        $response->assertStatus(200);
        $response->assertSee('書籍が見つかりませんでした。');
    }

    /**
     * キーワードが空の場合は、
     * 全書籍が表示されること
     */
    public function test_empty_keyword_displays_all_books(): void
    {
        $book1 = Book::factory()->create([
            'title' => '吾輩は猫である',
        ]);

        $book2 = Book::factory()->create([
            'title' => '羅生門',
        ]);

        $response = $this->get(route('books.index', [
            'keyword' => '',
        ]));

        $response->assertStatus(200);
        $response->assertSee($book1->title);
        $response->assertSee($book2->title);
    }

    /**
     * ジャンルを指定すると、
     * 指定ジャンルに紐づく書籍だけ表示されること
     */
    public function test_genre_filter_displays_only_matching_books(): void
    {
        $novel = Genre::factory()->create([
            'name' => '小説',
        ]);

        $technology = Genre::factory()->create([
            'name' => '技術',
        ]);

        $novelBook = Book::factory()->create([
            'title' => '坊っちゃん',
        ]);

        $technologyBook = Book::factory()->create([
            'title' => 'Laravel入門',
        ]);

        $novelBook->genres()->attach($novel->id);
        $technologyBook->genres()->attach($technology->id);

        $response = $this->get(route('books.index', [
            'genre' => $novel->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee($novelBook->title);
        $response->assertDontSee($technologyBook->title);
    }

    /**
     * 複数ジャンルを持つ書籍でも、
     * 指定ジャンルを持っていれば表示されること
     */
    public function test_book_with_multiple_genres_is_found_by_selected_genre(): void
    {
        $novel = Genre::factory()->create([
            'name' => '小説',
        ]);

        $classic = Genre::factory()->create([
            'name' => '文学',
        ]);

        $book = Book::factory()->create([
            'title' => '坊っちゃん',
        ]);

        $book->genres()->attach([
            $novel->id,
            $classic->id,
        ]);

        $response = $this->get(route('books.index', [
            'genre' => $classic->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee($book->title);
    }

    /**
     * キーワードとジャンルを同時指定すると、
     * 両方の条件を満たす書籍だけ表示されること
     */
    public function test_keyword_and_genre_filter_are_both_applied(): void
    {
        $novel = Genre::factory()->create([
            'name' => '小説',
        ]);

        $technology = Genre::factory()->create([
            'name' => '技術',
        ]);

        $matchedBook = Book::factory()->create([
            'title' => '夏目漱石作品集',
            'author' => '夏目漱石',
        ]);

        $wrongGenreBook = Book::factory()->create([
            'title' => '夏目式Laravel入門',
            'author' => '山田太郎',
        ]);

        $wrongKeywordBook = Book::factory()->create([
            'title' => '羅生門',
            'author' => '芥川龍之介',
        ]);

        $matchedBook->genres()->attach($novel->id);
        $wrongGenreBook->genres()->attach($technology->id);
        $wrongKeywordBook->genres()->attach($novel->id);

        $response = $this->get(route('books.index', [
            'keyword' => '夏目',
            'genre' => $novel->id,
        ]));

        $response->assertStatus(200);

        $response->assertSee($matchedBook->title);
        $response->assertDontSee($wrongGenreBook->title);
        $response->assertDontSee($wrongKeywordBook->title);
    }

    /**
     * 検索条件を指定したままページネーションしても、
     * クエリパラメータが維持されること
     */
    public function test_search_conditions_are_kept_in_pagination_links(): void
    {
        Book::factory()->count(15)->create([
            'author' => '夏目漱石',
        ]);

        $response = $this->get(route('books.index', [
            'keyword' => '夏目',
        ]));

        $response->assertStatus(200);

        $response->assertSee('keyword=%E5%A4%8F%E7%9B%AE', false);
    }
}
