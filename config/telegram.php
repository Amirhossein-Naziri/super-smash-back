<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Telegram bot.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN', '8140283298:AAEiANouwoVgV2WKOIqsXEp-nQyV5ARUrlw'),
    
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', 'https://api.daom.ir/api/telegram/webhook'),
    
    'game_url' => env('TELEGRAM_GAME_URL', 'https://daom.ir/game'),
    
    'admin_ids' => env('TELEGRAM_ADMIN_IDS', []),
    
    'messages' => [
        'welcome' => "🎮 سلام! به بازی Super Smash خوش آمدید!\n\nبرای شروع بازی، روی دکمه زیر کلیک کنید:\n👇👇👇",
        'admin_welcome' => "👑 به پنل ادمین خوش آمدید!\n\nگزینه مورد نظر را انتخاب کنید:",
        'all_stages_completed' => '🎉 تمامی ۱۷۰ مرحله تکمیل شده است!',
        'no_codes_found' => "📋 لیست کدها\n\nهیچ کدی یافت نشد.",
        'no_stages_found' => "📋 لیست مرحله‌ها\n\nهیچ مرحله‌ای یافت نشد.",
    ],
    
    'keyboards' => [
        'admin_main' => [
            [
                ['text' => 'تنظیمات داستان', 'callback_data' => 'admin_story_settings'],
                ['text' => 'تنظیمات کدها', 'callback_data' => 'admin_code_settings'],
            ],
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_back'],
            ]
        ],
        'codes_settings' => [
            [
                ['text' => 'ایجاد کد های جدید', 'callback_data' => 'admin_create_codes'],
                ['text' => 'لیست کد ها', 'callback_data' => 'admin_list_codes'],
            ],
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_back_to_main'],
            ]
        ],
        'story_settings' => [
            [
                ['text' => 'ساخت داستان جدید', 'callback_data' => 'admin_create_story'],
                ['text' => 'لیست مرحله ها', 'callback_data' => 'admin_list_stages'],
            ],
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_back_to_main'],
            ]
        ],
        'code_count' => [
            [
                ['text' => '5 کد', 'callback_data' => 'create_codes_5'],
                ['text' => '10 کد', 'callback_data' => 'create_codes_10'],
            ],
            [
                ['text' => '20 کد', 'callback_data' => 'create_codes_20'],
                ['text' => '50 کد', 'callback_data' => 'create_codes_50'],
            ],
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_code_settings'],
            ]
        ],
        'story_correct' => [
            [
                ['text' => '✅ درست', 'callback_data' => 'story_correct_true'],
                ['text' => '❌ اشتباه', 'callback_data' => 'story_correct_false'],
            ]
        ],
    ],
]; 