<?php

namespace App\Services;

use App\Interfaces\StepServiceInterface;
use App\Models\Riddle;
use App\Models\Step;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StepService implements StepServiceInterface
{
    /**
     * Create a new step for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  array  $data
     * @return \App\Models\Step
     */
    public function createStep(Riddle $riddle, array $data)
    {
        $nextOrderNumber = ($riddle->steps()->max('order_number') ?? 0) + 1;
        $qrCodeValue = (string) Str::uuid();

        return $riddle->steps()->create([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'order_number' => $nextOrderNumber,
            'qr_code' => $qrCodeValue,
        ]);
    }

    /**
     * Get the detail of a step with its hints.
     *
     * @param  \App\Models\Step  $step
     * @return \App\Models\Step
     */
    public function getStepDetail(Step $step)
    {
        return $step->load(['hints' => function($query) {
            $query->orderBy('order_number', 'asc')
                ->select('id', 'step_id', 'order_number', 'type', 'content');
        }]);
    }

    /**
     * Update a step.
     *
     * @param  \App\Models\Step  $step
     * @param  array  $data
     * @return \App\Models\Step
     */
    public function updateStep(Step $step, array $data)
    {
        $step->update($data);
        return $step->fresh();
    }

    /**
     * Delete a step and reorder remaining steps.
     *
     * @param  \App\Models\Step  $step
     * @return bool
     */
    public function deleteStep(Step $step)
    {
        $orderDeleted = $step->order_number;
        $riddleId = $step->riddle_id;

        return DB::transaction(function() use ($step, $orderDeleted, $riddleId) {
            $step->delete();

            Step::where('riddle_id', $riddleId)
                ->where('order_number', '>', $orderDeleted)
                ->decrement('order_number');

            return true;
        });
    }
}