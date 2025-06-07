<?php

namespace App\Services\Interfaces;

use App\Models\GameSession;
use App\Models\Riddle;
use App\Models\User;
use Illuminate\Http\Request;

interface GameplayServiceInterface
{
    /**
     * Starts a new game session for the given riddle and user.
     *
     * @param Riddle $riddle The riddle to start.
     * @param User $user The user who starts the game.
     * @param Request $request The HTTP request (used for private riddle password).
     * @return GameSession The newly created or existing game session.
     */
    public function startGame(Riddle $riddle, User $user, Request $request);

    /**
     * Abandons an active game session for the given user.
     *
     * @param GameSession $session The session to abandon.
     * @param User $user The user requesting the abandonment.
     * @return GameSession The updated game session.
     */
    public function abandonGame(GameSession $session, User $user);

    /**
     * Retrieves the current game step and related hints for an active session.
     *
     * @param GameSession $session The current game session.
     * @param User $user The user accessing the session.
     * @return array Data containing session step, step info, step count and hints.
     */
    public function getCurrentGame(GameSession $session, User $user);

    /**
     * Retrieves completed game session data and review status.
     *
     * @param GameSession $session The completed session.
     * @param User $user The user who completed the game.
     * @return array Data about the completed session including score, duration, steps, and review status.
     */
    public function getCompletedGame(GameSession $session, User $user);

    /**
     * Unlocks an additional hint for the current active step.
     *
     * @param GameSession $session The session where the hint should be unlocked.
     * @param User $user The user requesting the hint.
     * @param int $hintOrder The order number of the hint to unlock.
     * @return GameSession The updated session.
     */
    public function unlockHint(GameSession $session, User $user, int $hintOrder);

    /**
     * Validates the current step using a QR code and moves to the next step or ends the game.
     *
     * @param GameSession $session The current session.
     * @param User $user The user validating the step.
     * @param string $qrCode The scanned QR code.
     * @return array An array containing game completion status and the updated session.
     */
    public function validateStep(GameSession $session, User $user, string $qrCode);
}