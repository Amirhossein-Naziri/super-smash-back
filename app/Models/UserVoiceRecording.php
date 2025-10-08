<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVoiceRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stage_photo_id',
        'voice_file_path',
        'duration_seconds',
        'is_completed'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    /**
     * Get the user who made this recording
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the stage photo for this recording
     */
    public function stagePhoto()
    {
        return $this->belongsTo(StagePhoto::class);
    }

    /**
     * Get recordings for a user and stage
     */
    public static function getRecordingsForUserStage($userId, $stageId)
    {
        return self::where('user_id', $userId)
                   ->whereHas('stagePhoto', function($query) use ($stageId) {
                       $query->where('stage_id', $stageId);
                   })
                   ->get();
    }

    /**
     * Check if user has completed all recordings for a stage
     */
    public static function hasCompletedAllRecordings($userId, $stageId)
    {
        $totalPhotos = StagePhoto::where('stage_id', $stageId)->count();
        $completedRecordings = self::where('user_id', $userId)
                                  ->whereHas('stagePhoto', function($query) use ($stageId) {
                                      $query->where('stage_id', $stageId);
                                  })
                                  ->where('is_completed', true)
                                  ->count();

        return $completedRecordings >= $totalPhotos;
    }
}
