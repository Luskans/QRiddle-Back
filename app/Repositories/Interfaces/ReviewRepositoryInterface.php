<?php

namespace App\Repositories\Interfaces;

use App\Models\Review;
use App\Models\Riddle;
use Illuminate\Support\Collection;

interface ReviewRepositoryInterface
{
    public function getPaginatedByRiddle(Riddle $riddle, int $page, int $limit): array;
    public function getTopByRiddle(Riddle $riddle, int $limit): Collection;
    public function create(Riddle $riddle, int $userId, array $data): Review;
    public function update(Review $review, array $data): Review;
    public function delete(Review $review): bool;
    public function userHasReviewedRiddle(int $riddleId, int $userId): bool;
    public function userHasCompletedRiddle(Riddle $riddle, int $userId): bool;
}