<?php

namespace App\Services;

use App\Models\GameSession;
use App\Repositories\Interfaces\GameSessionRepositoryInterface;
use App\Services\Interfaces\GameSessionServiceInterface;

class GameSessionService implements GameSessionServiceInterface
{
    protected $gameSessionRepository;

    public function __construct(GameSessionRepositoryInterface $gameSessionRepository)
    {
        $this->gameSessionRepository = $gameSessionRepository;
    }
    
    public function getPlayedCount(int $userId): int
    {
        return $this->gameSessionRepository->countByUserId($userId);
    }

    public function getPlayedGameSessions(int $userId, int $page, int $limit): array
    {
        return $this->gameSessionRepository->getPaginatedByUserId($userId, $page, $limit);
    }

    public function getHomeActiveSession(int $userId): ?GameSession
    {
        return $this->gameSessionRepository->getHomeActiveSessionByUserId($userId);
    }
}