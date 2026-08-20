<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookIsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->actingAs($user);
    }

    /**
     * 13桁ISBNを指定すると、
     * Google Books APIから取得した書籍情報がJSONで返ること
     */
    public function test_13_digit_isbn_returns_book_information(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '吾輩は猫である',
                            'authors' => ['夏目漱石'],
                            'publishedDate' => '1905-01-01',
                            'description' => '書籍の説明です',
                            'imageLinks' => [
                                'thumbnail' => 'https://example.com/book.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $isbn = '9784101010014';

        $response = $this->get(
            route('books.isbn', ['isbn' => $isbn])
        );

        $response->assertStatus(200);

        $response->assertJson([
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
            'isbn' => $isbn,
            'published_date' => '1905-01-01',
            'description' => '書籍の説明です',
            'image_url' => 'https://example.com/book.jpg',
        ]);
    }

    /**
     * Google Books APIに該当書籍がない場合、
     * 404が返ること
     */
    public function test_book_not_found_returns_404(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [],
            ], 200),
        ]);

        $isbn = '9784101010014';

        $response = $this->get(
            route('books.isbn', ['isbn' => $isbn])
        );

        $response->assertStatus(404);

        $response->assertJson([
            'error' => '書籍が見つかりませんでした。',
        ]);
    }

    /**
     * ISBNが13桁未満の場合、
     * 404が返ること
     */
    public function test_isbn_with_less_than_13_digits_returns_404(): void
    {
        $response = $this->get(
            route('books.isbn', ['isbn' => '123456789012'])
        );

        $response->assertStatus(404);

        $response->assertJson([
            'error' => '書籍が見つかりませんでした。',
        ]);

        Http::assertNothingSent();
    }

    /**
     * ISBNが13桁より長い場合、
     * 404が返ること
     */
    public function test_isbn_with_more_than_13_digits_returns_404(): void
    {
        $response = $this->get(
            route('books.isbn', ['isbn' => '12345678901234'])
        );

        $response->assertStatus(404);

        $response->assertJson([
            'error' => '書籍が見つかりませんでした。',
        ]);

        Http::assertNothingSent();
    }

    /**
     * ISBNに数字以外が含まれる場合、
     * 404が返ること
     */
    public function test_isbn_containing_letters_returns_404(): void
    {
        $response = $this->get(
            route('books.isbn', ['isbn' => '97841010100AB'])
        );

        $response->assertStatus(404);

        $response->assertJson([
            'error' => '書籍が見つかりませんでした。',
        ]);

        Http::assertNothingSent();
    }

    /**
     * Google Books APIが429を返した場合、
     * 429が返ること
     */
    public function test_google_books_api_429_returns_429(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 429),
        ]);

        $response = $this->get(
            route('books.isbn', [
                'isbn' => '9784101010014',
            ])
        );

        $response->assertStatus(429);

        $response->assertJson([
            'error' => 'Google Books APIのクォータを超過しました。.envにGOOGLE_BOOKS_API_KEYを設定してください。',
        ]);
    }

    /**
     * API通信時に例外が発生した場合、
     * 500が返ること
     */
    public function test_api_exception_returns_500(): void
    {
        Http::fake(function () {
            throw new \Exception('通信エラー');
        });

        $response = $this->get(
            route('books.isbn', [
                'isbn' => '9784101010014',
            ])
        );

        $response->assertStatus(500);

        $response->assertJson([
            'error' => 'API通信エラーが発生しました。',
        ]);
    }

    /**
     * Google Books APIへのリクエストに
     * ISBNとAPIキーが含まれていること
     */
    public function test_request_contains_isbn_and_api_key(): void
    {
        config([
            'services.google_books.key' => 'test-api-key',
        ]);

        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'テスト書籍',
                            'authors' => ['テスト著者'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $isbn = '9784101010014';

        $this->get(
            route('books.isbn', ['isbn' => $isbn])
        );

        Http::assertSent(function ($request) use ($isbn) {
            return str_contains(
                $request->url(),
                'https://www.googleapis.com/books/v1/volumes'
            )
                && $request['q'] === 'isbn:'.$isbn
                && $request['key'] === 'test-api-key';
        });
    }
}
