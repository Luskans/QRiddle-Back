<?php

namespace App\Interfaces;

use App\Models\GameSession;

interface GameSessionServiceInterface
{
    public function getPlayedCount(int $userId): int;
    public function getHomeActiveSession(int $userId): ?GameSession;
    public function getPlayedGameSessions(int $userId, int $page, int $limit);
}