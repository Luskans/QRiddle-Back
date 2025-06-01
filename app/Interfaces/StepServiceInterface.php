<?php

namespace App\Interfaces;

use App\Models\Riddle;
use App\Models\Step;

interface StepServiceInterface
{
    public function createStep(Riddle $riddle, array $data);
    public function getStepDetail(Step $step);
    public function updateStep(Step $step, array $data);
    public function deleteStep(Step $step);
}