<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Riddle;
use App\Services\Interfaces\ReviewServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    protected $reviewService;

    public function __construct(ReviewServiceInterface $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Get the paginated list of reviews for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  \Illuminate\Http\Request  $request
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

        try {
            $result = $this->reviewService->getPaginatedReviews($riddle, $page, $limit);
            return response()->json($result, Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching reviews for riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des avis.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the 5 last updated reviews for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTopReviewsByRiddle(Riddle $riddle): JsonResponse
    {
        try {
            $reviews = $this->reviewService->getTopReviews($riddle, 5);
            
            return response()->json([
                'items' => $reviews,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error fetching top reviews for riddle {$riddle->id}: " . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la récupération des avis.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new review.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Riddle  $riddle
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Riddle $riddle): JsonResponse
    {
        $userId = $request->user()->id;

        $validatedData = $request->validate([
            'content' => 'required|string|min:2|max:1000',
            'rating' => 'required|integer|min:1|max:5',
            'difficulty' => 'required|integer|min:1|max:5',
        ]);

        try {
            $review = $this->reviewService->createReview($riddle, $userId, $validatedData);
            
            return response()->json([
                'data' => $review,
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error("Error creating review for riddle {$riddle->id} by user {$userId}: " . $e->getMessage());
            return response()->json(['message' =>  $e->getMessage() ?: 'Erreur serveur lors de la création de l\'avis.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a review.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Review $review): JsonResponse
    {
        $validatedData = $request->validate([
            'content' => 'sometimes|required|string|max:1000',
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'difficulty' => 'sometimes|required|integer|min:1|max:5',
        ]);

        try {
            $updatedReview = $this->reviewService->updateReview($review, $validatedData, $request->user()->id);
            
            return response()->json([
                'data' => $updatedReview,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error updating review {$review->id}: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage() ?: 'Erreur serveur lors de la mise à jour de l\'avis.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a review.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        try {
            $this->reviewService->deleteReview($review, $request->user()->id);
            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            Log::error("Error deleting review {$review->id}: " . $e->getMessage());
            return response()->json(['message' =>  $e->getMessage() ?: 'Erreur serveur lors de la suppression de l\'avis.'], $e->getCode() ?: Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}