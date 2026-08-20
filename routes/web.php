<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/**
 * ゲストも閲覧できる書籍ページ
 */
Route::get('/', [BookController::class, 'index']);
Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

/**
 * ログインが必要なページ
 */
Route::middleware('auth')->group(function () {
    // 書籍登録
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    // 書籍編集・削除
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    // お気に入り
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    // レビュー
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    // レビューいいね追加・解除
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    // ジャンル一覧
    Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');
    // ジャンル登録画面
    Route::get('/genres/create', [GenreController::class, 'create'])->name('genres.create');
    // ジャンル登録
    Route::post('/genres', [GenreController::class, 'store'])->name('genres.store');
    // 詳細画面
    Route::get('/genres/{genre}', [GenreController::class, 'show'])->name('genres.show');
    // isbn検索
    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])->name('books.isbn');
    // ジャンル編集画面
    Route::get('/genres/{genre}/edit', [GenreController::class, 'edit'])->name('genres.edit');
    // ジャンル更新処理
    Route::put('/genres/{genre}', [GenreController::class, 'update'])->name('genres.update');
    // ジャンル削除処理
    Route::delete('/genres/{genre}', [GenreController::class, 'destroy'])->name('genres.destroy');
    // 読書計画
    Route::get('/reading-plans', [ReadingPlanController::class, 'index'])->name('reading-plans.index');

    Route::get('/reading-plans/create', [ReadingPlanController::class, 'create'])->name('reading-plans.create');

    Route::post('/reading-plans', [ReadingPlanController::class, 'store'])->name('reading-plans.store');

    Route::get('/reading-plans/{readingPlan}/edit', [ReadingPlanController::class, 'edit'])->name('reading-plans.edit');

    Route::put('/reading-plans/{readingPlan}', [ReadingPlanController::class, 'update'])->name('reading-plans.update');

    Route::delete('/reading-plans/{readingPlan}', [ReadingPlanController::class, 'destroy'])->name('reading-plans.destroy');

    Route::post('/reading-plans/{readingPlan}/complete', [ReadingPlanController::class, 'complete'])->name('reading-plans.complete');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // 通知
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

// 書籍詳細
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
