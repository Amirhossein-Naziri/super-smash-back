<?php

namespace App\Services;

use App\Models\Code;
use App\Models\Stage;
use App\Models\StagePhoto;
use App\Models\AdminState;
use App\Models\Reward;
use App\Traits\TelegramMessageTrait;
use App\Services\PhotoBlurService;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;
use App\Http\Controllers\CodeController;

class TelegramAdminService
{
    use TelegramMessageTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Get admin state
     */
    public function getAdminState($chatId)
    {
        return AdminState::getState($chatId);
    }

    /**
     * Set admin state
     */
    public function setAdminState($chatId, array $state): void
    {
        // Set expiration to 1 hour from now
        $expiresAt = now()->addHour();
        AdminState::setState($chatId, $state, $expiresAt);
    }

    /**
     * Clear admin state
     */
    public function clearAdminState($chatId): void
    {
        AdminState::clearState($chatId);
    }

    /**
     * Show current state (updated for new system)
     */
    public function showCurrentState($chatId): void
    {
        $state = $this->getAdminState($chatId);
        
        if (!$state) {
            $this->sendMessage($chatId, "🔍 وضعیت فعلی: هیچ حالتی تنظیم نشده است.");
            return;
        }
        
        $text = "🔍 وضعیت فعلی:\n\n";
        $text .= "حالت: {$state['mode']}\n";
        $text .= "انتظار برای: {$state['waiting_for']}\n";
        
        if (isset($state['stage_number'])) {
            $text .= "شماره مرحله: {$state['stage_number']}\n";
        }
        
        if (isset($state['current_photo'])) {
            $text .= "عکس فعلی: {$state['current_photo']}\n";
        }
        
        if (isset($state['photos_uploaded'])) {
            $text .= "عکس‌های آپلود شده: {$state['photos_uploaded']}/6\n";
        }
        
        if (isset($state['points'])) {
            $text .= "امتیاز: {$state['points']}\n";
        }
        
        // Add database state info for debugging
        $dbState = AdminState::where('chat_id', $chatId)->first();
        if ($dbState) {
            $text .= "\n📊 وضعیت دیتابیس:\n";
            $text .= "تاریخ ایجاد: {$dbState->created_at}\n";
            $text .= "تاریخ انقضا: {$dbState->expires_at}\n";
        }
        
        $keyboard = [
            [
                ['text' => '🔄 بازنشانی', 'callback_data' => 'admin_reset_story'],
                ['text' => '🔍 دیباگ دیتابیس', 'callback_data' => 'admin_debug_db'],
            ],
            [
                ['text' => '📸 تست عکس', 'callback_data' => 'admin_test_photo'],
                ['text' => 'بازگشت', 'callback_data' => 'admin_story_settings'],
            ]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Debug database state
     */
    public function debugDatabaseState($chatId): void
    {
        $states = AdminState::where('chat_id', $chatId)->get();
        
        if ($states->isEmpty()) {
            $text = "📊 دیباگ دیتابیس\n\nهیچ رکوردی یافت نشد.";
        } else {
            $text = "📊 دیباگ دیتابیس\n\n";
            foreach ($states as $state) {
                $text .= "🆔 ID: {$state->id}\n";
                $text .= "📅 ایجاد: {$state->created_at}\n";
                $text .= "⏰ انقضا: {$state->expires_at}\n";
                $text .= "📝 داده: " . json_encode($state->state_data, JSON_UNESCAPED_UNICODE) . "\n\n";
            }
        }
        
        $keyboard = [
            [
                ['text' => 'بازگشت', 'callback_data' => 'admin_show_state'],
            ]
        ];
        
        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Send admin menu
     */
    public function sendAdminMenu($chatId): void
    {
        $this->sendMessage($chatId, config('telegram.messages.admin_welcome'), config('telegram.keyboards.admin_main'));
    }

    /**
     * Send codes settings menu
     */
    public function sendCodesSettingsMenu($chatId): void
    {
        $text = "🔧 تنظیمات کدها\n\nگزینه مورد نظر را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.codes_settings'));
    }

    /**
     * Send story settings menu
     */
    public function sendStorySettingsMenu($chatId): void
    {
        $text = "📸 تنظیمات مراحل\n\nگزینه مورد نظر را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.story_settings'));
    }

    /**
     * Send reward settings menu
     */
    public function sendRewardSettingsMenu($chatId): void
    {
        $text = "🎁 تنظیمات جایزه‌ها\n\nگزینه مورد نظر را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.reward_settings'));
    }

    /**
     * Ask for code count
     */
    public function askForCodeCount($chatId): void
    {
        $text = "🔢 تعداد کدهای مورد نیاز را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.code_count'));
    }

    /**
     * Create codes
     */
    public function createCodes($chatId, $count): void
    {
        try {
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $code = Code::generateUniqueCode();
                Code::create([
                    'code' => $code,
                    'is_active' => true
                ]);
                $codes[] = $code;
            }

            $text = "✅ {$count} کد جدید ایجاد شد!\n\n";
            $text .= "📋 لیست کدها:\n";
            foreach ($codes as $code) {
                $text .= "🔑 {$code}\n";
            }

            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_code_settings'],
                ]
            ];

            $this->sendMessage($chatId, $text, $keyboard);

        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در ایجاد کدها: ' . $e->getMessage());
        }
    }

    /**
     * Show codes list
     */
    public function showCodesList($chatId): void
    {
        $codes = Code::orderBy('created_at', 'desc')->limit(50)->get();
        
        if ($codes->isEmpty()) {
            $text = config('telegram.messages.no_codes_found');
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_code_settings'],
                ]
            ];
        } else {
            $text = "📋 لیست کدها\n\n";
            
            foreach ($codes as $code) {
                $status = $code->is_active ? "✅" : "❌";
                $user = $code->user ? "👤 {$code->user->telegram_first_name}" : "👤 -";
                $text .= "{$status} {$code->code} - {$user}\n";
            }
            
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_code_settings'],
                ]
            ];
        }

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Export codes CSV and send
     */
    public function exportCodesCsvAndSend($chatId): void
    {
        try {
            $codes = Code::all();
            
            $csvContent = "Code,Is Active,User ID,Created At\n";
            foreach ($codes as $code) {
                $csvContent .= "{$code->code}," . ($code->is_active ? 'Yes' : 'No') . ",{$code->user_id},{$code->created_at}\n";
            }
            
            $fileName = 'codes_' . date('Y-m-d_H-i-s') . '.csv';
            $filePath = storage_path('app/temp/' . $fileName);
            
            // Create temp directory if it doesn't exist
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            file_put_contents($filePath, $csvContent);
            
            $this->sendDocument($chatId, $filePath, $fileName);
            
            // Clean up
            unlink($filePath);
            
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در اکسپورت کدها: ' . $e->getMessage());
        }
    }

    /**
     * Send document
     */
    private function sendDocument($chatId, $filePath, $fileName): void
    {
        $document = InputFile::create($filePath, $fileName);
        $this->telegram->sendDocument([
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => "📤 فایل CSV کدها"
        ]);
    }

    /**
     * Update admin state
     */
    private function updateAdminState($chatId, $key, $value): void
    {
        $state = $this->getAdminState($chatId);
        if ($state) {
            $state[$key] = $value;
            $this->setAdminState($chatId, $state);
        }
    }

    /**
     * Handle text messages (updated for new system)
     */
    public function handleTextMessage($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        
        if (!$state) {
            $this->sendMessage($chatId, "لطفاً از منوی ادمین استفاده کنید.");
            return;
        }
        
        $mode = $state['mode'] ?? '';
        $waitingFor = $state['waiting_for'] ?? '';
        
        // Handle stage photo upload text inputs
        if ($mode === 'stage_photo_upload') {
            if ($waitingFor === 'stage_title') {
                $this->handleStageTitleInput($chatId, $text);
            } elseif ($waitingFor === 'stage_points') {
                $this->handleStagePointsInput($chatId, $text);
            }
            return;
        }
        
        // Handle reward creation
        if ($mode === 'reward_creation') {
            $this->handleRewardTextMessage($chatId, $text);
            return;
        }
        
        $this->sendMessage($chatId, "لطفاً از منوی ادمین استفاده کنید.");
    }

    /**
     * Handle photo message
     */
    public function handlePhotoMessage($chatId, $message): void
    {
        $state = $this->getAdminState($chatId);
    
        // If no state, debug and return
        if (!$state) {
            \Log::info('Photo received but no state', [
                'chat_id' => $chatId
            ]);
            $this->debugPhotoStructure($chatId, $message);
            return;
        }

        $mode = $state['mode'] ?? '';
        $waitingFor = $state['waiting_for'] ?? '';
        
        // Handle stage photo upload
        if ($mode === 'stage_photo_upload' && str_starts_with($waitingFor, 'photo_')) {
            $this->handleStagePhotoUpload($chatId, $message);
            return;
        }
        
        // Handle reward creation photos
        if ($mode === 'reward_creation' && $waitingFor === 'image') {
            $this->handleRewardPhotoMessage($chatId, $message);
            return;
        }
    
        // Handle test modes
        if (isset($state['test_mode'])) {
            if ($state['test_mode'] === 'save_photo') {
                $this->testSavePhoto($chatId, $message);
                return;
            } elseif ($state['test_mode'] === 'download') {
                $this->testFileDownload($chatId, $message);
                return;
            }
        }
    
        \Log::info('Photo received but not in correct state', [
            'state' => $state,
            'mode' => $mode,
            'waiting_for' => $waitingFor
        ]);
    
        $this->debugPhotoStructure($chatId, $message);
    }

    /**
     * Debug photo structure
     */
    public function debugPhotoStructure($chatId, $message): void
    {
        $messageArray = $message->toArray();
        
        $text = "🔍 دیباگ ساختار عکس:\n\n";
        $text .= "📋 پیام کامل:\n";
        $text .= json_encode($messageArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $this->sendMessage($chatId, $text);
    }

    /**
     * Test save photo
     */
    public function testSavePhoto($chatId, $message): void
    {
        try {
            $messageArray = $message->toArray();
            
            if (isset($messageArray['photo']) && is_array($messageArray['photo'])) {
                $largestPhoto = end($messageArray['photo']);
                $fileId = $largestPhoto['file_id'];
                
                $fileResponse = $this->telegram->getFile(['file_id' => $fileId]);
                $filePath = $fileResponse['file_path'];
                $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";
                
                $imageContent = file_get_contents($imageUrl);
                $fileName = 'test_' . time() . '.jpg';
                $saved = Storage::disk('public')->put('test/' . $fileName, $imageContent);
                
                if ($saved) {
                    $this->sendMessage($chatId, "✅ عکس تست با موفقیت ذخیره شد: {$fileName}");
                } else {
                    $this->sendMessage($chatId, "❌ خطا در ذخیره عکس تست");
                }
            } else {
                $this->sendMessage($chatId, "❌ عکس یافت نشد");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ خطا در تست ذخیره: " . $e->getMessage());
        }
    }

    /**
     * Test file download
     */
    public function testFileDownload($chatId, $message): void
    {
        try {
            $messageArray = $message->toArray();
            
            if (isset($messageArray['photo']) && is_array($messageArray['photo'])) {
                $largestPhoto = end($messageArray['photo']);
                $fileId = $largestPhoto['file_id'];
                
                $fileResponse = $this->telegram->getFile(['file_id' => $fileId]);
                $filePath = $fileResponse['file_path'];
                $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";
                
                $this->sendMessage($chatId, "🔗 URL دانلود: {$imageUrl}");
            } else {
                $this->sendMessage($chatId, "❌ عکس یافت نشد");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ خطا در تست دانلود: " . $e->getMessage());
        }
    }

    /**
     * Show stages list (updated for new system)
     */
    public function showStagesList($chatId): void
    {
        $stages = Stage::with(['photos'])->orderBy('stage_number')->get();
        
        if ($stages->isEmpty()) {
            $text = config('telegram.messages.no_stages_found');
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_story_settings'],
                ]
            ];
        } else {
            $text = "📋 لیست مراحل\n\n";
            $keyboard = [];
            
            foreach ($stages as $stage) {
                $status = $stage->is_completed ? "✅" : "⏳";
                $photosCount = $stage->photos->count();
                
                $text .= "{$status} مرحله {$stage->stage_number} - {$stage->points} امتیاز\n";
                
                if ($photosCount > 0) {
                    $text .= "   📸 {$photosCount} عکس\n";
                } else {
                    $text .= "   ⚠️ بدون محتوا\n";
                }
                
                $text .= "\n";
                
                $keyboard[] = [
                    ['text' => "مرحله {$stage->stage_number}", 'callback_data' => "view_stage_{$stage->id}"]
                ];
            }
            
            $keyboard[] = [
                ['text' => 'بازگشت', 'callback_data' => 'admin_story_settings'],
            ];
        }

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Show stage details (updated for new system)
     */
    public function showStageDetails($chatId, $stageId): void
    {
        $stage = Stage::with(['photos.userProgress'])->find($stageId);
        
        if (!$stage) {
            $this->sendErrorMessage($chatId, 'مرحله یافت نشد.');
            return;
        }

        $text = "📖 جزئیات مرحله {$stage->stage_number}\n\n";
        $text .= "📊 امتیاز: {$stage->points}\n";
        $text .= "📈 وضعیت: " . ($stage->is_completed ? "✅ تکمیل شده" : "⏳ در انتظار") . "\n\n";

        // Show photos
        if ($stage->photos->count() > 0) {
            $text .= "📸 عکس‌های مرحله:\n\n";
            foreach ($stage->photos as $photo) {
                $text .= "🔹 عکس {$photo->photo_order}\n";
                
                // Get users who unlocked this photo
                $unlockedUsers = \App\Models\UserUnlockedPhoto::getUsersForPhoto($photo->id);
                
                if ($unlockedUsers->count() > 0) {
                    $text .= "   👥 کاربران بازکننده:\n";
                    foreach ($unlockedUsers as $unlock) {
                        $userName = $unlock->user->telegram_first_name ?? 'کاربر ' . $unlock->user_id;
                        $unlockedAt = $unlock->unlocked_at->format('Y/m/d H:i');
                        $text .= "      • {$userName} (ID: {$unlock->user_id}) - {$unlockedAt}\n";
                    }
                } else {
                    $text .= "   🔒 هیچ کاربری باز نکرده\n";
                }
                $text .= "\n";
            }
        } else {
            $text .= "⚠️ هیچ عکسی برای این مرحله وجود ندارد.\n\n";
        }

        $keyboard = [
            [
                ['text' => 'بازگشت به لیست', 'callback_data' => 'admin_list_stages'],
            ]
        ];
        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Start reward creation
     */
    public function startRewardCreation($chatId): void
    {
        $state = [
            'mode' => 'reward_creation',
            'waiting_for' => 'title',
            'reward_data' => []
        ];
        
        $this->setAdminState($chatId, $state);
        $this->sendMessage($chatId, "🎁 شروع ساخت جایزه جدید\n\nلطفاً عنوان جایزه را وارد کنید:");
    }

    /**
     * Handle reward text message
     */
    public function handleRewardTextMessage($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        
        if (!$state || $state['mode'] !== 'reward_creation') {
            return;
        }
        
        $waitingFor = $state['waiting_for'] ?? '';
        
        switch ($waitingFor) {
            case 'title':
                $state['reward_data']['title'] = $text;
                $state['waiting_for'] = 'description';
                $this->setAdminState($chatId, $state);
                $this->sendMessage($chatId, "✅ عنوان ذخیره شد!\n\nلطفاً توضیحات جایزه را وارد کنید:");
                break;
                
            case 'description':
                $state['reward_data']['description'] = $text;
                $state['waiting_for'] = 'score';
                $this->setAdminState($chatId, $state);
                $this->sendMessage($chatId, "✅ توضیحات ذخیره شد!\n\nلطفاً امتیاز مورد نیاز را وارد کنید:");
                break;
                
            case 'score':
                $score = (int) $text;
                if ($score <= 0) {
                    $this->sendMessage($chatId, "❌ لطفاً یک عدد مثبت وارد کنید:");
                    return;
                }
                
                $state['reward_data']['score'] = $score;
                $state['waiting_for'] = 'image';
                $this->setAdminState($chatId, $state);
                $this->sendMessage($chatId, "✅ امتیاز ذخیره شد!\n\nلطفاً عکس جایزه را ارسال کنید:");
                break;
        }
    }

    /**
     * Handle reward photo message
     */
    public function handleRewardPhotoMessage($chatId, $message): void
    {
        $state = $this->getAdminState($chatId);
        
        if (!$state || $state['mode'] !== 'reward_creation') {
            return;
        }
        
        try {
            $messageArray = $message->toArray();
            
            $fileId = null;
            if (isset($messageArray['photo']) && is_array($messageArray['photo'])) {
                $largestPhoto = end($messageArray['photo']);
                $fileId = $largestPhoto['file_id'];
            }
            
            if (!$fileId) {
                throw new \Exception('شناسه فایل عکس یافت نشد.');
            }
            
            $fileResponse = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $fileResponse['file_path'];
            $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";
            
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                throw new \Exception('خطا در دانلود عکس از تلگرام.');
            }
            
            $fileName = 'reward_' . time() . '.jpg';
            $imagePath = 'rewards/' . $fileName;
            $saved = Storage::disk('public')->put($imagePath, $imageContent);
            
            if (!$saved) {
                throw new \Exception('خطا در ذخیره عکس در سرور.');
            }
            
            // Create reward
            $rewardData = $state['reward_data'];
            $reward = Reward::create([
                'title' => $rewardData['title'],
                'description' => $rewardData['description'],
                'score' => $rewardData['score'],
                'image_path' => $imagePath,
                'is_active' => true
            ]);
            
            $this->clearAdminState($chatId);
            
            $text = "🎉 جایزه جدید با موفقیت ایجاد شد!\n\n";
            $text .= "📊 اطلاعات جایزه:\n";
            $text .= "عنوان: {$reward->title}\n";
            $text .= "امتیاز: {$reward->score}\n";
            $text .= "وضعیت: فعال";
            
            $keyboard = [
                [
                    ['text' => '🎁 جایزه جدید', 'callback_data' => 'admin_create_reward'],
                    ['text' => '🏠 منوی اصلی', 'callback_data' => 'admin_main_menu'],
                ]
            ];
            
            $this->sendMessage($chatId, $text, $keyboard);
            
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, '❌ خطا در پردازش عکس: ' . $e->getMessage());
        }
    }

    /**
     * Show rewards list
     */
    public function showRewardsList($chatId): void
    {
        $rewards = Reward::orderBy('score', 'desc')->get();
        
        if ($rewards->isEmpty()) {
            $text = "🎁 لیست جایزه‌ها\n\nهیچ جایزه‌ای یافت نشد.";
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_reward_settings'],
                ]
            ];
        } else {
            $text = "🎁 لیست جایزه‌ها\n\n";
            
            foreach ($rewards as $reward) {
                $status = $reward->is_active ? "✅ فعال" : "❌ غیرفعال";
                $text .= "🎁 {$reward->title} - {$reward->score} امتیاز - {$status}\n";
                if ($reward->description) {
                    $text .= "   📝 {$reward->description}\n";
                }
                $text .= "\n";
            }
            
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_reward_settings'],
                ]
            ];
        }

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Toggle reward status
     */
    public function toggleRewardStatus($chatId, $rewardId): void
    {
        $reward = Reward::find($rewardId);
        
        if (!$reward) {
            $this->sendErrorMessage($chatId, 'جایزه یافت نشد.');
            return;
        }

        $reward->is_active = !$reward->is_active;
        $reward->save();

        $status = $reward->is_active ? "✅ فعال" : "❌ غیرفعال";
        $text = "🎁 وضعیت جایزه '{$reward->title}' تغییر کرد به: {$status}";

        $keyboard = [
            [
                ['text' => 'بازگشت به لیست', 'callback_data' => 'admin_list_rewards'],
            ]
        ];
        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Start new stage photo upload process
     */
    public function startStagePhotoUpload($chatId): void
    {
        $nextStageNumber = Stage::getHighestStageNumber() + 1;
        
        $state = [
            'mode' => 'stage_photo_upload',
            'waiting_for' => 'stage_title',
            'stage_number' => $nextStageNumber,
            'current_photo' => 1,
            'photos_uploaded' => 0,
            'stage_data' => [
                'title' => '',
                'points' => 0,
                'photos' => []
            ]
        ];
        
        $this->setAdminState($chatId, $state);
        
        $text = "📸 شروع آپلود عکس‌های مرحله جدید\n\n";
        $text .= "شماره مرحله: {$nextStageNumber}\n";
        $text .= "لطفاً عنوان مرحله را وارد کنید:";
        
        $this->sendMessage($chatId, $text);
    }

    /**
     * Handle stage title input
     */
    public function handleStageTitleInput($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        if (!$state || $state['mode'] !== 'stage_photo_upload' || $state['waiting_for'] !== 'stage_title') {
            return;
        }
        
        $state['stage_data']['title'] = $text;
        $state['waiting_for'] = 'stage_points';
        
        $this->setAdminState($chatId, $state);
        
        $this->sendMessage($chatId, "✅ عنوان مرحله ذخیره شد!\n\nلطفاً امتیاز این مرحله را وارد کنید:");
    }

    /**
     * Handle stage points input
     */
    public function handleStagePointsInput($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        if (!$state || $state['mode'] !== 'stage_photo_upload' || $state['waiting_for'] !== 'stage_points') {
            return;
        }
        
        $points = (int) $text;
        if ($points <= 0) {
            $this->sendMessage($chatId, "❌ لطفاً یک عدد مثبت وارد کنید:");
            return;
        }
        
        $state['stage_data']['points'] = $points;
        $state['waiting_for'] = 'photo_1';
        
        $this->setAdminState($chatId, $state);
        
        $text = "✅ امتیاز مرحله ذخیره شد!\n\n";
        $text .= "حالا باید ۶ عکس برای این مرحله آپلود کنید.\n";
        $text .= "عکس شماره ۱ از ۶ را ارسال کنید:";
        
        $this->sendMessage($chatId, $text);
    }

    /**
     * Handle stage photo upload
     */
    public function handleStagePhotoUpload($chatId, $message): void
    {
        $state = $this->getAdminState($chatId);
        if (!$state || $state['mode'] !== 'stage_photo_upload') {
            return;
        }
        
        try {
            // Convert message to array for better debugging
            $messageArray = $message->toArray();
            \Log::info('Stage photo upload - Full message structure', ['message' => $messageArray]);

            $fileId = null;
            $fileType = null;

            // Check for photo in message
            if (isset($messageArray['photo']) && is_array($messageArray['photo'])) {
                $largestPhoto = end($messageArray['photo']);
                if (isset($largestPhoto['file_id'])) {
                    $fileId = $largestPhoto['file_id'];
                    $fileType = 'photo';
                }
            }
            // Check for document
            elseif (isset($messageArray['document'])) {
                $document = $messageArray['document'];
                if ($this->isImageDocument($document)) {
                    $fileId = $document['file_id'];
                    $fileType = 'document';
                } else {
                    throw new \Exception('لطفاً یک عکس معتبر (JPG/PNG) ارسال کنید.');
                }
            }

            if (!$fileId) {
                throw new \Exception('شناسه فایل عکس یافت نشد.');
            }

            // Get file info from Telegram
            $fileResponse = $this->telegram->getFile(['file_id' => $fileId]);
            if (!isset($fileResponse['file_path'])) {
                throw new \Exception('مسیر فایل از API تلگرام دریافت نشد.');
            }

            $filePath = $fileResponse['file_path'];
            $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";

            // Download image content
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                throw new \Exception('خطا در دانلود عکس از تلگرام.');
            }

            // Validate image content
            if (!@imagecreatefromstring($imageContent)) {
                throw new \Exception('فایل ارسالی یک تصویر معتبر نیست.');
            }

            // Process photo and create blurred version
            $photoData = PhotoBlurService::processUploadedPhoto(
                $imageContent, 
                $state['stage_number'], 
                $state['current_photo']
            );

            // Save photo data to state
            $state['stage_data']['photos'][] = [
                'photo_order' => $state['current_photo'],
                'original_path' => $photoData['original_path'],
                'blurred_path' => $photoData['blurred_path']
            ];

            $state['photos_uploaded']++;
            $state['current_photo']++;

            if ($state['photos_uploaded'] < 6) {
                $state['waiting_for'] = 'photo_' . $state['current_photo'];
                $this->setAdminState($chatId, $state);
                
                $text = "✅ عکس شماره {$state['photos_uploaded']} از ۶ ذخیره شد!\n\n";
                $text .= "عکس شماره {$state['current_photo']} از ۶ را ارسال کنید:";
                
                $this->sendMessage($chatId, $text);
            } else {
                // All photos uploaded, create stage and photos
                $this->createStageWithPhotos($chatId, $state);
            }

        } catch (\Exception $e) {
            \Log::error('Stage photo upload error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendErrorMessage($chatId, '❌ خطا در آپلود عکس: ' . $e->getMessage());
        }
    }

    /**
     * Create stage with all photos
     */
    private function createStageWithPhotos($chatId, $state): void
    {
        try {
            // Create stage
            $stage = Stage::create([
                'stage_number' => $state['stage_number'],
                'points' => $state['stage_data']['points'],
                'is_completed' => false
            ]);

            // Create stage photos
            foreach ($state['stage_data']['photos'] as $photoData) {
                StagePhoto::create([
                    'stage_id' => $stage->id,
                    'image_path' => $photoData['original_path'],
                    'blurred_image_path' => $photoData['blurred_path'],
                    'photo_order' => $photoData['photo_order'],
                    'is_unlocked' => false
                ]);
            }

            // Clear state
            $this->clearAdminState($chatId);

            $text = "🎉 مرحله جدید با موفقیت ایجاد شد!\n\n";
            $text .= "📊 اطلاعات مرحله:\n";
            $text .= "شماره: {$stage->stage_number}\n";
            $text .= "عنوان: {$state['stage_data']['title']}\n";
            $text .= "امتیاز: {$stage->points}\n";
            $text .= "تعداد عکس‌ها: ۶\n\n";
            $text .= "✅ همه عکس‌ها ذخیره شدند.";

            $keyboard = [
                [
                    ['text' => '📸 مرحله جدید', 'callback_data' => 'admin_start_stage_photo_upload'],
                    ['text' => '🏠 منوی اصلی', 'callback_data' => 'admin_main_menu'],
                ]
            ];

            $this->sendMessage($chatId, $text, $keyboard);

        } catch (\Exception $e) {
            \Log::error('Error creating stage with photos', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);

            $this->sendErrorMessage($chatId, '❌ خطا در ایجاد مرحله: ' . $e->getMessage());
        }
    }

    /**
     * Check if document is an image
     */
    private function isImageDocument($document): bool
    {
        if (!is_array($document)) {
            return false;
        }
    
        $validMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
        // Check mime_type
        if (isset($document['mime_type']) && in_array(strtolower($document['mime_type']), $validMimeTypes)) {
            return true;
        }
    
        // Check file_name extension
        if (isset($document['file_name'])) {
            $extension = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
            return in_array($extension, $validExtensions);
        }
    
        return false;
    }

    /**
     * Send voice settings menu
     */
    public function sendVoiceSettingsMenu($chatId): void
    {
        $text = "🎤 تنظیمات ویس‌ها\n\nگزینه مورد نظر را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.voice_settings'));
    }

    /**
     * Show voice stages list
     */
    public function showVoiceStagesList($chatId): void
    {
        $stages = Stage::withCount(['photos', 'voiceRecordings'])->orderBy('stage_number')->get();
        
        if ($stages->isEmpty()) {
            $text = "📋 لیست مراحل ویس‌ها\n\nهیچ مرحله‌ای یافت نشد.";
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_voice_settings'],
                ]
            ];
        } else {
            $text = "📋 لیست مراحل ویس‌ها\n\n";
            $keyboard = [];
            
            foreach ($stages as $stage) {
                $voiceCount = $stage->voice_recordings_count;
                $status = $voiceCount > 0 ? "🎤 {$voiceCount} ویس" : "🔇 بدون ویس";
                
                $text .= "📖 مرحله {$stage->stage_number}\n";
                $text .= "   📸 {$stage->photos_count} عکس\n";
                $text .= "   {$status}\n\n";
                
                $keyboard[] = [
                    ['text' => "مرحله {$stage->stage_number}", 'callback_data' => "view_voice_stage_{$stage->id}"]
                ];
            }
            
            $keyboard[] = [
                ['text' => 'بازگشت', 'callback_data' => 'admin_voice_settings'],
            ];
        }

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Show voice stage users
     */
    public function showVoiceStageUsers($chatId, $stageId): void
    {
        $stage = Stage::find($stageId);
        if (!$stage) {
            $this->sendErrorMessage($chatId, 'مرحله یافت نشد.');
            return;
        }

        // Get users who have voice recordings for this stage
        $users = \App\Models\User::whereHas('voiceRecordings', function($query) use ($stageId) {
            $query->whereHas('stagePhoto', function($q) use ($stageId) {
                $q->where('stage_id', $stageId);
            });
        })->with(['voiceRecordings' => function($query) use ($stageId) {
            $query->whereHas('stagePhoto', function($q) use ($stageId) {
                $q->where('stage_id', $stageId);
            });
        }])->get();

        if ($users->isEmpty()) {
            $text = "👥 کاربران مرحله {$stage->stage_number}\n\nهیچ کاربری ویس ضبط نکرده است.";
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => 'admin_voice_stages'],
                ]
            ];
        } else {
            $text = "👥 کاربران مرحله {$stage->stage_number}\n\n";
            $keyboard = [];
            
            foreach ($users as $user) {
                $recordingCount = $user->voiceRecordings->count();
                $text .= "👤 {$user->telegram_first_name}\n";
                $text .= "   🎤 {$recordingCount} ویس ضبط شده\n\n";
                
                $keyboard[] = [
                    ['text' => $user->telegram_first_name, 'callback_data' => "view_user_recordings_{$stageId}_{$user->id}"]
                ];
            }
            
            $keyboard[] = [
                ['text' => 'بازگشت', 'callback_data' => 'admin_voice_stages'],
            ];
        }

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Show user recordings
     */
    public function showUserRecordings($chatId, $stageId, $userId): void
    {
        $user = \App\Models\User::find($userId);
        $stage = Stage::find($stageId);
        
        if (!$user || !$stage) {
            $this->sendErrorMessage($chatId, 'کاربر یا مرحله یافت نشد.');
            return;
        }

        $recordings = \App\Models\UserVoiceRecording::where('user_id', $userId)
            ->whereHas('stagePhoto', function($query) use ($stageId) {
                $query->where('stage_id', $stageId);
            })
            ->with('stagePhoto')
            ->orderBy('created_at')
            ->get();

        if ($recordings->isEmpty()) {
            $text = "🎤 ویس‌های {$user->telegram_first_name} در مرحله {$stage->stage_number}\n\nهیچ ویسی یافت نشد.";
            $keyboard = [
                [
                    ['text' => 'بازگشت', 'callback_data' => "view_voice_stage_{$stageId}"],
                ]
            ];
        } else {
            $text = "🎤 ویس‌های {$user->telegram_first_name} در مرحله {$stage->stage_number}\n\n";
            $text .= "📊 تعداد ویس‌ها: {$recordings->count()}\n\n";
            
            foreach ($recordings as $recording) {
                $photoOrder = $recording->stagePhoto->photo_order;
                $createdAt = $recording->created_at->format('Y/m/d H:i');
                $text .= "🎵 عکس {$photoOrder} - {$createdAt}\n";
            }
            
            $keyboard = [
                [
                    ['text' => '🎵 پخش ویس کامل', 'callback_data' => "play_combined_voice_{$stageId}_{$userId}"],
                ],
                [
                    ['text' => 'بازگشت', 'callback_data' => "view_voice_stage_{$stageId}"],
                ]
            ];
        }

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Send combined voice recording
     */
    public function sendCombinedVoiceRecording($chatId, $stageId, $userId): void
    {
        try {
            // Make API call to get combined voice recording
            $response = \Http::get(config('app.url') . "/api/admin/stages/{$stageId}/users/{$userId}/combined");
            
            if ($response->successful()) {
                $data = $response->json();
                $audioUrl = $data['combined_audio_url'] ?? null;
                
                if ($audioUrl) {
                    // Send audio file to Telegram
                    $this->telegram->sendAudio([
                        'chat_id' => $chatId,
                        'audio' => $audioUrl,
                        'caption' => "🎵 ویس کامل کاربر در این مرحله"
                    ]);
                } else {
                    $this->sendErrorMessage($chatId, 'خطا در دریافت ویس ترکیبی.');
                }
            } else {
                $this->sendErrorMessage($chatId, 'خطا در درخواست ویس ترکیبی.');
            }
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در ارسال ویس: ' . $e->getMessage());
        }
    }

    /**
     * Send error message
     */
    private function sendErrorMessage($chatId, $message): void
    {
        $this->sendMessage($chatId, $message);
    }

    /**
     * Send success message
     */
    private function sendSuccessMessage($chatId, $message): void
    {
        $this->sendMessage($chatId, $message);
    }
}