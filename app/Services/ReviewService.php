<?php

namespace App\Services;

use App\Interfaces\ReviewServiceInterface;
use App\Models\Review;
use App\Models\Riddle;
use Symfony\Component\HttpFoundation\Response;

class ReviewService implements ReviewServiceInterface
{
    /**
     * Get paginated reviews for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $page
     * @param  int  $limit
     * @return array
     */
    public function getPaginatedReviews(Riddle $riddle, int $page, int $limit)
    {
        $offset = ($page - 1) * $limit;
        
        $query = Review::query()
            ->select(['id', 'user_id', 'content', 'rating', 'difficulty', 'updated_at'])
            ->where('riddle_id', $riddle->id)
            ->orderBy('updated_at', 'desc')
            ->with('user:id,name,image');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $reviews = $query->skip($offset)
            ->take($limit)
            ->get();

        return [
            'items' => $reviews,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    /**
     * Get the top reviews for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopReviews(Riddle $riddle, int $limit)
    {
        return Review::query()
            ->select(['id', 'user_id', 'content', 'rating', 'difficulty', 'updated_at'])
            ->where('riddle_id', $riddle->id)
            ->orderBy('updated_at', 'desc')
            ->with('user:id,name,image')
            ->take($limit)
            ->get();
    }

    /**
     * Create a new review.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $userId
     * @param  array  $data
     * @return \App\Models\Review
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createReview(Riddle $riddle, int $userId, array $data)
    {
        $existingReview = $riddle->reviews()
            ->where('user_id', $userId)
            ->first();

        if ($existingReview) {
            throw new \Exception('Vous avez déjà laissé un avis pour cette énigme.', Response::HTTP_FORBIDDEN);
        }

        $gameCompleted = $riddle->gameSessions()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->exists();

        if (!$gameCompleted) {
            throw new \Exception('Vous devez avoir terminé l\'énigme pour laisser un avis.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $riddle->reviews()->create([
            'user_id' => $userId,
            'content' => $data['content'],
            'rating' => $data['rating'],
            'difficulty' => $data['difficulty'],
        ]);
    }

    /**
     * Update a review.
     *
     * @param  \App\Models\Review  $review
     * @param  array  $data
     * @return \App\Models\Review
     */
    public function updateReview(Review $review, array $data)
    {
        $review->update($data);
        return $review->fresh();
    }

    /**
     * Delete a review.
     *
     * @param  \App\Models\Review  $review
     * @return bool
     */
    public function deleteReview(Review $review)
    {
        return $review->delete();
    }
}