<?php

namespace App\Repositories\Interfaces;

use App\Models\GameSession;

interface GameSessionRepositoryInterface
{
    public function countByUserId(int $userId): int;
    public function getPaginatedByUserId(int $userId, int $page, int $limit): array;
    public function getHomeActiveSessionByUserId(int $userId): ?GameSession;
    public function getActiveSessionForRiddleAndUser(int $riddleId, int $userId): ?GameSession;
    public function abandonAllActiveSessionsForUser(int $userId): void;
    public function createSession(array $data): GameSession;
    public function updateSessionStatus(GameSession $session, string $status): void;
}