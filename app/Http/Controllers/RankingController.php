<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        $rankedBooks = Book::query()
            // 平均評価を reviews_avg_rating として取得
            ->withAvg('reviews', 'rating')

            // レビュー件数を reviews_count として取得
            ->withCount('reviews')

            // レビューが1件以上ある書籍だけを対象にする
            ->whereHas('reviews')

            // 平均評価が高い順
            ->orderByDesc('reviews_avg_rating')

            // 平均評価が同じ場合はレビュー件数が多い順
            ->orderByDesc('reviews_count')

            // それでも同じ場合はIDが小さい順
            ->orderBy('id')

            // 上位10件
            ->limit(10)

            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
