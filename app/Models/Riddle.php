<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Riddle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'creator_id',
        'title',
        'description',
        'is_private', 
        'password',
        'status',
        'latitude',
        'longitude'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function steps()
    {
        return $this->hasMany(Step::class);
    }

    public function gameSessions()
    {
        return $this->hasMany(GameSession::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function stepsCount()
    {
        return $this->withCount('steps');
    }

    // TODO : vérifier quelle méthodes plus rapide
    public function getStepsCountAttribute()
    {
        return $this->steps()->count();
    }
}
