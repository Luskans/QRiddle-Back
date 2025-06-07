<?php

namespace App\Repositories;

use App\Models\Riddle;
use App\Models\GameSession;
use App\Repositories\Interfaces\RiddleRepositoryInterface;

class RiddleRepository implements RiddleRepositoryInterface
{
    public function getPublishedRiddles()
    {
        return Riddle::query()
            ->select(['id', 'title', 'status', 'is_private', 'updated_at', 'latitude', 'longitude'])
            ->where('status', 'published')
            ->withCount('steps')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withAvg('reviews', 'difficulty')
            ->get();
    }

    public function create(array $data): Riddle
    {
        return Riddle::create($data);
    }

    public function update(Riddle $riddle, array $data): Riddle
    {
        $riddle->update($data);
        return $riddle->fresh();
    }

    public function delete(Riddle $riddle): bool
    {
        return $riddle->delete();
    }

    public function getByIdWithDetails(Riddle $riddle): Riddle
    {
        $riddle->load(['creator:id,name,image', 'steps:id,riddle_id,order_number,qr_code']);
        $riddle->loadCount('steps');
        $riddle->loadCount('reviews');
        $riddle->loadAvg('reviews', 'rating');
        $riddle->loadAvg('reviews', 'difficulty');
        return $riddle;
    }

    public function getUserGameSession(Riddle $riddle, int $userId)
    {
        return GameSession::select('id', 'status')
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->with('sessionSteps:id,game_session_id,status,start_time,end_time')
            ->first();
    }

    public function getCreatedCount(int $userId): int
    {
        return Riddle::where('creator_id', $userId)->count();
    }

    public function getUserCreatedRiddles(int $userId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $query = Riddle::query()
            ->select(['id', 'title', 'status', 'is_private', 'updated_at', 'latitude', 'longitude'])
            ->where('creator_id', $userId)
            ->orderBy('updated_at', 'desc');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $riddles = $query->skip($offset)->take($limit)->get();

        return [
            'items' => $riddles,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }
}
