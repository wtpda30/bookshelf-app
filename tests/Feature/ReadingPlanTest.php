<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証ユーザーは読書計画一覧を表示できる
     */
    public function test_authenticated_user_can_view_reading_plan_index(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('reading-plans.index'));

        $response->assertStatus(200);
        $response->assertViewIs('reading-plans.index');
    }

    /**
     * 未認証ユーザーは一覧画面からログイン画面へリダイレクトされる
     */
    public function test_guest_is_redirected_to_login_from_reading_plan_index(): void
    {
        $response = $this->get(route('reading-plans.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 自分の読書計画だけ表示される
     */
    public function test_only_logged_in_users_reading_plans_are_displayed(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myBook = Book::factory()->create([
            'title' => '自分の本',
        ]);

        $otherBook = Book::factory()->create([
            'title' => '他人の本',
        ]);

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $myBook->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $otherBook->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reading-plans.index'));

        $response->assertStatus(200);

        $response->assertViewHas('readingPlans', function ($readingPlans) use ($user) {
            return $readingPlans->every(
                fn ($plan) => $plan->user_id === $user->id
            );
        });
    }

    /**
     * 書籍と期限を指定して読書計画を登録できる
     */
    public function test_user_can_create_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => now()->addWeek()->toDateString(),
            ]);

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を作成しました');

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * 登録するとステータスは進行中になる
     */
    public function test_new_reading_plan_status_is_in_progress(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => now()->addDays(7)->toDateString(),
            ]);

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);
    }

    /**
     * 必須項目なしでは登録できない
     */
    public function test_reading_plan_cannot_be_created_without_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('reading-plans.create'))
            ->post(route('reading-plans.store'), []);

        $response->assertRedirect(route('reading-plans.create'));

        $response->assertSessionHasErrors([
            'book_id',
            'target_date',
        ]);
    }

    /**
     * 存在しない書籍IDでは登録できない
     */
    public function test_reading_plan_cannot_be_created_with_nonexistent_book(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => 999999,
                'target_date' => now()->addWeek()->toDateString(),
            ]);

        $response->assertSessionHasErrors('book_id');

        $this->assertDatabaseCount('reading_plans', 0);
    }

    /**
     * 同じユーザーが同じ本の進行中計画を重複登録できない
     */
    public function test_user_cannot_create_duplicate_in_progress_plan_for_same_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => now()->addDays(10)->toDateString(),
            ]);

        $response->assertSessionHasErrors('book_id');

        $this->assertDatabaseCount('reading_plans', 1);
    }

    /**
     * 状態で絞り込みできる
     */
    public function test_reading_plans_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();

        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        $inProgress = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reading-plans.index', [
                'status' => ReadingPlan::STATUS_IN_PROGRESS,
            ]));

        $response->assertStatus(200);

        $response->assertViewHas('readingPlans', function ($readingPlans) use ($inProgress) {
            return $readingPlans->count() === 1
                && $readingPlans->first()->id === $inProgress->id;
        });
    }

    /**
     * 「完了する」を押すと読了状態になる
     */
    public function test_user_can_complete_own_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
            'completed_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.complete', $readingPlan));

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を完了しました');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
        ]);

        $this->assertNotNull(
            $readingPlan->fresh()->completed_at
        );
    }

    /**
     * 他ユーザーの読書計画は完了できない
     */
    public function test_user_cannot_complete_another_users_reading_plan(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.complete', $readingPlan));

        $response->assertForbidden();

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * 自分の読書計画編集画面を表示できる
     */
    public function test_user_can_view_edit_page_for_own_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reading-plans.edit', $readingPlan));

        $response->assertStatus(200);
        $response->assertViewIs('reading-plans.edit');
        $response->assertViewHas('readingPlan');
    }

    /**
     * 現在の期限が編集画面の初期値として表示される
     */
    public function test_current_target_date_is_displayed_on_edit_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $targetDate = now()->addDays(10)->toDateString();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reading-plans.edit', $readingPlan));

        $response->assertStatus(200);
        $response->assertViewIs('reading-plans.edit');

        $response->assertViewHas('readingPlan', function ($plan) use ($targetDate) {
            return $plan->target_date->toDateString() === $targetDate;
        });

        $response->assertSee(
            'value="'.$targetDate.'"',
            false
        );
    }

    /**
     * 他ユーザーの読書計画編集画面は表示できない
     */
    public function test_user_cannot_edit_another_users_reading_plan(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reading-plans.edit', $readingPlan));

        $response->assertForbidden();
    }

    /**
     * 自分の読書計画の期限を変更できる
     */
    public function test_user_can_update_own_reading_plan_target_date(): void
    {

        $user = User::factory()->create();

        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([

            'user_id' => $user->id,

            'book_id' => $book->id,

            'target_date' => now()->addDays(5)->toDateString(),

            'status' => ReadingPlan::STATUS_IN_PROGRESS,

        ]);

        $newDate = now()->addDays(20)->toDateString();

        $response = $this

            ->actingAs($user)

            ->put(route('reading-plans.update', $readingPlan), [

                'target_date' => $newDate,

            ]);

        $response->assertRedirect(route('reading-plans.index'));

        $response->assertSessionHas('success', '読書計画を更新しました');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'target_date' => $newDate.' 00:00:00',
        ]);
    }

    /**
     * 過去の日付には変更できない
     */
    public function test_target_date_cannot_be_updated_to_past_date(): void
    {

        $user = User::factory()->create();

        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([

            'user_id' => $user->id,

            'book_id' => $book->id,

            'target_date' => now()->addDays(5)->toDateString(),

            'status' => ReadingPlan::STATUS_IN_PROGRESS,

        ]);

        $originalDate = $readingPlan->target_date->toDateString();

        $response = $this

            ->actingAs($user)

            ->put(route('reading-plans.update', $readingPlan), [

                'target_date' => now()->subDay()->toDateString(),

            ]);

        $response->assertSessionHasErrors('target_date');

        $this->assertSame(

            $originalDate,

            $readingPlan->fresh()->target_date->toDateString()

        );
    }

    /**
     * 他ユーザーの読書計画は更新できない
     */
    public function test_user_cannot_update_another_users_reading_plan(): void
    {

        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([

            'user_id' => $otherUser->id,

            'book_id' => $book->id,

            'status' => ReadingPlan::STATUS_IN_PROGRESS,

        ]);

        $response = $this

            ->actingAs($user)

            ->put(route('reading-plans.update', $readingPlan), [

                'target_date' => now()->addDays(20)->toDateString(),

            ]);

        $response->assertForbidden();
    }

    /**
     * 期限切れの計画を未来日に変更すると進行中に戻る
     */
    public function test_expired_plan_returns_to_in_progress_when_target_date_is_changed_to_future(): void
    {

        $user = User::factory()->create();

        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([

            'user_id' => $user->id,

            'book_id' => $book->id,

            'target_date' => now()->subDay()->toDateString(),

            'status' => ReadingPlan::STATUS_EXPIRED,

        ]);

        $response = $this

            ->actingAs($user)

            ->put(route('reading-plans.update', $readingPlan), [

                'target_date' => now()->addDays(10)->toDateString(),

            ]);

        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [

            'id' => $readingPlan->id,

            'status' => ReadingPlan::STATUS_IN_PROGRESS,

        ]);
    }

    /**
     * 自分の読書計画を削除できる
     */
    public function test_user_can_delete_own_reading_plan(): void
    {

        $user = User::factory()->create();

        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([

            'user_id' => $user->id,

            'book_id' => $book->id,

            'status' => ReadingPlan::STATUS_IN_PROGRESS,

        ]);

        $response = $this

            ->actingAs($user)

            ->delete(route('reading-plans.destroy', $readingPlan));

        $response->assertRedirect(route('reading-plans.index'));

        $response->assertSessionHas('success', '読書計画を削除しました');

        $this->assertDatabaseMissing('reading_plans', [

            'id' => $readingPlan->id,

        ]);
    }

    /**
     * 他ユーザーの読書計画は削除できない
     */
    public function test_user_cannot_delete_another_users_reading_plan(): void
    {

        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([

            'user_id' => $otherUser->id,

            'book_id' => $book->id,

            'status' => ReadingPlan::STATUS_IN_PROGRESS,

        ]);

        $response = $this

            ->actingAs($user)

            ->delete(route('reading-plans.destroy', $readingPlan));

        $response->assertForbidden();

        $this->assertDatabaseHas('reading_plans', [

            'id' => $readingPlan->id,

        ]);
    }

    /**
     * 存在しない読書計画は404
     */
    public function test_nonexistent_reading_plan_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this

            ->actingAs($user)

            ->get('/reading-plans/999999/edit');

        $response->assertNotFound();
    }
}
