<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * トップページが正常に表示されること
     */
    public function test_top_page_is_displayed(): void
    {
        Book::factory()->count(3)->create();
        Genre::factory()->count(3)->create();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('books.index');
        $response->assertViewHas('books');
    }

    /**
     * 書籍一覧画面が正常に表示されること
     */
    public function test_book_index_page_is_displayed(): void
    {
        $books = Book::factory()->count(3)->create();
        $genres = Genre::factory()->count(2)->create();

        foreach ($books as $book) {
            $book->genres()->attach($genres->pluck('id'));
        }

        $response = $this->get('/books');

        $response->assertStatus(200);
        $response->assertViewIs('books.index');
        $response->assertViewHas('books');
    }

    /**
     * 書籍詳細画面に書籍情報が表示されること
     */
    public function test_book_detail_page_is_displayed(): void
    {
        $book = Book::factory()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
        ]);

        $genres = Genre::factory()->count(2)->create();
        $book->genres()->attach($genres->pluck('id'));

        Review::factory()->count(2)->create([
            'book_id' => $book->id,
        ]);

        $response = $this->get("/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertViewIs('books.show');
        $response->assertViewHas('book');

        $response->assertSee('テスト書籍');
        $response->assertSee('テスト著者');
        $response->assertSee('9781234567890');
    }

    /**
     * 存在しない書籍IDを指定すると404が返ること
     */
    public function test_nonexistent_book_returns_404(): void
    {
        $response = $this->get('/books/999999');

        $response->assertStatus(404);
    }
}
