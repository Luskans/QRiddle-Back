<?php

namespace App\Services\Interfaces;

use App\Models\Review;
use App\Models\Riddle;

interface ReviewServiceInterface
{
    /**
     * Get paginated reviews for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $page
     * @param  int  $limit
     * @return array
     */
    public function getPaginatedReviews(Riddle $riddle, int $page, int $limit);

    /**
     * Get the top reviews for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTopReviews(Riddle $riddle, int $limit);

    /**
     * Create a new review.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $userId
     * @param  array  $data
     * @return \App\Models\Review
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createReview(Riddle $riddle, int $userId, array $data);

    /**
     * Update a review.
     *
     * @param  \App\Models\Review  $review
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Review
     */
    public function updateReview(Review $review, array $data, int $userId);

    /**
     * Delete a review.
     *
     * @param  \App\Models\Review  $review
     * @param  int  $userId
     * @return bool
     */
    public function deleteReview(Review $review, int $userId);
}