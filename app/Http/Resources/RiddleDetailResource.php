<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiddleDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isCreator = $user && $user->id === $this->creator_id;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'is_private' => (bool) $this->is_private,
            'status' => $this->status,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'updated_at' => $this->updated_at->toIso8601String(),
            'stepsCount' => $this->steps_count,
            'reviewsCount' => $this->reviews_count,
            'averageRating' => $this->reviews_avg_rating ? round($this->reviews_avg_rating, 1) : null,
            'averageDifficulty' => $this->reviews_avg_difficulty ? round($this->reviews_avg_difficulty, 1) : null,
            'password' => $this->when($isCreator, $this->password),
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'image' => $this->creator->image,
            ],
            'steps' => $this->when($isCreator, function() {
                return $this->steps->map(function($step) {
                    return [
                        'id' => $step->id,
                        'order_number' => $step->order_number,
                        'qr_code' => $step->qr_code,
                    ];
                });
            }),
        ];
    }
}
