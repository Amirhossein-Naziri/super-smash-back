<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpinnerResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'result_images',
        'is_win',
        'points_earned',
        'spin_date'
    ];

    protected $casts = [
        'result_images' => 'array',
        'is_win' => 'boolean',
        'points_earned' => 'integer',
        'spin_date' => 'date'
    ];

    /**
     * رابطه با کاربر
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * بررسی اینکه آیا کاربر امروز اسپین کرده یا نه
     */
    public static function hasUserSpunToday($userId): bool
    {
        return self::where('user_id', $userId)
            ->where('spin_date', today())
            ->exists();
    }

    /**
     * دریافت آخرین اسپین کاربر
     */
    public static function getLastUserSpin($userId)
    {
        return self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
