<?php

namespace App\Repositories\Interfaces;

use App\Models\Step;

interface StepRepositoryInterface
{
    public function create(array $data): Step;
    public function getNextOrderNumber(int $riddleId): int;
    public function getStepWithHints(int $stepId): Step;
    public function update(Step $step, array $data): Step;
    public function delete(Step $step): bool;
    public function reorderAfterDelete(int $riddleId, int $deletedOrderNumber): void;
}