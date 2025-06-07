<?php

namespace App\Services;

use App\Services\Interfaces\RiddleServiceInterface;
use App\Models\Riddle;
use App\Repositories\Interfaces\RiddleRepositoryInterface;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;


class RiddleService implements RiddleServiceInterface
{
    protected $riddleRepository;

    public function __construct(RiddleRepositoryInterface $riddleRepository)
    {
        $this->riddleRepository = $riddleRepository;
    }
    
    public function getPublishedRiddles()
    {
        return $this->riddleRepository->getPublishedRiddles();
    }

    public function createRiddle(array $data, int $userId)
    {
        if ($data['is_private']) {
            $data['password'] = Str::random(6);
        } else {
            $data['password'] = null;
        }

        $data['creator_id'] = $userId;

        return $this->riddleRepository->create($data);
    }

    public function getRiddleDetail(Riddle $riddle)
    {
        return $this->riddleRepository->getByIdWithDetails($riddle);
    }

    public function updateRiddle(Riddle $riddle, array $data, int $userId)
    {
        if ($userId !== $riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        if (isset($data['is_private'])) {
            $data['password'] = $data['is_private'] ? Str::random(6) : null;
        }

        if (isset($data['status']) && ($data['status'] === 'published' || $data['status'] === 'draft')) {
            if ($riddle->steps()->count() === 0) {
                throw new \Exception('Impossible de publier une énigme sans au moins une étape.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $stepsWithoutHints = $riddle->steps()
                ->whereDoesntHave('hints')
                ->count();

            if ($stepsWithoutHints > 0) {
                throw new \Exception('Toutes les étapes doivent avoir au moins un indice.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->riddleRepository->update($riddle, $data);
    }

    public function deleteRiddle(Riddle $riddle, int $userId)
    {
        if ($userId !== $riddle->creator_id) {
            throw new \Exception('Utilisateur non autorisé.', Response::HTTP_FORBIDDEN);
        }

        return $this->riddleRepository->delete($riddle);
    }

    public function getGameSessionForRiddle(Riddle $riddle, int $userId)
    {
        return $this->riddleRepository->getUserGameSession($riddle, $userId);
    }

    public function getCreatedCount(int $userId)
    {
        return $this->riddleRepository->getCreatedCount($userId);
    }

    public function getCreatedRiddles(int $userId, int $page, int $limit): array
    {
        return $this->riddleRepository->getUserCreatedRiddles($userId, $page, $limit);
    }
}