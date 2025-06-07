<?php

namespace App\Repositories\Interfaces;

use App\Models\Hint;
use App\Models\Step;

interface HintRepositoryInterface
{
    public function getNextOrderNumber(Step $step): int;
    public function createForStep(Step $step, array $data): Hint;
    public function update(Hint $hint, array $data): Hint;
    public function deleteAndReorder(Hint $hint): int;
}