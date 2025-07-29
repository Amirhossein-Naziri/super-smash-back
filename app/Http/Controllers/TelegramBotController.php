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
                $this->adminService->handleStoryTextMessage($chatId, $text);
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
        $adminIds = config('telegram.admin_ids', []);
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
            case 'admin_create_codes':
                $this->adminService->askForCodeCount($chatId);
                break;
            case 'admin_list_codes':
                $this->adminService->showCodesList($chatId);
                break;
            case 'admin_create_story':
                $this->adminService->startStoryCreation($chatId);
                break;
            case 'admin_list_stages':
                $this->adminService->showStagesList($chatId);
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
} 