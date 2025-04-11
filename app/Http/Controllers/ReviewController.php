<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Riddle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * Affiche la liste paginée des avis pour une énigme spécifique.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Riddle $riddle, Request $request): JsonResponse
    {
        // Validation pour la pagination
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
        ]);

        $limit = $validated['limit'] ?? 15; // Limite par défaut
        $offset = $validated['offset'] ?? 0;

        // Récupérer les avis pour cette énigme, avec les infos de l'utilisateur
        // Trier par date de création la plus récente
        $reviewsQuery = $riddle->reviews()
                               ->with('user:id,name,image') // Charger les infos de l'auteur
                               ->latest(); // Ou orderBy('created_at', 'desc')

        // Compter le total avant la pagination
        $totalCount = $reviewsQuery->count();

        // Appliquer la pagination
        $reviews = $reviewsQuery->skip($offset)->take($limit)->get();

        return response()->json([
            'reviews' => $reviews,
            'meta' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $totalCount,
                'hasMore' => ($offset + count($reviews)) < $totalCount,
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Enregistre un nouvel avis pour une énigme.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Riddle $riddle): JsonResponse
    {
        $userId = Auth::id();

        // Validation des données reçues
        $validatedData = $request->validate([
            'content' => 'required|string|max:1000', // Limite de caractères pour le contenu
            'rating' => 'required|integer|min:1|max:5', // Note entre 1 et 5
            // 'difficulty' => 'required|integer|min:1|max:5', // Si tu gardes la difficulté sur la review
        ]);

        // Vérification : L'utilisateur a-t-il déjà laissé un avis pour cette énigme ?
        // (Optionnel, mais souvent souhaité pour éviter les avis multiples)
        $existingReview = $riddle->reviews()->where('user_id', $userId)->first();
        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this riddle.'], Response::HTTP_CONFLICT); // 409 Conflict
        }

        // Vérification : L'utilisateur a-t-il terminé cette énigme ?
        // (Optionnel, mais logique : on ne devrait pouvoir noter qu'après avoir joué)
        // $hasCompleted = $riddle->gameSessions()
        //                        ->where('player_id', $userId)
        //                        ->where('status', 'completed') // Assure-toi que ce statut est correct
        //                        ->exists();
        // if (!$hasCompleted) {
        //     return response()->json(['message' => 'You must complete the riddle before reviewing it.'], Response::HTTP_FORBIDDEN); // 403 Forbidden
        // }

        // Créer l'avis
        try {
            $review = $riddle->reviews()->create([
                'user_id' => $userId,
                'content' => $validatedData['content'],
                'rating' => $validatedData['rating'],
                // 'difficulty' => $validatedData['difficulty'], // Si applicable
            ]);

            // Recharger l'avis avec les infos utilisateur pour la réponse
            $review->load('user:id,name,image');

            return response()->json($review, Response::HTTP_CREATED); // 201 Created

        } catch (\Exception $e) {
            Log::error("Error creating review for riddle {$riddle->id} by user {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to create review.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche les détails d'un avis spécifique.
     * (Accessible via GET /reviews/{review} grâce à ->shallow())
     *
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Review $review): JsonResponse
    {
        // Charger les relations si nécessaire (ex: utilisateur, énigme)
        $review->load(['user:id,name,image', 'riddle:id,title']);

        return response()->json($review, Response::HTTP_OK);
    }

    /**
     * Met à jour un avis existant.
     * (Accessible via PUT /reviews/{review} grâce à ->shallow())
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        // 1. Autorisation : Seul l'auteur de l'avis peut le modifier
        if (Auth::id() !== $review->user_id) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN); // 403 Forbidden
        }

        // 2. Validation des données (similaire à store, mais pas forcément tout requis)
        $validatedData = $request->validate([
            'content' => 'sometimes|required|string|max:1000',
            'rating' => 'sometimes|required|integer|min:1|max:5',
            // 'difficulty' => 'sometimes|required|integer|min:1|max:5',
        ]);

        // 3. Mettre à jour l'avis
        try {
            $review->update($validatedData);

            // Recharger avec l'utilisateur pour la réponse
            $review->load('user:id,name,image');

            return response()->json($review, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating review {$review->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update review.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un avis.
     * (Accessible via DELETE /reviews/{review} grâce à ->shallow())
     *
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Review $review): JsonResponse
    {
        // 1. Autorisation : Seul l'auteur ou un admin peut supprimer
        // (Ajouter une logique d'admin si nécessaire)
        if (Auth::id() !== $review->user_id /* && !Auth::user()->isAdmin() */) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // 2. Supprimer l'avis
        try {
            $review->delete();
            return response()->json(null, Response::HTTP_NO_CONTENT); // 204 No Content

        } catch (\Exception $e) {
            Log::error("Error deleting review {$review->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete review.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}