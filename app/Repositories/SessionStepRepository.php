<?php

namespace App\Repositories;

use App\Models\GameSession;
use App\Models\SessionStep;
use App\Repositories\Interfaces\SessionStepRepositoryInterface;

class SessionStepRepository implements SessionStepRepositoryInterface
{
    public function deleteStepsForSession(GameSession $session): void
    {
        $session->sessionSteps()->delete();
    }

    public function create(array $data): SessionStep
    {
        return SessionStep::create($data);
    }

    public function abandonStep(SessionStep $step): void
    {
        $step->update(['status' => 'abandoned', 'end_time' => now()]);
    }

    public function incrementExtraHints(SessionStep $step): void
    {
        $step->increment('extra_hints');
    }
}
