<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 登録者本人は書籍編集画面を表示できる
     */
    public function test_owner_can_view_book_edit_page(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('books.edit', $book));

        $response->assertStatus(200);
        $response->assertSee($book->title);
    }

    /**
     * 登録者本人は書籍を更新できる
     */
    public function test_owner_can_update_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $genre = Genre::factory()->create();

        $response = $this
            ->actingAs($user)
            ->put(route('books.update', $book), [
                'title' => '更新後のタイトル',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date->format('Y-m-d'),
                'description' => $book->description,
                'image_url' => $book->image_url,
                'genres' => [$genre->id],
            ]);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後のタイトル',
        ]);
    }

    /**
     * 登録者本人は書籍を削除できる
     */
    public function test_owner_can_delete_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }

    /**
     * 他ユーザーは書籍編集画面を表示できない
     */
    public function test_other_user_cannot_view_book_edit_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->get(route('books.edit', $book));

        $response->assertStatus(403);
    }

    /**
     * 他ユーザーは書籍を更新できない
     */
    public function test_other_user_cannot_update_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
            'title' => '変更前タイトル',
        ]);

        $genre = Genre::factory()->create();

        $response = $this
            ->actingAs($otherUser)
            ->put(route('books.update', $book), [
                'title' => '勝手に変更',
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date->format('Y-m-d'),
                'description' => $book->description,
                'image_url' => $book->image_url,
                'genres' => [$genre->id],
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '変更前タイトル',
        ]);
    }

    /**
     * 他ユーザーは書籍を削除できない
     */
    public function test_other_user_cannot_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->delete(route('books.destroy', $book));

        $response->assertStatus(403);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
    }

    /**
     * 未認証ユーザーは編集画面へアクセスするとログイン画面へリダイレクトされる
     */
    public function test_guest_is_redirected_to_login_when_accessing_edit_page(): void
    {
        $book = Book::factory()->create();

        $response = $this->get(route('books.edit', $book));

        $response->assertRedirect('/login');
    }

    /**
     * 未認証ユーザーは書籍を更新できない
     */
    public function test_guest_cannot_update_book(): void
    {
        $book = Book::factory()->create([
            'title' => '変更前タイトル',
        ]);

        $response = $this->put(route('books.update', $book), [
            'title' => '変更後タイトル',
            'author' => $book->author,
            'isbn' => $book->isbn,
            'published_date' => $book->published_date->format('Y-m-d'),
            'description' => $book->description,
            'image_url' => $book->image_url,
            'genres' => [],
        ]);

        $response->assertRedirect('/login');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '変更前タイトル',
        ]);
    }

    /**
     * 未認証ユーザーは書籍を削除できない
     */
    public function test_guest_cannot_delete_book(): void
    {
        $book = Book::factory()->create();

        $response = $this->delete(route('books.destroy', $book));

        $response->assertRedirect('/login');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
    }
}
