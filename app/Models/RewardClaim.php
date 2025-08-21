<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reward_id',
        'stage_number',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'stage_number' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }
}

