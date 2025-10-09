<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'user_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who used this code
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a unique 6-character code (lowercase)
     */
    public static function generateUniqueCode()
    {
        do {
            $code = strtolower(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Validate and use a code for a user
     */
    public static function validateAndUseCode($code, $userId)
    {
        // Clean and normalize code
        $cleanCode = strtolower(trim(preg_replace('/\s+/', '', $code)));
        
        // Find the code
        $codeRecord = self::where('code', $cleanCode)
                         ->where('is_active', true)
                         ->whereNull('user_id') // Not used yet
                         ->first();
        
        if (!$codeRecord) {
            return [
                'success' => false,
                'message' => 'کد وارد شده اشتباه است یا قبلاً استفاده شده'
            ];
        }
        
        // Mark code as used
        $codeRecord->update([
            'user_id' => $userId,
            'is_active' => false
        ]);
        
        return [
            'success' => true,
            'message' => 'کد با موفقیت اعمال شد',
            'code' => $codeRecord
        ];
    }

    /**
     * Check if a code is valid (without using it)
     */
    public static function isValidCode($code)
    {
        $cleanCode = strtolower(trim(preg_replace('/\s+/', '', $code)));
        
        return self::where('code', $cleanCode)
                  ->where('is_active', true)
                  ->whereNull('user_id')
                  ->exists();
    }
} 