<?php

namespace App\Repositories;

use App\Models\Review;
use App\Models\Riddle;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Support\Collection;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function getPaginatedByRiddle(Riddle $riddle, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $query = Review::where('riddle_id', $riddle->id)
            ->select(['id', 'user_id', 'content', 'rating', 'difficulty', 'updated_at'])
            ->with('user:id,name,image')
            ->orderBy('updated_at', 'desc');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $reviews = $query->skip($offset)->take($limit)->get();

        return [
            'items' => $reviews,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    public function getTopByRiddle(Riddle $riddle, int $limit): Collection
    {
        return Review::where('riddle_id', $riddle->id)
            ->select(['id', 'user_id', 'content', 'rating', 'difficulty', 'updated_at'])
            ->with('user:id,name,image')
            ->orderBy('updated_at', 'desc')
            ->take($limit)
            ->get();
    }

    public function create(Riddle $riddle, int $userId, array $data): Review
    {
        return $riddle->reviews()->create(array_merge($data, ['user_id' => $userId]));
    }

    public function update(Review $review, array $data): Review
    {
        $review->update($data);
        return $review->fresh();
    }

    public function delete(Review $review): bool
    {
        return $review->delete();
    }

    public function userHasReviewedRiddle(int $riddleId, int $userId): bool
    {
        return Review::where('user_id', $userId)
            ->where('riddle_id', $riddleId)
            ->exists();
    }

    public function userHasCompletedRiddle(Riddle $riddle, int $userId): bool
    {
        return $riddle->gameSessions()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->exists();
    }
}
