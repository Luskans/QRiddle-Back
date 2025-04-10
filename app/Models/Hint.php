<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Hint extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'step_id',
        'order_number',
        'type',
        'content'
    ];

    public function step()
    {
        return $this->belongsTo(Step::class);
    }
}
