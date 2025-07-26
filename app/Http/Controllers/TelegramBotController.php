<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use App\Models\Code;

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
        
        // Handle callback queries (button clicks)
        if ($update->has('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $callbackData = $callbackQuery->getData();
            
            $this->handleCallbackQuery($chatId, $callbackData);
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
     * Handle callback queries from inline keyboards
     */
    private function handleCallbackQuery($chatId, $callbackData)
    {
        switch ($callbackData) {
            case 'admin_code_settings':
                $this->sendCodesSettingsMenu($chatId);
                break;
            case 'admin_back_to_main':
                $this->sendAdminMenu($chatId);
                break;
            case 'admin_story_settings':
                // TODO: Implement story settings menu
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'تنظیمات داستان - به زودی...',
                    'parse_mode' => 'HTML'
                ]);
                break;
            case 'admin_create_codes':
                $this->askForCodeCount($chatId);
                break;
            case 'admin_list_codes':
                $this->showCodesList($chatId);
                break;
            default:
                // Handle create codes with count
                if (strpos($callbackData, 'create_codes_') === 0) {
                    $count = (int) str_replace('create_codes_', '', $callbackData);
                    $this->createCodes($chatId, $count);
                }
                break;
        }
    }

    /**
     * Ask admin for the number of codes to create
     */
    private function askForCodeCount($chatId)
    {
        $text = "🔧 ایجاد کد های جدید\n\nتعداد کدهایی که می‌خواهید ایجاد شود را انتخاب کنید:";
        $keyboard = [
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
     * Create specified number of codes
     */
    private function createCodes($chatId, $count)
    {
        try {
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $code = Code::create([
                    'code' => Code::generateUniqueCode(),
                    'is_active' => true
                ]);
                $codes[] = $code->code;
            }

            $text = "✅ {$count} کد جدید با موفقیت ایجاد شد:\n\n";
            $text .= implode("\n", $codes);
            $text .= "\n\nکدها فعال هستند و آماده استفاده می‌باشند.";

            $keyboard = [
                [
                    ['text' => 'بازگشت به تنظیمات کدها', 'callback_data' => 'admin_code_settings'],
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
        } catch (\Exception $e) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ خطا در ایجاد کدها: ' . $e->getMessage(),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    /**
     * Show list of all codes
     */
    private function showCodesList($chatId)
    {
        try {
            $codes = Code::with('user')->orderBy('created_at', 'desc')->get();
            
            if ($codes->isEmpty()) {
                $text = "📋 لیست کدها\n\nهیچ کدی یافت نشد.";
            } else {
                $text = "📋 لیست کدها\n\n";
                foreach ($codes as $code) {
                    $status = $code->is_active ? "✅ فعال" : "❌ غیرفعال";
                    $usedBy = $code->user ? "👤 {$code->user->name}" : "🔓 استفاده نشده";
                    $text .= "🔑 {$code->code} - {$status} - {$usedBy}\n";
                }
            }

            $keyboard = [
                [
                    ['text' => 'بازگشت به تنظیمات کدها', 'callback_data' => 'admin_code_settings'],
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
        } catch (\Exception $e) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ خطا در نمایش لیست کدها: ' . $e->getMessage(),
                'parse_mode' => 'HTML'
            ]);
        }
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