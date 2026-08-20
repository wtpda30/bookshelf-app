<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍登録用の正常データを作る
     */
    private function validBookData(Genre $genre): array
    {
        return [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784101010014',
            'published_date' => '2025-01-01',
            'description' => 'テスト説明',
            'image_url' => 'https://example.com/book.jpg',
            'genre_ids' => [$genre->id],
        ];
    }

    public function test_unauthenticated_user_cannot_store_book(): void
    {
        $genre = Genre::factory()->create();

        $response = $this->postJson(
            '/api/v1/books',
            $this->validBookData($genre)
        );

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_update_book(): void
    {
        $book = Book::factory()->create();

        $genre = Genre::factory()->create();

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            [
                'title' => '更新タイトル',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date,
                'description' => $book->description,
                'image_url' => $book->image_url,
                'genre_ids' => [$genre->id],
            ]
        );

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_delete_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->deleteJson(
            "/api/v1/books/{$book->id}"
        );

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_store_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/books',
            $this->validBookData($genre)
        );

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'user_id' => $user->id,
        ]);
    }

    public function test_authenticated_user_id_is_used_when_storing_book(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();

        Sanctum::actingAs($user);

        $data = $this->validBookData($genre);

        // あえて他人のIDを送る
        $data['user_id'] = $otherUser->id;

        $response = $this->postJson(
            '/api/v1/books',
            $data
        );

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('books', [
            'title' => 'テスト書籍',
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_owner_can_update_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $genre = Genre::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            [
                'title' => '更新後タイトル',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date,
                'description' => $book->description,
                'image_url' => $book->image_url,
                'genre_ids' => [$genre->id],
            ]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後タイトル',
        ]);
    }

    public function test_other_user_cannot_update_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        $genre = Genre::factory()->create();

        Sanctum::actingAs($otherUser);

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            [
                'title' => '勝手に更新',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date,
                'description' => $book->description,
                'image_url' => $book->image_url,
                'genre_ids' => [$genre->id],
            ]
        );

        $response->assertStatus(403);

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
            'title' => '勝手に更新',
        ]);
    }

    public function test_owner_can_delete_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/v1/books/{$book->id}"
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }

    public function test_other_user_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson(
            "/api/v1/books/{$book->id}"
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
    }
}
