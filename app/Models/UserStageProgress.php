<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStageProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stage_id',
        'unlocked_photos_count',
        'completed_voice_recordings',
        'stage_completed'
    ];

    protected $casts = [
        'stage_completed' => 'boolean',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the stage
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get or create progress for user and stage
     */
    public static function getOrCreateProgress($userId, $stageId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'stage_id' => $stageId],
            [
                'unlocked_photos_count' => 0,
                'completed_voice_recordings' => 0,
                'stage_completed' => false
            ]
        );
    }

    /**
     * Update unlocked photos count
     */
    public function updateUnlockedPhotos($count)
    {
        $this->unlocked_photos_count = $count;
        $this->save();
    }

    /**
     * Update completed voice recordings count
     */
    public function updateCompletedRecordings($count)
    {
        $this->completed_voice_recordings = $count;
        
        // Check if stage is completed (all 6 photos unlocked and all voice recordings done)
        $totalPhotos = StagePhoto::where('stage_id', $this->stage_id)->count();
        if ($this->unlocked_photos_count >= $totalPhotos && $count >= $totalPhotos) {
            $this->stage_completed = true;
        }
        
        $this->save();
    }

    /**
     * Get next incomplete stage for user
     */
    public static function getNextIncompleteStage($userId)
    {
        $completedStages = self::where('user_id', $userId)
                              ->where('stage_completed', true)
                              ->pluck('stage_id')
                              ->toArray();

        return Stage::whereNotIn('id', $completedStages)
                    ->orderBy('stage_number')
                    ->first();
    }
}
