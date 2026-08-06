<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reviews = Review::all();

        $likeCounts = [0, 1, 2, 3];

        foreach ($reviews as $index => $review) {
            $users = User::where('id', '!=', $review->user_id)->get();

            $count = $likeCounts[$index % count($likeCounts)];

            $likeUserIds = $count === 0
                ? []
                : $users->random($count)->pluck('id')->toArray();

            $review->likedByUsers()->syncWithoutDetaching($likeUserIds);
        }
    }
}
