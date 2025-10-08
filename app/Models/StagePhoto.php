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
        'code_1',
        'code_2',
        'is_unlocked'
    ];

    protected $casts = [
        'is_unlocked' => 'boolean',
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
     * Generate unique codes for this photo
     */
    public static function generateUniqueCodes()
    {
        do {
            $code1 = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
            $code2 = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        } while (
            self::where('code_1', $code1)->orWhere('code_2', $code1)->exists() ||
            self::where('code_1', $code2)->orWhere('code_2', $code2)->exists() ||
            $code1 === $code2
        );

        return [$code1, $code2];
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
     * Check if both codes are valid for unlocking
     */
    public function validateCodes($code1, $code2)
    {
        // Clean and normalize codes - remove all whitespace and convert to uppercase
        $cleanCode1 = strtoupper(trim(preg_replace('/\s+/', '', $code1)));
        $cleanCode2 = strtoupper(trim(preg_replace('/\s+/', '', $code2)));
        $cleanStoredCode1 = strtoupper(trim(preg_replace('/\s+/', '', $this->code_1)));
        $cleanStoredCode2 = strtoupper(trim(preg_replace('/\s+/', '', $this->code_2)));
        
        return ($cleanStoredCode1 === $cleanCode1 && $cleanStoredCode2 === $cleanCode2) ||
               ($cleanStoredCode1 === $cleanCode2 && $cleanStoredCode2 === $cleanCode1);
    }
}
