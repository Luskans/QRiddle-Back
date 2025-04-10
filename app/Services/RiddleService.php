<?php

namespace App\Services;

use App\Interfaces\RiddleServiceInterface;
use App\Models\Riddle;
use Illuminate\Support\Facades\DB;


class RiddleService implements RiddleServiceInterface
{
    public function getCreatedCount($userId)
    {
        return DB::table('riddles')
            ->where('creator_id', $userId)
            ->count();
    }
}