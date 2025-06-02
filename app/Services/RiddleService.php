<?php

namespace App\Services;

use App\Interfaces\RiddleServiceInterface;
use App\Models\GameSession;
use App\Models\Riddle;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RiddleService implements RiddleServiceInterface
{
    /**
     * Get the list of published riddles.
     *
     * @return \App\Models\Riddle
     */
    public function getPublishedRiddles()
    {
        return Riddle::query()
            ->select(['id', 'title', 'status', 'is_private', 'updated_at', 'latitude', 'longitude'])
            ->where('status', 'published')
            ->withCount('steps')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withAvg('reviews', 'difficulty')
            ->get();
    }

    /**
     * Create a new riddle.
     *
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Riddle
     */
    public function createRiddle(array $data, int $userId)
    {
        if ($data['is_private']) {
            $data['password'] = Str::random(6);
        } else {
            $data['password'] = null;
        }

        return Riddle::create(array_merge($data, ['creator_id' => $userId]));
    }

    /**
     * Get the detail of a riddle with its steps.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \App\Models\Riddle
     */
    public function getRiddleDetail(Riddle $riddle)
    {
        $riddle->load(['creator:id,name,image', 'steps:id,riddle_id,order_number,qr_code']);
        $riddle->loadCount('steps');
        $riddle->loadCount('reviews');
        $riddle->loadAvg('reviews', 'rating');
        $riddle->loadAvg('reviews', 'difficulty');

        return $riddle;
    }

    /**
     * Update a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  array  $data
     * @return \App\Models\Riddle
     */
    public function updateRiddle(Riddle $riddle, array $data)
    {
        if (isset($data['is_private'])) {
            if ($data['is_private'] === true) {
                $data['password'] = Str::random(6);
            } else {
                $data['password'] = null;
            }
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

        $riddle->update($data);
        return $riddle->fresh();
    }

    /**
     * Delete a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return bool
     */
    public function deleteRiddle(Riddle $riddle)
    {
        return $riddle->delete();
    }

    /**
     * Get the user's game session for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $userId
     * @return \App\Models\GameSession
     */
    public function getGameSessionForRiddle(Riddle $riddle, int $userId)
    {
        return GameSession::select('id', 'status')
            ->where('riddle_id', $riddle->id)
            ->where('user_id', $userId)
            ->with('sessionSteps:id,game_session_id,status,start_time,end_time')
            ->first();
    }

    /**
     * Get the user's created riddles count.
     *
     * @param  int  $userId
     * @return int
     */
    public function getCreatedCount(int $userId)
    {
        return Riddle::where('creator_id', $userId)->count();
    }

    /**
     * Get paginated riddles created by a user.
     *
     * @param int $userId
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getCreatedRiddles(int $userId, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        
        $query = Riddle::query()
            ->select(['id', 'title', 'status', 'is_private', 'updated_at', 'latitude', 'longitude'])
            ->where('creator_id', $userId)
            ->orderBy('updated_at', 'desc');

        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $limit);

        $riddles = $query->skip($offset)
            ->take($limit)
            ->get();

        return [
            'items' => $riddles,
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $totalCount,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }
}