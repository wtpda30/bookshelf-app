<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return redirect('/books');
});

// 仮ルート（あとで本実装に置き換え）
Route::middleware('auth')->group(function () {
    Route::get('/books', function() {
            return '書籍一覧（準備中）';
        })->name('books.index');
});
