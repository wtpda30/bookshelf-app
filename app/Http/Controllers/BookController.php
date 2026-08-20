<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $query = Book::with('genres')
            ->withAvg('reviews', 'rating');

        // キーワード検索
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンルフィルタ
        if ($request->filled('genre')) {
            $query->whereHas('genres', function ($q) use ($request) {
                $q->where('genres.id', $request->genre);
            });
        }

        // 並び順
        switch ($request->get('sort', 'newest')) {
            case 'oldest':
                $query->oldest();
                break;

            case 'title':
                $query->orderBy('title', 'asc');
                break;

            case 'rating':
                $query->orderByDesc('reviews_avg_rating');
                break;

            case 'newest':
            default:
                $query->latest();
                break;
        }

        $books = $query
            ->paginate(10)
            ->withQueryString();

        $genres = Genre::all();

        return view('books.index', compact('books', 'genres'));
    }

    public function create()
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        $book = Book::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました');
    }

    public function show(Book $book)
    {
        $book->load([
            'genres',
            'reviews.user',
            'reviews.likedByUsers',
            'favoriteUsers',
        ]);

        return view('books.show', compact('book'));
    }

    public function edit(Book $book)
    {
        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorize('update', $book);

        $validated = $request->validated();

        $book->update([
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'],
            'published_date' => $validated['published_date'],
            'description' => $validated['description'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
        ]);

        $book->genres()->sync($validated['genres']);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました');
    }

    public function destroy(Book $book)
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました');
    }

    public function searchByIsbn(string $isbn)
    {
        // ISBNの形式が不正なとき
        if (! preg_match('/^\d{13}$/', $isbn)) {

            return response()->json([
                'error' => '書籍が見つかりませんでした。',
            ], 404);

        }

        try {
            $response = Http::timeout(5)->get(
                'https://www.googleapis.com/books/v1/volumes',
                [
                    'q' => 'isbn:'.$isbn,
                    'key' => config('services.google_books.key'),
                ]
            );

            if ($response->status() === 429) {
                return response()->json([
                    'error' => 'Google Books APIのクォータを超過しました。.envにGOOGLE_BOOKS_API_KEYを設定してください。',
                ], 429);
            }

            $data = $response->json();

            if (empty($data['items'])) {
                return response()->json([
                    'error' => '書籍が見つかりませんでした。',
                ], 404);
            }
            $volumeInfo = $data['items'][0]['volumeInfo'] ?? [];

            return response()->json([
                'title' => $volumeInfo['title'] ?? '',
                'author' => $volumeInfo['authors'][0] ?? '',
                'isbn' => $isbn,
                'published_date' => $volumeInfo['publishedDate'] ?? null,
                'description' => $volumeInfo['description'] ?? '',
                'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'API通信エラーが発生しました。',
            ], 500);
        }
    }
}
