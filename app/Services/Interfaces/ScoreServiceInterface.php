<?php

namespace App\Services\Interfaces;

use App\Models\GameSession;
use App\Models\Riddle;

interface ScoreServiceInterface
{
    /**
     * Get the paginated list of global ranking by period.
     *
     * @param string $period
     * @param int $page
     * @param int $limit
     * @param int $userId
     * @return array
     */
    public function getGlobalRanking(string $period, int $page, int $limit, int $userId);

    /**
     * Get the paginated list of riddle ranking.
     *
     * @param Riddle $riddle
     * @param int $page
     * @param int $limit
     * @param int $userId
     * @return array
     */
    public function getRiddleRanking(Riddle $riddle, int $page, int $limit, int $userId);

    /**
     * Get the top 5 of global ranking by period.
     *
     * @param string $period
     * @param int $userId
     * @return array
     */
    public function getTopGlobalRanking(string $period, int $userId);

    /**
     * Get the top 5 list of riddle ranking.
     *
     * @param Riddle $riddle
     * @param int $userId
     * @return array
     */
    public function getTopRiddleRanking(Riddle $riddle, int $userId);

    /**
     * Calculate the score after completing a riddle.
     *
     * @param GameSession $gameSession
     * @return int
     */
    public function calculateFinalScore(GameSession $gameSession);

    /**
     * Update global scores after completing a riddle.
     *
     * @param int $userId
     * @param int $score
     * @return void
     */
    public function updateGlobalScores(int $userId, int $score);
}