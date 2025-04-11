<?php

namespace App\Interfaces;

use App\Models\Riddle;

interface ScoreServiceInterface
{
    public function getRankingByPeriod(string $period, ?int $limit, ?int $offset = null);

    public function getAggregateRanking(?int $limit = null, ?int $offset = null): array;
    
    
    // NEW
    public function getGlobalRankingByPeriod(string $period, int $limit, int $offset): array;
    
    public function getGlobalUserRankByPeriod(string $period, int $userId): array | null;
    
    public function getAggregateGlobalUserRank(int $userId): array;

    public function getRankingByRiddle(Riddle $riddle, int $limit, int $offset): array;

    public function getUserRankByRiddle(Riddle $riddle, int $userId): array | null;

}