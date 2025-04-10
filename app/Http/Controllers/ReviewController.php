<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Riddle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(Request $request, Riddle $riddle): JsonResponse
    {
        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $reviews = $riddle->reviews()
            ->with('user:id,name,image')
            ->latest()
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'reviews' => $reviews
        ], Response::HTTP_OK);
    }

    /**
     * Crée un nouvel avis pour une énigme.
     */
    public function store(Request $request, $riddleId)
    {
        // $validated = $request->validate([
        //     'user_id' => 'required|exists:users,id',
        //     'content' => 'required|string',
        //     'rating'  => 'required|integer|between:1,5',
        // ]);
        // $validated['riddle_id'] = $riddleId;
        // $review = Review::create($validated);
        // return response()->json($review, Response::HTTP_CREATED);
    }

    /**
     * Affiche les détails d'un avis.
     */
    public function show($riddleId, $reviewId)
    {
        // $review = Review::where('riddle_id', $riddleId)->findOrFail($reviewId); 
        // return response()->json($review, Response::HTTP_OK);
    }

    /**
     * Met à jour un avis.
     */
    public function update(Request $request, $riddleId, $reviewId)
    {
        // $review = Review::where('riddle_id', $riddleId)->findOrFail($reviewId);   
        // $validated = $request->validate([
        //     'content' => 'sometimes|required|string',
        //     'rating'  => 'sometimes|required|integer|between:1,5',
        // ]);   
        // $review->update($validated);
        // return response()->json($review, Response::HTTP_OK);
    }

    /**
     * Supprime un avis.
     */
    public function destroy($riddleId, $reviewId)
    {
        // $review = Review::where('riddle_id', $riddleId)->findOrFail($reviewId);
        // $review->delete();
        // return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
