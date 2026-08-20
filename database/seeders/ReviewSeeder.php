<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        Review::query()->delete();

        /*
         * 評価別の日本語コメント
         */
        $commentsByRating = [
            1 => [
                '期待していた内容とは異なりました。',
                '内容が少し分かりにくかったです。',
                '最後まで読むのが難しかったです。',
                '自分には合わない内容でした。',
                'もう少し説明が欲しかったです。',
            ],
            2 => [
                '少し物足りなさを感じました。',
                '分かりにくい部分がありました。',
                '期待したほどではありませんでした。',
                '参考になる部分は少なめでした。',
                'もう少し内容が充実していると良かったです。',
            ],
            3 => [
                '全体的に読みやすい本でした。',
                '参考になる部分がありました。',
                '内容はおおむね理解できました。',
                '気軽に読める一冊でした。',
                '標準的な内容だと思います。',
            ],
            4 => [
                '内容が分かりやすかったです。',
                'とても参考になりました。',
                '読みやすく勉強になりました。',
                'もう一度読み返したい本です。',
                '多くの学びが得られました。',
            ],
            5 => [
                'とても素晴らしい本でした。',
                '内容が非常に分かりやすかったです。',
                '多くの人におすすめしたい本です。',
                '何度も読み返したい一冊です。',
                '期待以上の内容でした。',
            ],
        ];

        foreach ($books as $book) {
            /*
             * 各書籍に2〜4件のレビューを登録
             */
            $reviewCount = random_int(2, 4);

            /*
             * 同じ書籍へ同じユーザーが重複投稿しないように、
             * 必要人数だけユーザーを抽出
             */
            $selectedUsers = $users->random($reviewCount);

            foreach ($selectedUsers as $user) {
                $rating = random_int(1, 5);

                $comments = $commentsByRating[$rating];

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => $comments[array_rand($comments)],
                ]);
            }
        }
    }
}
