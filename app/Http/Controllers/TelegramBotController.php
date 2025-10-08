<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use App\Services\TelegramAdminService;
use App\Traits\TelegramMessageTrait;
use App\Helpers\TelegramCallbackHelper;

class TelegramBotController extends Controller
{
    use TelegramMessageTrait;

    protected $telegram;
    protected $adminService;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bot_token'));
        $this->adminService = new TelegramAdminService($this->telegram);
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        $update = $this->telegram->getWebhookUpdates();
        
        if ($update->has('message') && $update->getMessage()->has('text')) {
            $message = $update->getMessage();
            $text = $message->getText();
            $chatId = $message->getChat()->getId();
            
            if ($text === '/start') {
                $this->handleStartCommand($chatId, $message);
            } else {
                $this->adminService->handleTextMessage($chatId, $text);
            }
        }
        
        if ($update->has('message') && $update->getMessage()->has('photo')) {
            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $this->adminService->handlePhotoMessage($chatId, $message);
        }
        
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
        // Check both old and new config locations for backward compatibility
        $adminIds = config('telegram.admin_ids', config('services.telegram_admin_ids', []));
        
        // Debug: Log the admin check
        \Log::info("Admin check for chat ID: {$chatId}", [
            'admin_ids' => $adminIds,
            'is_admin' => in_array($chatId, $adminIds)
        ]);
        
        // Temporary: Always show admin menu for debugging
        if (empty($adminIds)) {
            \Log::warning("No admin IDs configured. Showing admin menu to chat ID: {$chatId}");
            $this->adminService->sendAdminMenu($chatId);
            return;
        }
        
        if (in_array($chatId, $adminIds)) {
            $this->adminService->sendAdminMenu($chatId);
            return;
        }

        $keyboard = [
            [
                ['text' => '🎮 شروع بازی', 'web_app' => ['url' => config('telegram.game_url')]]
            ]
        ];
        
        $this->sendMessage($chatId, config('telegram.messages.welcome'), $keyboard);
    }

    /**
     * Handle callback queries from inline keyboards
     */
    private function handleCallbackQuery($chatId, $callbackData)
    {
        switch ($callbackData) {
            case 'admin_code_settings':
                $this->adminService->sendCodesSettingsMenu($chatId);
                break;
            case 'admin_back_to_main':
                $this->adminService->sendAdminMenu($chatId);
                break;
            case 'admin_story_settings':
                $this->adminService->sendStorySettingsMenu($chatId);
                break;
            case 'admin_reward_settings':
                $this->adminService->sendRewardSettingsMenu($chatId);
                break;
            case 'admin_create_codes':
                $this->adminService->askForCodeCount($chatId);
                break;
            case 'admin_list_codes':
                $this->adminService->showCodesList($chatId);
                break;
            case 'admin_export_codes_csv':
                $this->adminService->exportCodesCsvAndSend($chatId);
                break;
            case 'admin_create_story':
                $this->adminService->startStoryCreation($chatId);
                break;
            case 'admin_start_stage_photo_upload':
                $this->adminService->startStagePhotoUpload($chatId);
                break;
            case 'admin_list_stages':
                $this->adminService->showStagesList($chatId);
                break;
            case 'admin_create_reward':
                $this->adminService->startRewardCreation($chatId);
                break;
            case 'admin_list_rewards':
                $this->adminService->showRewardsList($chatId);
                break;
            case 'admin_show_state':
                $this->adminService->showCurrentState($chatId);
                break;
            case 'admin_debug_db':
                $this->adminService->debugDatabaseState($chatId);
                break;
            case 'admin_test_photo':
                $this->adminService->sendMessage($chatId, "📸 لطفاً یک عکس ارسال کنید تا ساختار آن را بررسی کنیم.");
                break;
            case 'admin_test_save_photo':
                // Set test mode
                $this->adminService->setAdminState($chatId, [
                    'mode' => 'test',
                    'test_mode' => 'save_photo'
                ]);
                $this->adminService->sendMessage($chatId, "📸 لطفاً یک عکس ارسال کنید تا تست ذخیره کنیم.");
                break;
            case 'admin_test_download':
                // Set test mode
                $this->adminService->setAdminState($chatId, [
                    'mode' => 'test',
                    'test_mode' => 'download'
                ]);
                $this->adminService->sendMessage($chatId, "🔗 لطفاً یک عکس ارسال کنید تا تست دانلود کنیم.");
                break;
            case 'admin_reset_story':
                $this->adminService->resetStoryCreation($chatId);
                break;
            case 'finalize_stage':
                $this->adminService->finalizeStage($chatId);
                break;
            default:
                $this->handleDynamicCallback($chatId, $callbackData);
                break;
        }
    }

    /**
     * Handle dynamic callback queries
     */
    private function handleDynamicCallback($chatId, $callbackData)
    {
        $parsed = TelegramCallbackHelper::parseCallbackData($callbackData);
        
        switch ($parsed['action']) {
            case 'create_codes':
                $this->adminService->createCodes($chatId, $parsed['count']);
                break;
            case 'story_correct':
                $this->adminService->handleStoryCorrectChoice($chatId, $parsed['is_correct']);
                break;
            case 'view_stage':
                $this->adminService->showStageDetails($chatId, $parsed['stage_id']);
                break;
        }
    }

    /**
     * Set webhook for the bot
     */
    public function setWebhook()
    {
        $webhookUrl = config('telegram.webhook_url');
        
        try {
            $result = $this->telegram->setWebhook(['url' => $webhookUrl]);
            
            return response()->json([
                'success' => $result,
                'message' => $result ? 'Webhook set successfully' : 'Failed to set webhook',
                'url' => $webhookUrl
            ], $result ? 200 : 500);
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
    public function logError(Request $request)
    {
        $error = $request->input('error');
        $file = storage_path('logs/telegram-error.log');
        file_put_contents($file, date('Y-m-d H:i:s') . " - " . $error . "\n", FILE_APPEND);
        return response()->json(['success' => true]);
    }

    /**
     * Get current chat ID for admin setup
     */
    public function getChatId(Request $request)
    {
        $chatId = $request->input('chat_id');
        if ($chatId) {
            \Log::info("Chat ID received: {$chatId}");
            return response()->json([
                'success' => true,
                'chat_id' => $chatId,
                'message' => 'Chat ID logged successfully. Add this ID to your .env file as ADMIN_IDS'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No chat_id provided'
        ]);
    }
    /**
     * Test admin access
     */
    public function testAdmin(Request $request)
    {
        $chatId = $request->input('chat_id');
        $adminIds = config('telegram.admin_ids', config('services.telegram_admin_ids', []));
        
        return response()->json([
            'success' => true,
            'chat_id' => $chatId,
            'admin_ids' => $adminIds,
            'is_admin' => in_array($chatId, $adminIds),
            'config_telegram_admin_ids' => config('telegram.admin_ids', []),
            'config_services_telegram_admin_ids' => config('services.telegram_admin_ids', [])
        ]);
    }
}