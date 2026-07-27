<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookController;

Route::prefix('v1')->group(function () {
    // 書籍一覧
    Route::get('/books', [BookController::class, 'index'])->name('api.books.index');

    // 書籍詳細
    Route::get('/books/{book}', [BookController::class, 'show'])->name('api.books.show');

    // 書籍登録
    Route::post('/books', [BookController::class, 'store'])->name('api.books.store');

    // 書籍更新
    Route::put('/books/{book}', [BookController::class, 'update'])->name('api.books.update');

    // 書籍削除
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('api.books.destroy');
});
