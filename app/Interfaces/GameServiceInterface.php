<?php

namespace App\Interfaces;


interface GameServiceInterface
{
    public function getParticipatedCount(int $userId);

    public function getActiveRiddle(int $userId);
}