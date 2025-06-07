<?php

namespace App\Services;

use App\Models\Riddle;
use App\Models\Step;
use App\Repositories\Interfaces\StepRepositoryInterface;
use App\Services\Interfaces\StepServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StepService implements StepServiceInterface
{
    protected $stepRepository;

    public function __construct(StepRepositoryInterface $stepRepository)
    {
        $this->stepRepository = $stepRepository;
    }
      
    public function createStep(Riddle $riddle, array $data, $userId)
    {
        if ($userId !== $riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        $nextOrderNumber = $this->stepRepository->getNextOrderNumber($riddle->id);
        $qrCodeValue = (string) Str::uuid();

        return $this->stepRepository->create([
            'riddle_id' => $riddle->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'order_number' => $nextOrderNumber,
            'qr_code' => $qrCodeValue,
        ]);
    }

    public function getStepDetail(Step $step, int $userId)
    {
        if (!$step->riddle) {
            throw new \Exception('Étape non associée à une énigme.', Response::HTTP_NOT_FOUND);
        }

        if ($userId !== $step->riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->stepRepository->getStepWithHints($step->id);
    }

    public function updateStep(Step $step, array $data, int $userId)
    {
        if (!$step->riddle) {
            throw new \Exception('Étape non associée à une énigme.', Response::HTTP_NOT_FOUND);
        }

        if ($userId !== $step->riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->stepRepository->update($step, $data);
    }

    public function deleteStep(Step $step, $userId)
    {
        if (!$step->riddle) {
            throw new \Exception('Étape non associée à une énigme.', Response::HTTP_NOT_FOUND);
        }

        if ($userId !== $step->riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return DB::transaction(function () use ($step) {
            $this->stepRepository->delete($step);

            $this->stepRepository->reorderAfterDelete($step->riddle_id, $step->order_number);

            return true;
        });
    }
}