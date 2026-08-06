<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();

        if ($users->isEmpty() || $books->isEmpty()) {
            return;
        }

        /*
         * BladeやFormRequestで別の値を使用している場合は、
         * この3つだけ実際の値に合わせて変更。
         */
        $plannedStatus = 'planned';
        $readingStatus = 'reading';
        $completedStatus = 'completed';

        /*
         * 動作確認しやすいよう、主要シナリオを
         * 1人目のユーザーに集約。
         */
        $mainUser = $users->first();

        $mainUserPlans = [
            [
                'book_index' => 0,
                'due_date' => Carbon::today()->subDays(3),
                'status' => $completedStatus,
            ],
            [
                'book_index' => 1,
                'due_date' => Carbon::today()->subDay(),
                'status' => $readingStatus,
            ],
            [
                'book_index' => 2,
                'due_date' => Carbon::today(),
                'status' => $readingStatus,
            ],
            [
                'book_index' => 3,
                'due_date' => Carbon::today()->addDays(3),
                'status' => $plannedStatus,
            ],
            [
                'book_index' => 4,
                'due_date' => Carbon::today()->addDays(7),
                'status' => $plannedStatus,
            ],
        ];

        foreach ($mainUserPlans as $plan) {
            $book = $books->get($plan['book_index']);

            if ($book === null) {
                continue;
            }

            ReadingPlan::updateOrCreate(
                [
                    'user_id' => $mainUser->id,
                    'book_id' => $book->id,
                ],
                [
                    'due_date' => $plan['due_date'],
                    'status' => $plan['status'],
                ]
            );
        }

        /*
         * ほかのユーザーにも読書計画を配置。
         */
        $otherUsers = $users->skip(1)->values();

        $statuses = [
            $plannedStatus,
            $readingStatus,
            $completedStatus,
        ];

        $dueDateOffsets = [
            -5,
            -1,
            0,
            2,
            5,
            10,
        ];

        foreach ($otherUsers as $userIndex => $user) {
            /*
             * 各ユーザーに2件ずつ登録
             */
            for ($planIndex = 0; $planIndex < 2; $planIndex++) {
                $bookIndex = (
                    ($userIndex * 2) + $planIndex + 5
                ) % $books->count();

                $statusIndex = (
                    $userIndex + $planIndex
                ) % count($statuses);

                $offsetIndex = (
                    ($userIndex * 2) + $planIndex
                ) % count($dueDateOffsets);

                $book = $books->get($bookIndex);

                ReadingPlan::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'book_id' => $book->id,
                    ],
                    [
                        'due_date' => Carbon::today()->addDays(
                            $dueDateOffsets[$offsetIndex]
                        ),
                        'status' => $statuses[$statusIndex],
                    ]
                );
            }
        }
    }
}
