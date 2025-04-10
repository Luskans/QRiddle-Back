<?php

namespace App\Interfaces;


interface ScoreServiceInterface
{
    public function getRankingByPeriod(string $period, ?int $limit, ?int $offset = null);

    public function getUserRankByPeriod(string $period, int $userId): array | null;

    public function getAggregateRanking(?int $limit = null, ?int $offset = null): array;

    public function getAggregateUserRank(int $userId): array;

    // public function getPlayerRankByPeriodAndName(string $period, string $playerName);

    // public function getPlayersAutocomplete(string $searchName, int $limit = 5);
}