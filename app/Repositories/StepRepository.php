<?php

namespace App\Repositories;

use App\Models\Step;
use App\Repositories\Interfaces\StepRepositoryInterface;

class StepRepository implements StepRepositoryInterface
{
    public function create(array $data): Step
    {
        return Step::create($data);
    }

    public function getNextOrderNumber(int $riddleId): int
    {
        return (Step::where('riddle_id', $riddleId)->max('order_number') ?? 0) + 1;
    }

    public function getStepWithHints(int $stepId): Step
    {
        return Step::with(['hints' => function($query) {
            $query->orderBy('order_number', 'asc')
                  ->select('id', 'step_id', 'order_number', 'type', 'content');
        }])->findOrFail($stepId);
    }

    public function update(Step $step, array $data): Step
    {
        $step->update($data);
        return $step->fresh();
    }

    public function delete(Step $step): bool
    {
        return $step->delete();
    }

    public function reorderAfterDelete(int $riddleId, int $deletedOrderNumber): void
    {
        Step::where('riddle_id', $riddleId)
            ->where('order_number', '>', $deletedOrderNumber)
            ->decrement('order_number');
    }
}
