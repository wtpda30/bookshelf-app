<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧を表示する
     */
    public function index(): View
    {
        $books = auth()->user()
            ->favoriteBooks()
            ->with('genres')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * お気に入りの追加・解除を切り替える
     */
    public function toggle(Book $book): RedirectResponse
    {
        auth()->user()
            ->favoriteBooks()
            ->toggle($book->id);

        return back();
    }
}
