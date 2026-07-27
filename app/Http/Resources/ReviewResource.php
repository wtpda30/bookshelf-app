<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * レビューをJSON形式に整形
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'user_id' => $this->user_id,

            'user_name' => $this->whenLoaded(
                'user',
                fn () => $this->user?->name
            ),

            'rating' => $this->rating,
            'comment' => $this->comment,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
