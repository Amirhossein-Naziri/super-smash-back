<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_number',
        'points',
        'is_completed'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    /**
     * Get the stories for this stage
     */
    public function stories()
    {
        return $this->hasMany(Story::class)->orderBy('order');
    }

    /**
     * Get the next incomplete stage
     */
    public static function getNextIncompleteStage()
    {
        return self::where('is_completed', false)
                   ->orderBy('stage_number')
                   ->first();
    }

    /**
     * Get the highest stage number
     */
    public static function getHighestStageNumber()
    {
        return self::max('stage_number') ?? 0;
    }

    /**
     * Check if all stages are completed
     */
    public static function areAllStagesCompleted()
    {
        return self::where('is_completed', false)->count() === 0;
    }
} 