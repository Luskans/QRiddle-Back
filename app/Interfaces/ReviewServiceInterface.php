<?php

namespace App\Interfaces;

use App\Models\Review;
use App\Models\Riddle;

interface ReviewServiceInterface
{
    public function getPaginatedReviews(Riddle $riddle, int $page, int $limit);
    public function getTopReviews(Riddle $riddle, int $limit);
    public function createReview(Riddle $riddle, int $userId, array $data);
    public function updateReview(Review $review, array $data);
    public function deleteReview(Review $review);
}