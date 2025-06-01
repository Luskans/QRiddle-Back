<?php

namespace App\Services;

use App\Interfaces\ScoreServiceInterface;
use App\Models\GameSession;
use App\Models\GlobalScore;
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
            ->join('users', 'game_sessions.user_id', '=', 'users.id')
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
            ->where('user_id', $userId)
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












    // NEW
    /**
     * Get the paginated list of global ranking by period.
     *
     * @param string $period
     * @param int $page
     * @param int $limit
     * @param int $userId
     * @return array
     */
    public function getGlobalRanking(string $period, int $page, int $limit, int $userId)
    {
        $offset = ($page - 1) * $limit;

        $query = GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $globalRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GlobalScore::query()
                ->where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

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

    /**
     * Get the paginated list of riddle ranking.
     *
     * @param Riddle $riddle
     * @param int $page
     * @param int $limit
     * @param int $userId
     * @return array
     */
    public function getRiddleRanking(Riddle $riddle, int $page, int $limit, int $userId)
    {
        $offset = ($page - 1) * $limit;

        $query = GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->with('user:id,name,image');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $riddleRanks = $query->skip($offset)
            ->take($limit)
            ->get();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GameSession::query()
                ->where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

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

    /**
     * Get the top 5 of global ranking by period.
     *
     * @param string $period
     * @param int $userId
     * @return array
     */
    public function getTopGlobalRanking(string $period, int $userId)
    {      
        $globalRanking = GlobalScore::query()
            ->select(['id', 'user_id', 'score'])
            ->where('period', $period)
            ->orderBy('score', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        $userScore = GlobalScore::query()
            ->select(['id', 'score'])
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GlobalScore::query()
                ->where('period', $period)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return [
            'items' => $globalRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ];
    }

    /**
     * Get the top 5 list of riddle ranking.
     *
     * @param Riddle $riddle
     * @param int $userId
     * @return array
     */
    public function getTopRiddleRanking(Riddle $riddle, int $userId)
    {        
        $riddleRanking = GameSession::query()
            ->select(['id', 'user_id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->with('user:id,name,image')
            ->take(5)
            ->get();

        $userScore = GameSession::query()
            ->select(['id', 'score'])
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->value('score');

        $userRank = null;
        if ($userScore) {
            $userRank = GameSession::query()
                ->where('riddle_id', $riddle->id)
                ->where('score', '>', $userScore)
                ->count() + 1;
        }

        return [
            'items' => $riddleRanking,
            'data' => [
                'userScore' => $userScore,
                'userRank' => $userRank,
            ],
        ];
    }

    /**
     * Calculate the score after completing a riddle.
     *
     * @param GameSession $gameSession
     * @return int
     */
    public function calculateFinalScore(GameSession $gameSession)
    {        
        $test = $gameSession;
        return 50;
    }
}