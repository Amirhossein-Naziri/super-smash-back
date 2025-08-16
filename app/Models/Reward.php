<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'score',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'score' => 'integer'
    ];

    /**
     * Get the rewards that are currently active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the rewards ordered by score
     */
    public function scopeOrderByScore($query, $direction = 'desc')
    {
        return $query->orderBy('score', $direction);
    }
} 