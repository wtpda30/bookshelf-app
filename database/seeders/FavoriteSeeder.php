<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Book;
use App\Models\User;

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
