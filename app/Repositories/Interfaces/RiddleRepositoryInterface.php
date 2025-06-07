<?php

namespace App\Repositories\Interfaces;

use App\Models\Riddle;

interface RiddleRepositoryInterface
{
    public function getPublishedRiddles();
    public function create(array $data): Riddle;
    public function update(Riddle $riddle, array $data): Riddle;
    public function delete(Riddle $riddle): bool;
    public function getByIdWithDetails(Riddle $riddle): Riddle;
    public function getUserGameSession(Riddle $riddle, int $userId);
    public function getUserCreatedRiddles(int $userId, int $page, int $limit): array;
    public function getCreatedCount(int $userId): int;
}