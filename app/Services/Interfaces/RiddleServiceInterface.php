<?php

namespace App\Services\Interfaces;

use App\Models\Riddle;

interface RiddleServiceInterface
{
    /**
     * Get the list of published riddles.
     *
     * @return \App\Models\Riddle
     */
    public function getPublishedRiddles();

    /**
     * Create a new riddle.
     *
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Riddle
     */
    public function createRiddle(array $data, int $userId);

    /**
     * Get the detail of a riddle with its steps.
     *
     * @param  \App\Models\Riddle  $riddle
     * @return \App\Models\Riddle
     */
    public function getRiddleDetail(Riddle $riddle);

    /**
     * Update a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  array  $data
     * @param  int  $userId
     * @return \App\Models\Riddle
     */
    public function updateRiddle(Riddle $riddle, array $data, int $userId);

    /**
     * Delete a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $userId
     * @return bool
     */
    public function deleteRiddle(Riddle $riddle, int $userId);

    /**
     * Get the user's game session for a riddle.
     *
     * @param  \App\Models\Riddle  $riddle
     * @param  int  $userId
     * @return \App\Models\GameSession
     */
    public function getGameSessionForRiddle(Riddle $riddle, int $userId);

    /**
     * Get the user's created riddles count.
     *
     * @param  int  $userId
     * @return int
     */
    public function getCreatedCount(int $userId);

    /**
     * Get paginated riddles created by a user.
     *
     * @param int $userId
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getCreatedRiddles(int $userId, int $page, int $limit);
}