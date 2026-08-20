<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlan;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // 自分が投稿したレビュー
        $reviews = Review::with('book.genres')
            ->where('user_id', $userId)
            ->whereHas('book')
            ->get();

        // ① 基本統計
        $totalReviews = $reviews->count();

        $booksRead = ReadingPlan::where('user_id', $userId)
            ->where('status', ReadingPlan::STATUS_COMPLETED)
            ->count();

        $averageRating = $reviews->avg('rating') ?? 0;

        // ② 評価分布（★1〜★5）
        $ratingDistribution = collect(range(1, 5))
            ->mapWithKeys(function ($rating) use ($reviews) {
                return [
                    $rating => $reviews->where('rating', $rating)->count(),
                ];
            });

        // ③高評価書籍 TOP5
        $topRatedBooks = $reviews
            ->where('rating', '>=', 4)
            ->sortByDesc('rating')
            ->take(5)
            ->map(function ($review) {
                return [
                    'id' => $review->book->id,
                    'title' => $review->book->title,
                    'author' => $review->book->author,
                    'rating' => $review->rating,
                ];
            })
            ->values();

        // ④ジャンル別評価傾向 TOP5
        $genreRatings = $reviews
            ->flatMap(function ($review) {
                return $review->book->genres->map(function ($genre) use ($review) {
                    return [
                        'id' => $genre->id,
                        'name' => $genre->name,
                        'rating' => $review->rating,
                    ];
                });
            })
            ->groupBy('id')
            ->map(function ($items) {
                return [
                    'id' => $items->first()['id'],
                    'name' => $items->first()['name'],
                    'count' => $items->count(),
                    'average_rating' => $items->avg('rating'),
                ];
            })
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();

        $stats = [
            'summary' => [
                'total_reviews' => $totalReviews,
                'books_read' => $booksRead,
                'average_rating' => $averageRating,
            ],

            'rating_distribution' => $ratingDistribution,

            'top_rated_books' => $topRatedBooks,

            'genre_ratings' => $genreRatings,
        ];

        return view('reports.index', compact('stats'));
    }
}
