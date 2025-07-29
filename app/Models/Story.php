<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'title',
        'description',
        'image_path',
        'is_correct',
        'order'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    /**
     * Get the stage that owns this story
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get the correct story for a stage
     */
    public static function getCorrectStoryForStage($stageId)
    {
        return self::where('stage_id', $stageId)
                   ->where('is_correct', true)
                   ->first();
    }

    /**
     * Get all stories for a stage in random order (for game display)
     */
    public static function getStoriesForStageRandom($stageId)
    {
        return self::where('stage_id', $stageId)
                   ->inRandomOrder()
                   ->get();
    }
} 