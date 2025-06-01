<?php

namespace App\Http\NewControllers;

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
     * Get the paginated list of reviews for a riddle.
     *
     * @param  \App\Models\Riddle $riddle
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Riddle $riddle, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;
        $offset = ($page - 1) * $limit;
        
        $query = Review::query()
            ->select(['id', 'user_id', 'content', 'rating', 'difficulty', 'updated_at'])
            ->where('riddle_id', $riddle->id)
            ->orderBy('updated_at', 'desc')
            ->with('user:id,name,image');

        $totalQuery = clone $query;
        $totalCount = $totalQuery->count();
        $totalPages = ceil($totalCount / $limit);

        $reviews = $query->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'items' => $reviews,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ], Response::HTTP_OK);
    }


    /**
     * Get the 5 last updated reviews for a riddle.
     *
     * @param  \App\Models\Riddle $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopReviewsByRiddle(Riddle $riddle)
    {
        $reviews = Review::query()
            ->select(['id', 'user_id', 'content', 'rating', 'difficulty', 'updated_at'])
            ->where('riddle_id', $riddle->id)
            ->orderBy('updated_at', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        return response()->json([
            'items' => $reviews,
        ], Response::HTTP_OK);
    }


    /**
     * Create a new review.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Riddle $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Riddle $riddle): JsonResponse
    {
        $userId = Auth::id();

        $validatedData = $request->validate([
            'content' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
            'difficulty' => 'required|integer|min:1|max:5',
        ]);

        $existingReview = $riddle->reviews()
            ->where('user_id', $userId)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'Vous avez déjà laissé un avis pour cette énigme.'], Response::HTTP_FORBIDDEN);
        }

        $gameCompleted = $riddle->gameSessions()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->exists();

        if (!$gameCompleted) {
            return response()->json(['message' => 'Vous devez avoir terminé l\'énigme pour laisser un avis.'], Response::HTTP_FORBIDDEN); // 403 Forbidden
        }

        try {
            $review = $riddle->reviews()->create([
                'user_id' => $userId,
                'content' => $validatedData['content'],
                'rating' => $validatedData['rating'],
                'difficulty' => $validatedData['difficulty'],
            ]);

            return response()->json([
                'data' => $review,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error("Error creating review for riddle {$riddle->id} by user {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la création de l\'avis a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Update a review.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Review $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        if (Auth::id() !== $review->user_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN); // 403 Forbidden
        }

        $validatedData = $request->validate([
            'content' => 'sometimes|required|string|max:1000',
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'difficulty' => 'sometimes|required|integer|min:1|max:5',
        ]);

        try {
            $review->update($validatedData);
            $review->refresh();

            return response()->json([
                'data' => $review,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating review {$review->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la mise à jour de l\'avis a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Delete a review.
     *
     * @param  \App\Models\Review $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Review $review): JsonResponse
    {
        if (Auth::id() !== $review->user_id) {
            return response()->json(['message' => 'Utilisateur non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $review->delete();
            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting review {$review->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur : la suppression de l\'avis a échouée.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}