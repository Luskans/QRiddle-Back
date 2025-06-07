<?php

namespace App\Repositories\Interfaces;

use App\Models\GameSession;
use App\Models\SessionStep;

interface SessionStepRepositoryInterface
{
    public function deleteStepsForSession(GameSession $session): void;
    public function create(array $data): SessionStep;
    public function abandonStep(SessionStep $step): void;
    public function incrementExtraHints(SessionStep $step): void;
}
