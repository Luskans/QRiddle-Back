<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class RiddleDetailResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'is_private' => (bool) $this->is_private, // Assurer que c'est un booléen
            'status' => $this->status,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'creator_id' => $this->creator_id,
            'updated_at' => $this->updated_at->toIso8601String(), // Format ISO standard

            // Champs agrégés (doivent être chargés sur le modèle avant)
            'stepsCount' => $this->steps_count, // Sera disponible si chargé avec withCount('steps')
            'reviewsCount' => $this->reviews_count, // Sera disponible si chargé avec withCount('reviews')
            'averageRating' => round($this->reviews_avg_rating, 1) ?? null, // Sera disponible si chargé avec withAvg('reviews', 'rating')
            'averageDifficulty' => round($this->reviews_avg_difficulty, 1) ?? null, // Sera disponible si chargé avec withAvg('reviews', 'difficulty')

            // Mot de passe conditionnel
            // 'when' s'assure que la clé 'password' n'est ajoutée que si la condition est vraie
            'password' => $this->when(
                Auth::check() && Auth::id() === $this->creator_id && $this->is_private,
                $this->password // Renvoie la valeur du mot de passe
            ),
        ];
    }
}
