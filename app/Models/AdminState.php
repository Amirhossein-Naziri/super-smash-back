<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminState extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'state_data',
        'expires_at'
    ];

    protected $casts = [
        'state_data' => 'array',
        'expires_at' => 'datetime'
    ];

    /**
     * Get state for a specific chat ID
     */
    public static function getState($chatId)
    {
        $state = self::where('chat_id', $chatId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $state ? $state->state_data : null;
    }

    /**
     * Set state for a specific chat ID
     */
    public static function setState($chatId, array $stateData, $expiresAt = null)
    {
        // Delete existing state for this chat
        self::where('chat_id', $chatId)->delete();

        // Create new state
        return self::create([
            'chat_id' => $chatId,
            'state_data' => $stateData,
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * Clear state for a specific chat ID
     */
    public static function clearState($chatId)
    {
        return self::where('chat_id', $chatId)->delete();
    }

    /**
     * Clean up expired states
     */
    public static function cleanupExpired()
    {
        return self::where('expires_at', '<', now())->delete();
    }
} 