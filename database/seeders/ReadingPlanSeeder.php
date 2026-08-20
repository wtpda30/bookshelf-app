<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();

        if ($users->isEmpty() || $books->isEmpty()) {
            return;
        }

        /*
         * 主要な動作確認用データは1人目のユーザーに集約する
         */
        $mainUser = $users->first();

        $mainUserPlans = [
            // ① 読了済み：通知対象外
            [
                'book_index' => 0,
                'target_date' => Carbon::today()->subDays(5),
                'status' => ReadingPlan::STATUS_COMPLETED,
                'completed_at' => Carbon::today()->subDays(6),
            ],

            // ② 期限切れ前の状態
            // 日次バッチで expired に変更されることを確認する
            [
                'book_index' => 1,
                'target_date' => Carbon::today()->subDay(),
                'status' => ReadingPlan::STATUS_IN_PROGRESS,
                'completed_at' => null,
            ],

            // ③ 期限3日後通知の対象
            [
                'book_index' => 2,
                'target_date' => Carbon::today()->subDays(3),
                'status' => ReadingPlan::STATUS_EXPIRED,
                'completed_at' => null,
            ],

            // ④ 期限当日の通知対象
            [
                'book_index' => 3,
                'target_date' => Carbon::today(),
                'status' => ReadingPlan::STATUS_IN_PROGRESS,
                'completed_at' => null,
            ],

            // ⑤ 期限3日前通知の対象
            [
                'book_index' => 4,
                'target_date' => Carbon::today()->addDays(3),
                'status' => ReadingPlan::STATUS_IN_PROGRESS,
                'completed_at' => null,
            ],

            // ⑥ 通知対象外の通常の進行中計画
            [
                'book_index' => 5,
                'target_date' => Carbon::today()->addDays(7),
                'status' => ReadingPlan::STATUS_IN_PROGRESS,
                'completed_at' => null,
            ],
        ];

        foreach ($mainUserPlans as $plan) {
            $book = $books->get($plan['book_index']);

            if ($book === null) {
                continue;
            }

            ReadingPlan::create([
                'user_id' => $mainUser->id,
                'book_id' => $book->id,
                'target_date' => $plan['target_date'],
                'status' => $plan['status'],
                'completed_at' => $plan['completed_at'],
            ]);
        }

        /*
         * 他ユーザーにも少量の読書計画を配置
         * 認可確認用
         */
        $otherUsers = $users->skip(1)->values();

        foreach ($otherUsers as $userIndex => $user) {
            for ($planIndex = 0; $planIndex < 2; $planIndex++) {
                $bookIndex = (
                    ($userIndex * 2)
                    + $planIndex
                    + 6
                ) % $books->count();

                $book = $books->get($bookIndex);

                if ($book === null) {
                    continue;
                }

                ReadingPlan::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'target_date' => Carbon::today()->addDays(
                        ($userIndex + 1) * 2 + $planIndex
                    ),
                    'status' => ReadingPlan::STATUS_IN_PROGRESS,
                    'completed_at' => null,
                ]);
            }
        }
    }
}
