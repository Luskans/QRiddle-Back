<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Riddle;
use App\Repositories\Interfaces\ScoreRepositoryInterface;
use App\Services\Interfaces\ScoreServiceInterface;


class ScoreService implements ScoreServiceInterface
{
    protected $scoreRepository;

    public function __construct(ScoreRepositoryInterface $scoreRepository)
    {
        $this->scoreRepository = $scoreRepository;
    }

    public function getGlobalRanking(string $period, int $page, int $limit, int $userId)
    {
        $offset = ($page - 1) * $limit;

        $totalCount = $this->scoreRepository->countGlobalScores($period);
        $totalPages = ceil($totalCount / $limit);

        $globalRanks = $this->scoreRepository->getGlobalRanking($period, $limit, $offset);
        $userScore = $this->scoreRepository->getUserGlobalScore($userId, $period);
        $userRank = $userScore ? $this->scoreRepository->getUserGlobalRank($userId, $period, $userScore) : null;

        return [
            'items' => $globalRanks,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    public function getRiddleRanking(Riddle $riddle, int $page, int $limit, int $userId)
    {
        $offset = ($page - 1) * $limit;

        $totalCount = $this->scoreRepository->countRiddleScores($riddle);
        $totalPages = ceil($totalCount / $limit);

        $riddleRanks = $this->scoreRepository->getRiddleRanking($riddle, $limit, $offset);
        $userScore = $this->scoreRepository->getUserRiddleScore($riddle, $userId);
        $userRank = $userScore ? $this->scoreRepository->getUserRiddleRank($riddle, $userId, $userScore) : null;

        return [
            'items' => $riddleRanks,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }

    public function getTopGlobalRanking(string $period, int $userId)
    {      
        $globalRanking = $this->scoreRepository->getGlobalRanking($period, 5, 0);
        $userScore = $this->scoreRepository->getUserGlobalScore($userId, $period);
        $userRank = $userScore ? $this->scoreRepository->getUserGlobalRank($userId, $period, $userScore) : null;

        return [
            'items' => $globalRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ];
    }

    public function getTopRiddleRanking(Riddle $riddle, int $userId)
    {        
        $riddleRanking = $this->scoreRepository->getRiddleRanking($riddle, 5, 0);
        $userScore = $this->scoreRepository->getUserRiddleScore($riddle, $userId);
        $userRank = $userScore ? $this->scoreRepository->getUserRiddleRank($riddle, $userId, $userScore) : null;

        return [
            'items' => $riddleRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ];
    }

    public function calculateFinalScore(GameSession $gameSession): int
    {
        $riddle = $gameSession->riddle;
        $totalDuration = $gameSession->getTotalDuration();
        $avgDifficulty = $riddle->reviews()->avg('difficulty') ?: 3;
        
        // Get average completion time of other usersfor this riddle
        $avgCompletionTime = GameSession::where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->where('id', '!=', $gameSession->id)
            ->get()
            ->avg(function($session) {
                return $session->getTotalDuration();
            }) ?: $totalDuration;
        
        $totalScore = 0;
        $sessionSteps = $gameSession->sessionSteps()->with('step')->get();

        foreach ($sessionSteps as $sessionStep) {
            $stepBaseScore = 20;
            $hintPenalty = 0;

            for ($i = 1; $i <= $sessionStep->extra_hints; $i++) {
                if ($i === 1) {
                    $hintPenalty += 3;
                } else if ($i === 2) {
                    $hintPenalty += 2;
                } else {
                    $hintPenalty += 1;
                }
            }
            
            // Get step score (minimum of 12 points)
            $stepScore = max(12, $stepBaseScore - $hintPenalty);
            $totalScore += $stepScore;
        }
        
        // Difficulty multiplier 1.0, 1.1, 1.2, 1.3, 1.4
        $difficultyMultiplier = 1 + (($avgDifficulty - 1) * 0.1);
        $scoreWithDifficulty = $totalScore * $difficultyMultiplier;
        
        // Time multiplier -50% to +50% according to average completion time of other users
        $timeMultiplier = 1.0;
        if ($avgCompletionTime > 0 && $totalDuration > 0) {
            $timeRatio = $avgCompletionTime / $totalDuration;
            $timeMultiplier = min(1.5, max(0.5, $timeRatio));
        }
        
        $finalScore = $scoreWithDifficulty * $timeMultiplier;
        
        return (int) round($finalScore);
    }

    public function updateGlobalScores(int $userId, int $score): void
    {
        foreach (['week', 'month', 'all'] as $period) {
            $this->scoreRepository->updateGlobalScore($userId, $period, $score);
        }
    }
}