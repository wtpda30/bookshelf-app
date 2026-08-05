<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexBookRequest;
use App\Http\Requests\Api\StoreBookRequest;
use App\Http\Requests\Api\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     *
     * 書籍一覧を取得
     */
    public function index(IndexBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $perPage = $validated['per_page'] ?? 20;

        $books = Book::query()
            // ジャンル情報を取得
            ->with('genres')

            // 平均評価をreviews_avg_ratingとして取得
            ->withAvg('reviews', 'rating')

            // レビュー件数をreviews_countとして取得
            ->withCount('reviews')

            // キーワード検索
            ->when(
                filled($validated['keyword'] ?? null),
                function ($query) use ($validated) {
                    $keyword = $validated['keyword'];

                    $query->where(function ($query) use ($keyword) {
                        $query
                            ->where('title', 'like', "%{$keyword}%")
                            ->orWhere('author', 'like', "%{$keyword}%");
                    });
                }
            )

            // ジャンル検索
            ->when(
                isset($validated['genre_id']),
                function ($query) use ($validated) {
                    $query->whereHas(
                        'genres',
                        function ($query) use ($validated) {
                            $query->where(
                                'genres.id',
                                $validated['genre_id']
                            );
                        }
                    );
                }
            )

            // ID昇順
            ->orderBy('id')

            // 1ページ20件
            ->paginate($perPage);

            return BookResource::collection($books)
    ->additional([
        'message' => '取得成功',
    ])
    ->response()
    ->setStatusCode(200);
    }

    /**
     *
     * 書籍詳細を取得
     */
    public function show(Book $book): JsonResponse
    {
        $book->load([
            'genres',
            // レビュー投稿者も一緒に取得
            'reviews.user',
        ]);

        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return response()->json([
            'message' => '取得成功',
            'data' => new BookResource($book),], 200);
    }

    /**
     *
     * 書籍を登録
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $book = DB::transaction(function () use ($validated) {
            $genreIds = $validated['genre_ids'];

            unset($validated['genre_ids']);

            $book = Book::create($validated);

            $book->genres()->attach($genreIds);

            return $book;
        });

        $book->load('genres');
        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return response()->json([
            'message' => '登録成功',
            'data' => new BookResource($book),], 201);
    }

    /**
     *
     * 書籍を更新
     */
    public function update(UpdateBookRequest $request,Book $book): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $book) {
            $genreIds = $validated['genre_ids'];

            unset($validated['genre_ids']);

            $book->update($validated);

            /*
             * 現在のジャンルを、
             * 送られてきたジャンルIDに置き換える
             */
            $book->genres()->sync($genreIds);
        });

        $book->refresh();

        $book->load('genres');
        $book->loadAvg('reviews', 'rating');
        $book->loadCount('reviews');

        return response()->json([
            'message' => '更新成功',
            'data' => new BookResource($book),], 200);
    }

    /**
     *
     * 書籍を削除
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json(null, 204);
    }
}
