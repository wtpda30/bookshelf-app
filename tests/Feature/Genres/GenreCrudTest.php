<?php

namespace Tests\Feature\Feature\Genres;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証済みユーザーがジャンル一覧画面を表示できること
     */
    public function test_authenticated_user_can_display_genre_index_page(): void
    {
        $user = User::factory()->create();

        Genre::create(['name' => '小説']);
        Genre::create(['name' => 'ビジネス']);

        $response = $this
            ->actingAs($user)
            ->get(route('genres.index'));

        $response->assertOk();
        $response->assertViewIs('genres.index');
        $response->assertViewHas('genres');
        $response->assertSee('小説');
        $response->assertSee('ビジネス');
    }

    /**
     * 未認証ユーザーはジャンル一覧画面を表示できないこと
     */
    public function test_guest_is_redirected_to_login_from_genre_index_page(): void
    {
        $response = $this->get(route('genres.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 認証済みユーザーがジャンル登録画面を表示できること
     */
    public function test_authenticated_user_can_display_genre_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('genres.create'));

        $response->assertOk();
        $response->assertViewIs('genres.create');
    }

    /**
     * 未認証ユーザーはジャンル登録画面を表示できないこと
     */
    public function test_guest_is_redirected_to_login_from_genre_create_page(): void
    {
        $response = $this->get(route('genres.create'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 正常なジャンル名でジャンルを登録できること
     */
    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('genres.store'), [
                'name' => 'ミステリー',
            ]);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを作成しました');

        $this->assertDatabaseHas('genres', [
            'name' => 'ミステリー',
        ]);
    }

    /**
     * ジャンル名が未入力の場合は登録できないこと
     */
    public function test_genre_name_is_required_when_creating(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), [
                'name' => '',
            ]);

        $response->assertRedirect(route('genres.create'));
        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 0);
    }

    /**
     * ジャンル名が255文字を超える場合は登録できないこと
     */
    public function test_genre_name_must_not_exceed_255_characters_when_creating(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), [
                'name' => str_repeat('あ', 256),
            ]);

        $response->assertRedirect(route('genres.create'));
        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 0);
    }

    /**
     * 既に登録されているジャンル名は登録できないこと
     */
    public function test_duplicate_genre_name_cannot_be_created(): void
    {
        $user = User::factory()->create();

        Genre::create([
            'name' => '小説',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), [
                'name' => '小説',
            ]);

        $response->assertRedirect(route('genres.create'));
        $response->assertSessionHasErrors('name');

        $this->assertDatabaseCount('genres', 1);
    }

    /**
     * ジャンル詳細画面に紐づく書籍が表示されること
     */
    public function test_authenticated_user_can_display_genre_detail_page(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '技術書',
        ]);

        $book = Book::factory()
            ->for($user)
            ->create([
                'title' => 'Laravel入門',
            ]);

        $book->genres()->attach($genre->id);

        $response = $this
            ->actingAs($user)
            ->get(route('genres.show', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.show');
        $response->assertViewHas('genre');
        $response->assertViewHas('books');
        $response->assertSee('技術書');
        $response->assertSee('Laravel入門');
    }

    /**
     * ジャンル詳細画面では書籍が10件ずつ表示されること
     */
    public function test_genre_detail_page_paginates_books_by_ten(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '小説',
        ]);

        $books = Book::factory()
            ->count(11)
            ->for($user)
            ->create();

        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        $response = $this
            ->actingAs($user)
            ->get(route('genres.show', $genre));

        $response->assertOk();

        $response->assertViewHas('books', function ($books) {
            return $books->count() === 10
                && $books->total() === 11
                && $books->perPage() === 10;
        });
    }

    /**
     * 存在しないジャンルIDでは404が返ること
     */
    public function test_nonexistent_genre_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/genres/999999');

        $response->assertNotFound();
    }

    /**
     * ジャンル編集画面に現在のジャンル名が渡されること
     */
    public function test_authenticated_user_can_display_genre_edit_page(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '変更前ジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('genres.edit', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.edit');
        $response->assertViewHas('genre', function ($viewGenre) use ($genre) {
            return $viewGenre->id === $genre->id
                && $viewGenre->name === '変更前ジャンル';
        });

        $response->assertSee('変更前ジャンル');
    }

    /**
     * 正常なジャンル名に更新できること
     */
    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '変更前ジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('genres.update', $genre), [
                'name' => '変更後ジャンル',
            ]);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを更新しました');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '変更後ジャンル',
        ]);

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
            'name' => '変更前ジャンル',
        ]);
    }

    /**
     * 現在のジャンル名を変更せず更新できること
     */
    public function test_current_genre_name_is_allowed_when_updating(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '小説',
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('genres.update', $genre), [
                'name' => '小説',
            ]);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '小説',
        ]);
    }

    /**
     * 別のジャンルが使用している名前には更新できないこと
     */
    public function test_name_used_by_another_genre_cannot_be_used_for_update(): void
    {
        $user = User::factory()->create();

        Genre::create([
            'name' => '小説',
        ]);

        $genre = Genre::create([
            'name' => 'ビジネス',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), [
                'name' => '小説',
            ]);

        $response->assertRedirect(route('genres.edit', $genre));
        $response->assertSessionHasErrors('name');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => 'ビジネス',
        ]);
    }

    /**
     * 書籍と紐づいていないジャンルを削除できること
     */
    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除対象ジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('success', 'ジャンルを削除しました');

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);
    }

    /**
     * 書籍と紐づいているジャンルは削除できないこと
     */
    public function test_genre_with_books_cannot_be_deleted(): void
    {

        $user = User::factory()->create();

        $genre = Genre::create([

            'name' => '使用中ジャンル',

        ]);

        $book = Book::factory()

            ->for($user)

            ->create();

        $book->genres()->attach($genre->id);

        $response = $this

            ->actingAs($user)

            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));

        $response->assertSessionHas(

            'error',

            'このジャンルには書籍が紐付いているため削除できません'

        );

        $this->assertDatabaseHas('genres', [

            'id' => $genre->id,

            'name' => '使用中ジャンル',

        ]);

        $this->assertDatabaseHas('book_genres', [

            'book_id' => $book->id,

            'genre_id' => $genre->id,

        ]);

    }
}
