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
                         ->first();
        
        if (!$codeRecord) {
            return [
                'success' => false,
                'message' => 'کد وارد شده اشتباه است یا غیرفعال است'
            ];
        }
        
        // Check if this user has already used this code
        if ($codeRecord->user_id === $userId) {
            return [
                'success' => true,
                'message' => 'کد قبلاً توسط شما استفاده شده است',
                'code' => $codeRecord
            ];
        }
        
        // Check if code is already used by another user
        if ($codeRecord->user_id !== null && $codeRecord->user_id !== $userId) {
            return [
                'success' => false,
                'message' => 'کد قبلاً توسط کاربر دیگری استفاده شده است'
            ];
        }
        
        // Mark code as used by this user
        $codeRecord->update([
            'user_id' => $userId
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
                  ->exists();
    }
} 