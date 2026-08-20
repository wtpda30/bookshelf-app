<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を編集できるか
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるか
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;

    }
}
