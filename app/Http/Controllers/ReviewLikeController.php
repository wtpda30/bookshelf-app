<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use App\Models\Review;


class ReviewLikeController extends Controller
{
    /**
     * レビューのいいね追加・解除を切り替える
     */
    public function toggle(Review $review): RedirectResponse
    {
        auth()->user()->likedReviews()->toggle($review->id);

        return back();
    }
}
