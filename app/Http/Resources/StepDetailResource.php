<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class StepDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'qr_code' => $this->qr_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'hints' => $this->hints->map(function($hint) {
                return [
                    'id' => $hint->id,
                    'order_number' => $hint->order_number,
                    'type' => $hint->type,
                    'content' => $hint->content
                ];
            }),
        ];
    }
}
