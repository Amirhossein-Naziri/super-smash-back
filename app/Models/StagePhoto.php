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
     * Get photos for a stage in order
     */
    public static function getPhotosForStage($stageId)
    {
        return self::where('stage_id', $stageId)
                   ->orderBy('photo_order')
                   ->get();
    }
}
