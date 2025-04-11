<?php

namespace App\Interfaces;

use App\Models\GameSession;

interface GameServiceInterface
{
    public function getPlayedCount(int $userId): int;
    public function getActiveSession(int $userId): ?GameSession;
    public function startSession(int $userId, int $riddleId): array;
    public function abandonSession(GameSession $gameSession): GameSession;
}