<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\GenreController;

/**
     * ゲストも閲覧できる書籍ページ
     */
Route::get('/', [BookController::class, 'index']);
Route::get('/books', [BookController::class, 'index'])->name('books.index');

/**
     * ログインが必要なページ
     */
Route::middleware('auth')->group(function () {
    //書籍登録
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    //書籍編集・削除
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    //お気に入り
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    //レビュー
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    //レビューいいね追加・解除
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    // ジャンル一覧
    Route::get('/genres', [GenreController::class, 'index'])
        ->name('genres.index');
    // ジャンル登録画面
    Route::get('/genres/create', [GenreController::class, 'create'])
        ->name('genres.create');
    // ジャンル登録
    Route::post('/genres', [GenreController::class, 'store'])
        ->name('genres.store');
    // 詳細画面
    Route::get('/genres/{genre}', [GenreController::class, 'show'])
        ->name('genres.show');
    // ジャンル編集画面
    Route::get('/genres/{genre}/edit', [GenreController::class, 'edit'])
        ->name('genres.edit');
    // ジャンル更新処理
    Route::put('/genres/{genre}', [GenreController::class, 'update'])
        ->name('genres.update');
    // ジャンル削除処理
    Route::delete('/genres/{genre}', [GenreController::class, 'destroy'])
        ->name('genres.destroy');
});

//書籍詳細
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
