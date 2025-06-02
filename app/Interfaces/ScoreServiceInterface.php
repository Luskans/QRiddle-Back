<?php

namespace App\Interfaces;

use App\Models\GameSession;
use App\Models\Riddle;

interface ScoreServiceInterface
{
    public function getGlobalRanking(string $period, int $page, int $limit, int $userId);
    public function getRiddleRanking(Riddle $riddle, int $page, int $limit, int $userId);
    public function getTopGlobalRanking(string $period, int $userId);
    public function getTopRiddleRanking(Riddle $riddle, int $userId);
    public function calculateFinalScore(GameSession $gameSession);
    public function updateGlobalScores(int $userId, int $score);
}