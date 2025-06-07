<?php

namespace App\Services\Interfaces;

use App\Models\GameSession;

interface GameSessionServiceInterface
{
    /**
     * Get the count of played riddles.
     *
     * @param int $userId
     * @return int
     */
    public function getPlayedCount(int $userId): int;

    /**
     * Get paginated game sessions for a user.
     *
     * @param int $userId
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getHomeActiveSession(int $userId): ?GameSession;

    /**
     * Get home's required datas of an active game session.
     *
     * @param int $userId
     * @return ?GameSession
     */
    public function getPlayedGameSessions(int $userId, int $page, int $limit);
}