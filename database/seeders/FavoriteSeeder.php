<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        $favoriteCounts = [3, 4, 5, 3, 5];

        foreach ($users as $index => $user) {
            $favoriteBookIds = $books
                ->random($favoriteCounts[$index])
                ->pluck('id')
                ->toArray();

            $user->favoriteBooks()->syncWithoutDetaching($favoriteBookIds);
        }
    }
}
