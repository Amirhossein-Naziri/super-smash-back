<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use App\Models\Code;
use App\Models\Stage;
use App\Models\Story;
use Illuminate\Support\Facades\Storage;

class TelegramBotController extends Controller
{
    protected $telegram;
    
    // Store admin states for story creation
    private $adminStates = [];

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
            } else {
                // Handle text messages during story creation
                $this->handleTextMessage($chatId, $text, $message);
            }
        }
        
        // Handle photo messages during story creation
        if ($update->has('message') && $update->getMessage()->has('photo')) {
            $message = $update->getMessage();
            $chatId = $message->getChat()->getId();
            $this->handlePhotoMessage($chatId, $message);
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
     * Handle text messages during story creation
     */
    private function handleTextMessage($chatId, $text, $message)
    {
        // Check if admin is in story creation mode
        if (!isset($this->adminStates[$chatId]) || $this->adminStates[$chatId]['mode'] !== 'story_creation') {
            // Send debug message for non-story creation mode
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🔍 پیام دریافت شد اما در حالت ساخت داستان نیستید.\nمتن: {$text}",
                'parse_mode' => 'HTML'
            ]);
            return;
        }

        $state = $this->adminStates[$chatId];
        $waitingFor = $state['waiting_for'] ?? '';

        // Debug: Send current state
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "🔍 حالت فعلی: {$waitingFor}\nمتن دریافتی: {$text}",
            'parse_mode' => 'HTML'
        ]);

        switch ($waitingFor) {
            case 'points':
                if (is_numeric($text) && $text > 0) {
                    $this->adminStates[$chatId]['points'] = (int) $text;
                    $this->adminStates[$chatId]['waiting_for'] = 'title';
                    
                    // Send confirmation message
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "✅ امتیاز مرحله {$this->adminStates[$chatId]['stage_number']} ثبت شد: {$text}",
                        'parse_mode' => 'HTML'
                    ]);
                    
                    $this->askForStoryDetails($chatId, 1);
                } else {
                    $this->telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => '❌ لطفاً یک عدد مثبت وارد کنید.',
                        'parse_mode' => 'HTML'
                    ]);
                }
                break;

            case 'title':
                if (!isset($this->adminStates[$chatId]['current_story_data'])) {
                    $this->adminStates[$chatId]['current_story_data'] = [];
                }
                $this->adminStates[$chatId]['current_story_data']['title'] = $text;
                $this->adminStates[$chatId]['waiting_for'] = 'description';
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => '📝 حالا متن داستان را وارد کنید:',
                    'parse_mode' => 'HTML'
                ]);
                break;

            case 'description':
                $this->adminStates[$chatId]['current_story_data']['description'] = $text;
                $this->adminStates[$chatId]['waiting_for'] = 'image';
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => '🖼️ حالا عکس داستان را ارسال کنید:',
                    'parse_mode' => 'HTML'
                ]);
                break;
        }
    }

    /**
     * Handle photo messages during story creation
     */
    private function handlePhotoMessage($chatId, $message)
    {
        // Check if admin is in story creation mode
        if (!isset($this->adminStates[$chatId]) || $this->adminStates[$chatId]['mode'] !== 'story_creation') {
            return;
        }

        $state = $this->adminStates[$chatId];
        $waitingFor = $state['waiting_for'] ?? '';

        if ($waitingFor === 'image') {
            try {
                // Get the largest photo size
                $photos = $message->getPhoto();
                $largestPhoto = end($photos);
                $fileId = $largestPhoto['file_id'];

                // Get file info
                $file = $this->telegram->getFile(['file_id' => $fileId]);
                $filePath = $file['file_path'];

                // Download and save the image
                $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";
                $imageContent = file_get_contents($imageUrl);
                
                $fileName = 'story_' . time() . '_' . $state['current_story'] . '.jpg';
                $imagePath = 'stories/' . $fileName;
                
                // Save to storage
                Storage::disk('public')->put($imagePath, $imageContent);
                
                // Save image path to state
                if (!isset($this->adminStates[$chatId]['current_story_data'])) {
                    $this->adminStates[$chatId]['current_story_data'] = [];
                }
                $this->adminStates[$chatId]['current_story_data']['image_path'] = $imagePath;
                $this->adminStates[$chatId]['waiting_for'] = 'correct_choice';
                
                // Ask for correct/incorrect choice
                $this->askForCorrectChoice($chatId);
                
            } catch (\Exception $e) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ خطا در ذخیره عکس: ' . $e->getMessage(),
                    'parse_mode' => 'HTML'
                ]);
            }
        }
    }

    /**
     * Ask for correct/incorrect choice
     */
    private function askForCorrectChoice($chatId)
    {
        $state = $this->adminStates[$chatId];
        $storyNumber = $state['current_story'];
        
        $text = "✅ عکس داستان {$storyNumber} ذخیره شد!\n\n";
        $text .= "حالا انتخاب کنید که آیا این داستان درست است یا اشتباه:";
        
        $keyboard = [
            [
                ['text' => '✅ درست', 'callback_data' => 'story_correct_true'],
                ['text' => '❌ اشتباه', 'callback_data' => 'story_correct_false'],
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
     * Handle story correct/incorrect choice
     */
    private function handleStoryCorrectChoice($chatId, $isCorrect)
    {
        $state = $this->adminStates[$chatId];
        $storyNumber = $state['current_story'];
        
        // Add correct choice to current story data
        if (!isset($this->adminStates[$chatId]['current_story_data'])) {
            $this->adminStates[$chatId]['current_story_data'] = [];
        }
        $this->adminStates[$chatId]['current_story_data']['is_correct'] = $isCorrect;
        $this->adminStates[$chatId]['current_story_data']['order'] = $storyNumber;
        
        // Add story to stories array
        $this->adminStates[$chatId]['stories'][] = $this->adminStates[$chatId]['current_story_data'];
        
        // Clear current story data
        unset($this->adminStates[$chatId]['current_story_data']);
        
        $status = $isCorrect ? "✅ درست" : "❌ اشتباه";
        $text = "✅ داستان {$storyNumber} با موفقیت ذخیره شد!\n";
        $text .= "وضعیت: {$status}\n\n";
        
        if ($storyNumber < 3) {
            $text .= "حالا داستان " . ($storyNumber + 1) . " را شروع می‌کنیم...";
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ]);
            
            // Start next story
            $this->adminStates[$chatId]['current_story']++;
            $this->askForStoryDetails($chatId, $storyNumber + 1);
        } else {
            $text .= "🎉 تمامی ۳ داستان تکمیل شد!\n\n";
            $text .= "آیا می‌خواهید مرحله را نهایی کنید؟";
            
            $keyboard = [
                [
                    ['text' => '✅ بله، نهایی کن', 'callback_data' => 'finalize_stage'],
                    ['text' => '❌ لغو', 'callback_data' => 'admin_story_settings'],
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
                $this->sendStorySettingsMenu($chatId);
                break;
            case 'admin_create_codes':
                $this->askForCodeCount($chatId);
                break;
            case 'admin_list_codes':
                $this->showCodesList($chatId);
                break;
            case 'admin_create_story':
                $this->startStoryCreation($chatId);
                break;
            case 'admin_list_stages':
                $this->showStagesList($chatId);
                break;
            case 'finalize_stage':
                $this->finalizeStage($chatId);
                break;
            default:
                // Handle create codes with count
                if (strpos($callbackData, 'create_codes_') === 0) {
                    $count = (int) str_replace('create_codes_', '', $callbackData);
                    $this->createCodes($chatId, $count);
                }
                // Handle story creation steps
                elseif (strpos($callbackData, 'story_step_') === 0) {
                    $step = str_replace('story_step_', '', $callbackData);
                    $this->handleStoryCreationStep($chatId, $step);
                }
                // Handle stage selection for viewing
                elseif (strpos($callbackData, 'view_stage_') === 0) {
                    $stageId = (int) str_replace('view_stage_', '', $callbackData);
                    $this->showStageDetails($chatId, $stageId);
                }
                // Handle story correct/incorrect choice
                elseif (strpos($callbackData, 'story_correct_') === 0) {
                    $isCorrect = str_replace('story_correct_', '', $callbackData) === 'true';
                    $this->handleStoryCorrectChoice($chatId, $isCorrect);
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
     * Send story settings submenu
     */
    private function sendStorySettingsMenu($chatId)
    {
        $text = "📚 تنظیمات داستان‌ها\n\nگزینه مورد نظر را انتخاب کنید:";
        $keyboard = [
            [
                ['text' => 'ساخت داستان جدید', 'callback_data' => 'admin_create_story'],
                ['text' => 'لیست مرحله ها', 'callback_data' => 'admin_list_stages'],
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
     * Start story creation process
     */
    private function startStoryCreation($chatId)
    {
        // Get next stage number
        $nextStageNumber = Stage::getHighestStageNumber() + 1;
        
        if ($nextStageNumber > 170) {
            // All stages are completed
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '🎉 تمامی ۱۷۰ مرحله تکمیل شده است!',
                'parse_mode' => 'HTML'
            ]);
            return;
        }

        // Initialize admin state for story creation
        $this->adminStates[$chatId] = [
            'mode' => 'story_creation',
            'stage_number' => $nextStageNumber,
            'current_story' => 1,
            'stories' => [],
            'points' => null,
            'current_story_data' => [],
            'waiting_for' => 'points'
        ];

        $text = "📚 ساخت داستان جدید\n\n";
        $text .= "شما در حال ساخت مرحله {$nextStageNumber} هستید.\n\n";
        $text .= "برای شروع، ابتدا امتیاز این مرحله را وارد کنید:";
        
        $keyboard = [
            [
                ['text' => 'لغو', 'callback_data' => 'admin_story_settings'],
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
     * Handle story creation steps
     */
    private function handleStoryCreationStep($chatId, $step)
    {
        if (!isset($this->adminStates[$chatId])) {
            return;
        }

        $state = $this->adminStates[$chatId];
        
        switch ($step) {
            case 'points_entered':
                $this->askForStoryDetails($chatId, $state['current_story']);
                break;
            case 'story_complete':
                if ($state['current_story'] < 3) {
                    $this->adminStates[$chatId]['current_story']++;
                    $this->askForStoryDetails($chatId, $state['current_story']);
                } else {
                    $this->finalizeStage($chatId);
                }
                break;
        }
    }

    /**
     * Ask for story details
     */
    private function askForStoryDetails($chatId, $storyNumber)
    {
        $state = $this->adminStates[$chatId];
        
        $text = "📖 داستان {$storyNumber}\n\n";
        $text .= "لطفاً اطلاعات داستان {$storyNumber} را وارد کنید:\n\n";
        $text .= "1️⃣ عنوان داستان\n";
        $text .= "2️⃣ متن داستان\n";
        $text .= "3️⃣ عکس داستان\n";
        $text .= "4️⃣ انتخاب درست/اشتباه بودن\n\n";
        $text .= "ابتدا عنوان داستان را ارسال کنید:";

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);

        // Update state to wait for title
        $this->adminStates[$chatId]['waiting_for'] = 'title';
    }

    /**
     * Finalize stage creation
     */
    private function finalizeStage($chatId)
    {
        $state = $this->adminStates[$chatId];
        
        try {
            // Create stage
            $stage = Stage::create([
                'stage_number' => $state['stage_number'],
                'points' => $state['points'],
                'is_completed' => true
            ]);

            // Create stories
            foreach ($state['stories'] as $storyData) {
                Story::create([
                    'stage_id' => $stage->id,
                    'title' => $storyData['title'],
                    'description' => $storyData['description'],
                    'image_path' => $storyData['image_path'],
                    'is_correct' => $storyData['is_correct'],
                    'order' => $storyData['order']
                ]);
            }

            $text = "✅ مرحله {$state['stage_number']} با موفقیت ایجاد شد!\n\n";
            $text .= "📊 امتیاز: {$state['points']}\n";
            $text .= "📚 تعداد داستان‌ها: ۳\n\n";
            $text .= "آیا می‌خواهید مرحله بعدی را بسازید؟";

            $keyboard = [
                [
                    ['text' => 'بله، مرحله بعدی', 'callback_data' => 'admin_create_story'],
                    ['text' => 'بازگشت به منو', 'callback_data' => 'admin_story_settings'],
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

            // Clear admin state
            unset($this->adminStates[$chatId]);

        } catch (\Exception $e) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ خطا در ایجاد مرحله: ' . $e->getMessage(),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    /**
     * Show stages list
     */
    private function showStagesList($chatId)
    {
        $stages = Stage::with('stories')->orderBy('stage_number')->get();
        
        if ($stages->isEmpty()) {
            $text = "📋 لیست مرحله‌ها\n\nهیچ مرحله‌ای یافت نشد.";
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_story_settings'],
                ]
            ];
        } else {
            $text = "📋 لیست مرحله‌ها\n\n";
            $keyboard = [];
            
            foreach ($stages as $stage) {
                $status = $stage->is_completed ? "✅" : "⏳";
                $text .= "{$status} مرحله {$stage->stage_number} - {$stage->points} امتیاز\n";
                
                $keyboard[] = [
                    ['text' => "مرحله {$stage->stage_number}", 'callback_data' => "view_stage_{$stage->id}"]
                ];
            }
            
            $keyboard[] = [
                ['text' => 'بازگشت', 'callback_data' => 'admin_story_settings'],
            ];
        }

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
     * Show stage details
     */
    private function showStageDetails($chatId, $stageId)
    {
        $stage = Stage::with('stories')->find($stageId);
        
        if (!$stage) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ مرحله یافت نشد.',
                'parse_mode' => 'HTML'
            ]);
            return;
        }

        $text = "📖 جزئیات مرحله {$stage->stage_number}\n\n";
        $text .= "📊 امتیاز: {$stage->points}\n";
        $text .= "📚 داستان‌ها:\n\n";

        foreach ($stage->stories as $story) {
            $status = $story->is_correct ? "✅ درست" : "❌ اشتباه";
            $text .= "🔹 {$story->title}\n";
            $text .= "   {$story->description}\n";
            $text .= "   {$status}\n\n";
        }

        $keyboard = [
            [
                ['text' => 'بازگشت به لیست', 'callback_data' => 'admin_list_stages'],
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