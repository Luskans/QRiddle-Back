<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SessionStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'step_id',
        'hint_used_number', 
        'status',
        'start_time',
        'end_time'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function gameSession()
    {
        return $this->belongsTo(GameSession::class);
    }

    public function step()
    {
        return $this->belongsTo(Step::class);
    }
}
