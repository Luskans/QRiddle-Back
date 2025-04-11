<?php

namespace App\Services;

use App\Interfaces\ScoreServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use Illuminate\Support\Facades\DB;


class ScoreService implements ScoreServiceInterface
{
    public function getRankingByPeriod(string $period, ?int $limit, ?int $offset = null)
    {
        $query = DB::table('global_scores')
            ->join('users', 'global_scores.user_id', '=', 'users.id')
            ->select('users.name', 'users.image', 'global_scores.score')
            ->where('global_scores.period', '=', $period)
            ->orderByDesc('global_scores.score');

        if (!is_null($limit)) {
            $query->limit($limit);
        }

        if (!is_null($offset)) {
            $query->offset($offset);
        }

        return $query->get();
    }

    public function getUserRankByPeriod(string $period, int $userId): array | null
    {
        $userScore = DB::table('global_scores')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        if (!$userScore) {
            return null;
        }

        $userRank = DB::table('global_scores')
            ->where('period', $period)
            ->where('score', '>', $userScore)
            ->count() + 1;

        return [
            'score' => $userScore,
            'rank' => $userRank,
        ];
    }

    public function getAggregateRanking(?int $limit = null, ?int $offset = null): array
    {
        return [
            'week'  => $this->getRankingByPeriod('week', $limit, $offset),
            'month' => $this->getRankingByPeriod('month', $limit, $offset),
            'all'   => $this->getRankingByPeriod('all', $limit, $offset),
        ];
    }

    







    // NEW
    public function getGlobalRankingByPeriod(string $period, int $limit, int $offset): array
    {
        $query = DB::table('global_scores')
            ->join('users', 'global_scores.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.name', 'users.image', 'global_scores.score')
            ->where('global_scores.period', '=', $period)
            ->orderByDesc('global_scores.score')
            ->skip($offset)
            ->take($limit)
            ->get();

            return [
                'ranking' => $query
            ];
    }

    public function getGlobalUserRankByPeriod(string $period, int $userId): array | null
    {
        $userScore = DB::table('global_scores')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        if (!$userScore) {
            return null;
        }

        $userRank = DB::table('global_scores')
            ->where('period', $period)
            ->where('score', '>', $userScore)
            ->count() + 1;

        return [
            'score' => $userScore,
            'rank' => $userRank,
        ];
    }

    public function getAggregateGlobalUserRank(int $userId): array
    {
        return [
            'week'  => $this->getGlobalUserRankByPeriod('week', $userId),
            'month' => $this->getGlobalUserRankByPeriod('month', $userId),
            'all'   => $this->getGlobalUserRankByPeriod('all', $userId),
        ];
    }

    public function getRankingByRiddle(Riddle $riddle, int $limit, int $offset): array
    {
        $query = DB::table('game_sessions')
            ->join('users', 'game_sessions.player_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.name', 'users.image', 'game_sessions.score')
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->orderByDesc('score')
            ->skip($offset)
            ->take($limit)
            ->get();

            return [
                'ranking' => $query
            ];
    }

    public function getUserRankByRiddle(Riddle $riddle, int $userId): array | null
    {
        $userScore = DB::table('game_sessions')
            ->where('player_id', $userId)
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->value('score');
        
        if (!$userScore) {
            return null;
        }

        $userRank = DB::table('game_sessions')
            ->where('riddle_id', $riddle->id)
            ->where('score', '>', $userScore)
            ->count() + 1;

        return [
            'score' => $userScore,
            'rank' => $userRank,
        ];
    }
}