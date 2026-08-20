<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessReadingPlanRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト内の「今日」を固定
        Carbon::setTestNow(
            Carbon::create(2026, 8, 20, 12, 0, 0)
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 期限3日前の進行中計画に通知が作成される
     */
    public function test_notification_is_created_three_days_before_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'テスト書籍',
        ]);

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        $notification = $user->notifications()->first();

        $this->assertSame(
            'three_days_before',
            $notification->data['timing']
        );

        $this->assertSame(
            $readingPlan->id,
            $notification->data['reading_plan_id']
        );
    }

    /**
     * 期限当日の進行中計画に通知が作成される
     */
    public function test_notification_is_created_on_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'テスト書籍',
        ]);

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $notification = $user->notifications()->first();

        $this->assertNotNull($notification);

        $this->assertSame(
            'on_due_date',
            $notification->data['timing']
        );

        $this->assertSame(
            $readingPlan->id,
            $notification->data['reading_plan_id']
        );
    }

    /**
     * 期限3日後の計画に通知が作成される
     */
    public function test_notification_is_created_three_days_after_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'テスト書籍',
        ]);

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->subDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $notification = $user->notifications()->first();

        $this->assertNotNull($notification);

        $this->assertSame(
            'three_days_after',
            $notification->data['timing']
        );

        $this->assertSame(
            $readingPlan->id,
            $notification->data['reading_plan_id']
        );
    }

    /**
     * 期限を過ぎた進行中計画はexpiredになる
     */
    public function test_past_in_progress_plan_becomes_expired(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->subDay()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);
    }

    /**
     * 3日前・当日・3日後以外には通知されない
     */
    public function test_notification_is_not_created_outside_reminder_dates(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(5)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * completedの読書計画には通知されない
     */
    public function test_completed_plan_does_not_receive_notification(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * 通知は読書計画の所有ユーザーにだけ作成される
     */
    public function test_notification_is_created_only_for_plan_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $otherUser->id,
        ]);

        $notification = $user->notifications()->first();

        $this->assertSame(
            $readingPlan->id,
            $notification->data['reading_plan_id']
        );
    }

    /**
     * 通知内容がnotificationsテーブルに正しく保存される
     */
    public function test_notification_data_is_saved_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'Laravel入門',
        ]);

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $notification = $user->notifications()->first();

        $this->assertNotNull($notification);

        $this->assertSame(
            '読書期限が近づいています',
            $notification->data['title']
        );

        $this->assertSame(
            '「Laravel入門」の読書期限まであと3日です。',
            $notification->data['body']
        );

        $this->assertSame(
            'three_days_before',
            $notification->data['timing']
        );

        $this->assertSame(
            $readingPlan->id,
            $notification->data['reading_plan_id']
        );
    }

    /**
     * 同じ読書計画でも3日前・当日・3日後にそれぞれ通知できる
     */
    public function test_same_plan_can_receive_notifications_at_each_timing(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'title' => 'テスト書籍',
        ]);

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-08-23',
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        // 3日前
        Carbon::setTestNow(
            Carbon::create(2026, 8, 20, 12, 0, 0)
        );

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        // 当日
        Carbon::setTestNow(
            Carbon::create(2026, 8, 23, 12, 0, 0)
        );

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        // 3日後
        Carbon::setTestNow(
            Carbon::create(2026, 8, 26, 12, 0, 0)
        );

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $notifications = $user
            ->notifications()
            ->get()
            ->pluck('data')
            ->pluck('timing');

        $this->assertCount(3, $notifications);

        $this->assertTrue(
            $notifications->contains('three_days_before')
        );

        $this->assertTrue(
            $notifications->contains('on_due_date')
        );

        $this->assertTrue(
            $notifications->contains('three_days_after')
        );
    }

    /**
     * 複数の対象読書計画がすべて処理される
     */
    public function test_multiple_reading_plans_are_processed(): void
    {

        $user1 = User::factory()->create();

        $user2 = User::factory()->create();

        $book1 = Book::factory()->create();

        $book2 = Book::factory()->create();

        ReadingPlan::factory()->create([

            'user_id' => $user1->id,
            'book_id' => $book1->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        ReadingPlan::factory()->create([

            'user_id' => $user2->id,
            'book_id' => $book2->id,
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 2);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user1->id,
            'notifiable_type' => User::class,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user2->id,
            'notifiable_type' => User::class,
        ]);
    }

    /**
     * 対象0件でもエラーにならず正常終了する
     */
    public function test_command_succeeds_when_there_are_no_target_plans(): void
    {
        $this->artisan('app:process-reading-plan-reminders')

            ->expectsOutput('読書計画の日次処理が完了しました。')

            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * 本日期限の読書計画はexpiredにならない
     */
    public function test_plan_due_today_does_not_become_expired(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * 未来期限の読書計画はexpiredにならない
     */
    public function test_future_plan_does_not_become_expired(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(5)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * completedの読書計画はexpiredにならない
     */
    public function test_completed_plan_does_not_become_expired(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->subDays(5)->toDateString(),
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now()->subDays(2),
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
        ]);
    }

    /**
     * 対象以外の正常な計画には影響しない
     */
    public function test_other_normal_plans_are_not_affected(): void
    {
        $user = User::factory()->create();

        $expiredBook = Book::factory()->create();
        $futureBook = Book::factory()->create();
        $completedBook = Book::factory()->create();

        $expiredTarget = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $expiredBook->id,
            'target_date' => now()->subDay()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $futurePlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $futureBook->id,
            'target_date' => now()->addDays(10)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $completedPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $completedBook->id,
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ReadingPlan::STATUS_COMPLETED,
            'completed_at' => now()->subDays(3),
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $expiredTarget->id,
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $futurePlan->id,
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $completedPlan->id,
            'status' => ReadingPlan::STATUS_COMPLETED,
        ]);
    }

    /**
     * 失効対象が複数あればすべてexpiredになる
     */
    public function test_multiple_expired_targets_are_all_processed(): void
    {
        $user = User::factory()->create();

        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        $plan1 = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => now()->subDay()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $plan2 = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => now()->subDays(2)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $plan3 = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        foreach ([$plan1, $plan2, $plan3] as $plan) {
            $this->assertDatabaseHas('reading_plans', [
                'id' => $plan->id,
                'status' => ReadingPlan::STATUS_EXPIRED,
            ]);
        }
    }

    /**
     * 対象0件でも正常終了する
     */
    public function test_command_succeeds_when_there_are_no_expiration_targets(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(10)->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);
    }

    /**
     * バッチを複数回実行しても状態が壊れない
     */
    public function test_running_expiration_batch_multiple_times_does_not_break_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $readingPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => now()->subDay()->toDateString(),
            'status' => ReadingPlan::STATUS_IN_PROGRESS,
        ]);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->artisan('app:process-reading-plan-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'status' => ReadingPlan::STATUS_EXPIRED,
        ]);
    }
}
