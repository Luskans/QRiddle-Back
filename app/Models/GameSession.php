<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'riddle_id',
        'user_id',
        'status',
        'score'
    ];

    public function riddle()
    {
        return $this->belongsTo(Riddle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sessionSteps()
    {
        return $this->hasMany(SessionStep::class);
    }

    public function latestActiveSessionStep(): HasOne
    {
        return $this->hasOne(SessionStep::class)
                    ->where('status', 'active')
                    ->latest('start_time');
    }

    public function getTotalDuration()
    {
        return $this->sessionSteps->reduce(function ($total, $step) {
            if ($step->end_time && $step->start_time) {
                return $total + (strtotime($step->end_time) - strtotime($step->start_time));
            }
            return $total;
        }, 0);
    }
}