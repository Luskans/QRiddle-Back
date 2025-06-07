<?php

namespace App\Repositories;

use App\Models\GameSession;
use App\Models\GlobalScore;
use App\Models\Riddle;
use App\Repositories\Interfaces\ScoreRepositoryInterface;

class ScoreRepository implements ScoreRepositoryInterface
{
    public function getGlobalRanking(string $period, int $limit, int $offset)
    {
        return GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->with('user:id,name,image')
            ->orderByDesc('score')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    public function countGlobalScores(string $period): int
    {
        return GlobalScore::where('period', $period)->count();
    }

    public function getUserGlobalScore(int $userId, string $period): ?int
    {
        return GlobalScore::where('user_id', $userId)
            ->where('period', $period)
            ->value('score');
    }

    public function getUserGlobalRank(int $userId, string $period, int $userScore): ?int
    {
        if ($userScore === null) {
            return null;
        }

        return GlobalScore::where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
    }

    public function getRiddleRanking(Riddle $riddle, int $limit, int $offset)
    {
        return GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->with('user:id,name,image')
            ->orderByDesc('score')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    public function countRiddleScores(Riddle $riddle): int
    {
        return GameSession::where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->count();
    }

    public function getUserRiddleScore(Riddle $riddle, int $userId): ?int
    {
        return GameSession::where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');
    }

    public function getUserRiddleRank(Riddle $riddle, int $userId, int $userScore): ?int
    {
        if ($userScore === null) {
            return null;
        }

        return GameSession::where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->where('status', 'completed')
                ->count() + 1;
    }

    public function getTopGlobalRanking(string $period, int $limit = 5)
    {
        return GlobalScore::where('period', $period)
            ->with('user:id,name,image')
            ->orderByDesc('score')
            ->take($limit)
            ->get();
    }

    public function getTopRiddleRanking(Riddle $riddle, int $limit = 5)
    {
        return GameSession::where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->with('user:id,name,image')
            ->orderByDesc('score')
            ->take($limit)
            ->get();
    }

    public function updateGlobalScore(int $userId, string $period, int $score): void
    {
        $globalScore = GlobalScore::firstOrNew([
            'user_id' => $userId,
            'period' => $period
        ]);

        $globalScore->score = ($globalScore->score ?? 0) + $score;
        $globalScore->save();
    }
}
