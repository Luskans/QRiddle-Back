<?php

namespace App\Interfaces;

use App\Models\Riddle;

interface RiddleServiceInterface
{
    public function getPublishedRiddles();
    public function createRiddle(array $data, int $userId);
    public function getRiddleDetail(Riddle $riddle);
    public function updateRiddle(Riddle $riddle, array $data);
    public function deleteRiddle(Riddle $riddle);
    public function getGameSessionForRiddle(Riddle $riddle, int $userId);
    public function getCreatedCount(int $userId);
    public function getCreatedRiddles(int $userId, int $page, int $limit);
}