<?php

namespace App\Services;

use App\Interfaces\GameServiceInterface;
use App\Models\GameSession;
use Illuminate\Support\Facades\DB;


class GameService implements GameServiceInterface
{
    public function getParticipatedCount(int $userId)
    {
        return DB::table('game_sessions')
            ->where('player_id', $userId)
            ->count();
    }

    public function getActiveRiddle(int $userId)
    {
        // return GameSession::with('sessionSteps')
        // ->where('player_id', $userId)
        // ->where('status', 'active')
        // ->orderBy('created_at', 'desc')
        // ->first();

        return GameSession::with(['sessionSteps', 'riddle.steps'])
            ->where('player_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();
    }
}