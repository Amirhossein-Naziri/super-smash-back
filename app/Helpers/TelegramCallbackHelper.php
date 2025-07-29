<?php

namespace App\Helpers;

class TelegramCallbackHelper
{
    /**
     * Parse callback data and return action and parameters
     */
    public static function parseCallbackData(string $callbackData): array
    {
        if (strpos($callbackData, 'create_codes_') === 0) {
            return [
                'action' => 'create_codes',
                'count' => (int) str_replace('create_codes_', '', $callbackData)
            ];
        }
        
        if (strpos($callbackData, 'story_correct_') === 0) {
            return [
                'action' => 'story_correct',
                'is_correct' => str_replace('story_correct_', '', $callbackData) === 'true'
            ];
        }
        
        if (strpos($callbackData, 'view_stage_') === 0) {
            return [
                'action' => 'view_stage',
                'stage_id' => (int) str_replace('view_stage_', '', $callbackData)
            ];
        }
        
        return [
            'action' => $callbackData,
            'parameters' => []
        ];
    }

    /**
     * Get callback action type
     */
    public static function getActionType(string $callbackData): string
    {
        $parsed = self::parseCallbackData($callbackData);
        return $parsed['action'];
    }

    /**
     * Check if callback is admin action
     */
    public static function isAdminAction(string $callbackData): bool
    {
        return strpos($callbackData, 'admin_') === 0;
    }

    /**
     * Check if callback is story action
     */
    public static function isStoryAction(string $callbackData): bool
    {
        return strpos($callbackData, 'story_') === 0;
    }
} 