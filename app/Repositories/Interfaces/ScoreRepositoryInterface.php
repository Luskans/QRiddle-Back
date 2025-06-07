<?php

namespace App\Repositories\Interfaces;

use App\Models\Riddle;

interface ScoreRepositoryInterface
{
    public function getGlobalRanking(string $period, int $limit, int $offset);
    public function countGlobalScores(string $period): int;
    public function getUserGlobalScore(int $userId, string $period): ?int;
    public function getUserGlobalRank(int $userId, string $period, int $userScore): ?int;
    public function getRiddleRanking(Riddle $riddle, int $limit, int $offset);
    public function countRiddleScores(Riddle $riddle): int;
    public function getUserRiddleScore(Riddle $riddle, int $userId): ?int;
    public function getUserRiddleRank(Riddle $riddle, int $userId, int $userScore): ?int;
    public function getTopGlobalRanking(string $period, int $limit = 5);
    public function getTopRiddleRanking(Riddle $riddle, int $limit = 5);
    public function updateGlobalScore(int $userId, string $period, int $score): void;
}