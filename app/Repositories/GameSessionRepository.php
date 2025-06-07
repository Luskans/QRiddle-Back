<?php

namespace App\Repositories;

use App\Models\GameSession;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;

class GameSessionRepository implements GameSessionRepositoryInterface
{
    public function countByUserId(int $userId): int
    {
        return GameSession::where('user_id', $userId)->count();
    }

    public function getPaginatedByUserId(int $userId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $query = GameSession::query()
            ->select(['id', 'riddle_id', 'status', 'created_at'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->with('riddle:id,title,latitude,longitude');

        $totalCount = $query->count();
        $totalPages = (int) ceil($totalCount / $limit);

        $items = $query->skip($offset)->take($limit)->get();

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    public function getHomeActiveSessionByUserId(int $userId): ?GameSession
    {
        return GameSession::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest('updated_at')
            ->with([
                'riddle' => fn ($query) => $query
                    ->select('id', 'title', 'latitude', 'longitude')
                    ->withCount('steps'),
                'latestActiveSessionStep' => fn ($query) => $query
                    ->with('step:id,order_number'),
            ])
            ->first();
    }

    public function getActiveSessionForRiddleAndUser(int $riddleId, int $userId): ?GameSession
    {
        return GameSession::where('riddle_id', $riddleId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    public function abandonAllActiveSessionsForUser(int $userId): void
    {
        GameSession::where('user_id', $userId)
            ->where('status', 'active')
            ->each(function ($session) {
                $session->update(['status' => 'abandoned']);
                $session->sessionSteps()
                    ->where('status', 'active')
                    ->update(['status' => 'abandoned', 'end_time' => now()]);
            });
    }

    public function createSession(array $data): GameSession
    {
        return GameSession::create($data);
    }

    public function updateSessionStatus(GameSession $session, string $status): void
    {
        $session->update(['status' => $status]);
    }
}
