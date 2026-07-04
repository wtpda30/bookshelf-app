<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        $comments = [
            'とても面白かったです。',
            '内容がわかりやすかったです。',
            'もう一度読み返したい本です。',
            '学びが多い一冊でした。',
            '初心者にもおすすめできます。',
            '具体例が多く理解しやすかったです。',
        ];

        $reviewCounts = [4, 4, 3, 3, 3, 3, 3, 3, 2, 2, 2];

        foreach ($books as $index => $book) {
            $selectedUsers = $users->random($reviewCounts[$index]);

        foreach ($selectedUsers as $user) {
                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => rand(3, 5),
                    'comment' => $comments[array_rand($comments)],
                ]);
            }
        }
    }
}
