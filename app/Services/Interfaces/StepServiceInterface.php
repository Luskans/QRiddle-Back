<?php

namespace App\Services\Interfaces;

use App\Models\Riddle;
use App\Models\Step;

interface StepServiceInterface
{
    /**
     * Create a new step for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Step
     */
    public function createStep(Riddle $riddle, array $data, int $userId);

    /**
     * Get the detail of a step with its hints.
     *
     * @param  \App\Models\Step  $step
     * @param  int  $userId
     * @return \App\Models\Step
     */
    public function getStepDetail(Step $step, int $userId);

    /**
     * Update a step.
     *
     * @param  \App\Models\Step  $step
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Step
     */
    public function updateStep(Step $step, array $data, int $userId);

    /**
     * Delete a step and reorder remaining steps.
     *
     * @param  \App\Models\Step  $step
     * @param  int  $userId
     * @return bool
     */
    public function deleteStep(Step $step, int $userId);
}