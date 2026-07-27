<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * 書籍をJSON形式に整形
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,

            'published_date' => $this->published_date,
            'description' => $this->description,
            'image_url' => $this->image_url,

            'genres' => $this->whenLoaded('genres', fn ()=>$this->genres->map(function ($genre) {
                    return [
                        'id' => $genre->id,
                        'name' => $genre->name,
                    ];
                })
            ),

            // 小数点第1位
            'average_rating' => $this->reviews_avg_rating === null
                ? null
                : round((float) $this->reviews_avg_rating, 1),

            'reviews_count' => (int) ($this->reviews_count ?? 0),

            // 詳細取得時のみ含まれる
            'reviews' => ReviewResource::collection(
                $this->whenLoaded('reviews')
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
