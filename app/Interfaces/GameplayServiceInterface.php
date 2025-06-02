<?php

namespace App\Interfaces;

use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\User;
use Illuminate\Http\Request;

interface GameplayServiceInterface
{
    public function startGame(Riddle $riddle, User $user, Request $request);
    public function abandonGame(GameSession $session, User $user);
    public function validateStep(GameSession $session, User $user, string $qrCode);
    public function unlockHint(GameSession $session, User $user, int $hintOrder);
    public function getCurrentGame(GameSession $session, User $user);
    public function getCompletedGame(GameSession $session, User $user);
}