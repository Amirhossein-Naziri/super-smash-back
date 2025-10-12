<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StagePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'image_path',
        'blurred_image_path',
        'photo_order',
        'is_unlocked',
        'partially_unlocked'
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'partially_unlocked' => 'boolean',
    ];

    /**
     * Get the stage that owns this photo
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get voice recordings for this photo
     */
    public function voiceRecordings()
    {
        return $this->hasMany(UserVoiceRecording::class);
    }

    /**
     * Get user progress for this photo
     */
    public function userProgress()
    {
        return $this->hasMany(UserUnlockedPhoto::class, 'stage_photo_id');
    }

    /**
     * Get photos for a stage in order
     */
    public static function getPhotosForStage($stageId)
    {
        return self::where('stage_id', $stageId)
                   ->orderBy('photo_order')
                   ->get();
    }

    /**
     * Generate unique codes for a photo
     */
    public static function generateUniqueCodes()
    {
        do {
            $code1 = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
            $code2 = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        } while (
            \App\Models\Code::where('code', $code1)->exists() || 
            \App\Models\Code::where('code', $code2)->exists() ||
            $code1 === $code2
        );

        return [$code1, $code2];
    }
}
