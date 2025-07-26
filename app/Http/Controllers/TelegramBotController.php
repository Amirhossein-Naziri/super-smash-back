<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramBotController extends Controller
{
    protected $telegram;

    public function __construct()
    {
        // Use the bot token directly for now
        $this->telegram = new Api('8140283298:AAEiANouwoVgV2WKOIqsXEp-nQyV5ARUrlw');
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        $update = $this->telegram->getWebhookUpdates();
        
        // Handle /start command
        if ($update->has('message') && $update->getMessage()->has('text')) {
            $message = $update->getMessage();
            $text = $message->getText();
            $chatId = $message->getChat()->getId();
            
            if ($text === '/start') {
                $this->handleStartCommand($chatId, $message);
            }
        }
        
        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle /start command
     */
    protected function handleStartCommand($chatId, $message)
    {
        $adminIds = config('services.telegram_admin_ids', []);
        if (in_array($chatId, $adminIds)) {
            $this->sendAdminMenu($chatId);
            return;
        }
        $welcomeMessage = "🎮 سلام! به بازی Super Smash خوش آمدید!\n\n";
        $welcomeMessage .= "برای شروع بازی، روی دکمه زیر کلیک کنید:\n";
        $welcomeMessage .= "👇👇👇";
        $keyboard = [
            [
                ['text' => '🎮 شروع بازی', 'web_app' => ['url' => 'https://daom.ir/game']]
            ]
        ];
        $replyMarkup = json_encode([
            'inline_keyboard' => $keyboard
        ]);
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $welcomeMessage,
            'reply_markup' => $replyMarkup,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Send admin menu as inline keyboard
     */
    private function sendAdminMenu($chatId)
    {
        $text = "👑 به پنل ادمین خوش آمدید!\n\nگزینه مورد نظر را انتخاب کنید:";
        $keyboard = [
            [
                ['text' => 'تنظیمات داستان', 'callback_data' => 'admin_story_settings'],
                ['text' => 'تنظیمات کدها', 'callback_data' => 'admin_code_settings'],
            ],
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_back'],
            ]
        ];
        $replyMarkup = json_encode([
            'inline_keyboard' => $keyboard
        ]);
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Send codes settings submenu
     */
    private function sendCodesSettingsMenu($chatId)
    {
        $text = "🔧 تنظیمات کدها\n\nگزینه مورد نظر را انتخاب کنید:";
        $keyboard = [
            [
                ['text' => 'ایجاد کد های جدید', 'callback_data' => 'admin_create_codes'],
                ['text' => 'لیست کد ها', 'callback_data' => 'admin_list_codes'],
            ],
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_back_to_main'],
            ]
        ];
        $replyMarkup = json_encode([
            'inline_keyboard' => $keyboard
        ]);
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Set webhook for the bot
     */
    public function setWebhook()
    {
        $webhookUrl = 'https://api.daom.ir/api/telegram/webhook';
        
        try {
            $result = $this->telegram->setWebhook(['url' => $webhookUrl]);
            
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook set successfully',
                    'url' => $webhookUrl
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to set webhook'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error setting webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bot info
     */
    public function getBotInfo()
    {
        try {
            $botInfo = $this->telegram->getMe();
            
            return response()->json([
                'success' => true,
                'bot_info' => $botInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting bot info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log error from frontend to a file
     */
    public function logError(\Illuminate\Http\Request $request)
    {
        $error = $request->input('error');
        $file = storage_path('logs/telegram-error.log');
        file_put_contents($file, date('Y-m-d H:i:s') . " - " . $error . "\n", FILE_APPEND);
        return response()->json(['success' => true]);
    }
} 