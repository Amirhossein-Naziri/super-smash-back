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
        'unlocked_at',
        'is_partial_unlock'
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'is_partial_unlock' => 'boolean',
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
        return self::updateOrCreate(
            ['user_id' => $userId, 'stage_photo_id' => $stagePhotoId],
            ['unlocked_at' => now(), 'is_partial_unlock' => false]
        );
    }

    /**
     * Record that a user partially unlocked a photo
     */
    public static function recordPartialUnlock($userId, $stagePhotoId)
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'stage_photo_id' => $stagePhotoId],
            ['unlocked_at' => now(), 'is_partial_unlock' => true]
        );
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
