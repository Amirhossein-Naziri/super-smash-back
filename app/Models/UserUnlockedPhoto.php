<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUnlockedPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stage_photo_id',
        'unlocked_at'
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
    ];

    /**
     * Get the user who unlocked this photo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the stage photo that was unlocked
     */
    public function stagePhoto()
    {
        return $this->belongsTo(StagePhoto::class);
    }

    /**
     * Record that a user unlocked a photo
     */
    public static function recordUnlock($userId, $stagePhotoId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'stage_photo_id' => $stagePhotoId],
            ['unlocked_at' => now()]
        );
    }

    /**
     * Record that a user partially unlocked a photo (for legacy support)
     */
    public static function recordPartialUnlock($userId, $stagePhotoId)
    {
        // For now, we treat partial unlock as full unlock
        // This maintains compatibility with existing code
        return self::recordUnlock($userId, $stagePhotoId);
    }

    /**
     * Get users who unlocked a specific photo
     */
    public static function getUsersForPhoto($stagePhotoId)
    {
        return self::where('stage_photo_id', $stagePhotoId)
                   ->with('user')
                   ->get();
    }

    /**
     * Get photos unlocked by a specific user in a stage
     */
    public static function getPhotosForUserInStage($userId, $stageId)
    {
        return self::where('user_id', $userId)
                   ->whereHas('stagePhoto', function($query) use ($stageId) {
                       $query->where('stage_id', $stageId);
                   })
                   ->with('stagePhoto')
                   ->get();
    }
}
