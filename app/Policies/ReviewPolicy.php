<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
    * レビューを編集できるか
    */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
    /**
    * レビューを削除できるか
    */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
