<?php

namespace App\Services;

use App\Interfaces\GameSessionServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\SessionStep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class GameSessionService implements GameSessionServiceInterface
{
    /**
     * Get the count of played riddles.
     *
     * @param int $userId
     * @return int
     */
    public function getPlayedCount(int $userId): int
    {
        return GameSession::query()
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Get paginated game sessions for a user.
     *
     * @param int $userId
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPlayedGameSessions(int $userId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        
        $query = GameSession::query()
            ->select(['id', 'riddle_id', 'status', 'created_at'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->with('riddle:id,title,latitude,longitude');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $gameSessions = $query->skip($offset)
            ->take($limit)
            ->get();

        return [
            'items' => $gameSessions,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    /**
     * Get home's required datas of an active game session.
     *
     * @param int $userId
     * @return ?GameSession
     */
    public function getHomeActiveSession(int $userId): ?GameSession
    {
        return GameSession::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest('updated_at')
            ->with([
                'riddle' => function ($query) {
                    $query->select('id', 'title', 'latitude', 'longitude')
                        ->withCount('steps');
                },
                'latestActiveSessionStep' => function ($query) {
                    $query->with(['step' => function ($query) {
                        $query->select('id', 'order_number');
                    }]);
                }
            ])
            ->first();
    }
}