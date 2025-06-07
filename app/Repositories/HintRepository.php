<?php

namespace App\Repositories;

use App\Models\Hint;
use App\Models\Step;
use App\Repositories\Interfaces\HintRepositoryInterface;
use Illuminate\Support\Facades\DB;

class HintRepository implements HintRepositoryInterface
{
    public function getNextOrderNumber(Step $step): int
    {
        return ($step->hints()->max('order_number') ?? 0) + 1;
    }

    public function createForStep(Step $step, array $data): Hint
    {
        return $step->hints()->create($data);
    }

    public function update(Hint $hint, array $data): Hint
    {
        $hint->update($data);
        return $hint->fresh();
    }

    public function deleteAndReorder(Hint $hint): int
    {
        $orderDeleted = $hint->order_number;
        $stepId = $hint->step_id;

        DB::transaction(function () use ($hint, $orderDeleted, $stepId) {
            $hint->delete();

            Hint::where('step_id', $stepId)
                ->where('order_number', '>', $orderDeleted)
                ->decrement('order_number');
        });

        return $stepId;
    }
}
