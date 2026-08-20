<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessReadingPlanReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-reading-plan-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        $readingPlans = ReadingPlan::with(['user', 'book'])
            ->whereIn('status', [
                ReadingPlanStatus::InProgress->value,
                ReadingPlanStatus::Expired->value,
            ])
            ->get();

        foreach ($readingPlans as $readingPlan) {
            $targetDate = $readingPlan->target_date;

            // 期限3日前
            if (
                $readingPlan->status === ReadingPlanStatus::InProgress
                && $today->equalTo($targetDate->copy()->subDays(3))
            ) {
                $readingPlan->user->notify(
                    new ReadingPlanReminder(
                        '読書期限が近づいています',
                        "「{$readingPlan->book->title}」の読書期限まであと3日です。",
                        'three_days_before',
                        $readingPlan->id
                    )
                );
            }

            // 期限当日
            if (
                $readingPlan->status === ReadingPlanStatus::InProgress
                && $today->equalTo($targetDate)
            ) {
                $readingPlan->user->notify(
                    new ReadingPlanReminder(
                        '今日は読書期限です',
                        "「{$readingPlan->book->title}」の読書期限は今日です。",
                        'on_due_date',
                        $readingPlan->id
                    )
                );
            }

            // 期限を過ぎた進行中の計画を期限切れにする
            if (
                $readingPlan->status === ReadingPlanStatus::InProgress
                && $targetDate->isBefore($today)
            ) {
                $readingPlan->update([
                    'status' => ReadingPlanStatus::Expired->value,
                ]);
            }

            // 期限3日後
            if ($today->equalTo($targetDate->copy()->addDays(3))) {
                $readingPlan->user->notify(
                    new ReadingPlanReminder(
                        '読書期限を過ぎています',
                        "「{$readingPlan->book->title}」の読書期限を3日過ぎています。",
                        'three_days_after',
                        $readingPlan->id
                    )
                );
            }
        }

        $this->info('読書計画の日次処理が完了しました。');

        return Command::SUCCESS;
    }
}
