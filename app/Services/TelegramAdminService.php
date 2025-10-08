<?php

namespace App\Services;

use App\Models\Code;
use App\Models\Stage;
use App\Models\Story;
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
     * Show current state for debugging
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
        
        if (isset($state['current_story'])) {
            $text .= "داستان فعلی: {$state['current_story']}\n";
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
        $dbStates = AdminState::where('chat_id', $chatId)->get();
        
        if ($dbStates->isEmpty()) {
            $this->sendMessage($chatId, "🔍 هیچ رکوردی در دیتابیس برای این چت یافت نشد.");
            return;
        }
        
        $text = "🔍 وضعیت دیتابیس برای چت {$chatId}:\n\n";
        
        foreach ($dbStates as $index => $dbState) {
            $text .= "رکورد " . ($index + 1) . ":\n";
            $text .= "ID: {$dbState->id}\n";
            $text .= "تاریخ ایجاد: {$dbState->created_at}\n";
            $text .= "تاریخ انقضا: {$dbState->expires_at}\n";
            $text .= "داده‌ها: " . json_encode($dbState->state_data, JSON_UNESCAPED_UNICODE) . "\n\n";
        }
        
        $this->sendMessage($chatId, $text);
    }

    /**
     * Debug photo message structure
     */
    public function debugPhotoStructure($chatId, $message): void
    {
        try {
            $text = "🔍 ساختار پیام عکس:\n\n";
            
            // Get message class
            $text .= "نوع پیام: " . get_class($message) . "\n\n";
            
            // Get message data
            $messageData = $message->toArray();
            $text .= "داده‌های پیام:\n";
            $text .= json_encode($messageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            // Get photos
            $photos = $message->getPhoto();
            $text .= "عکس‌ها:\n";
            $text .= json_encode($photos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            // Get largest photo
            if (!empty($photos)) {
                $largestPhoto = end($photos);
                $text .= "بزرگترین عکس:\n";
                $text .= json_encode($largestPhoto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                
                if (isset($largestPhoto['file_id'])) {
                    $text .= "✅ file_id یافت شد: " . $largestPhoto['file_id'] . "\n";
                } else {
                    $text .= "❌ file_id یافت نشد\n";
                }
            }
            
            $text .= "\n🔧 برای تست، روی یکی از دکمه‌های زیر کلیک کنید:";
            
            $keyboard = [
                [
                    ['text' => '📸 تست ذخیره عکس', 'callback_data' => 'admin_test_save_photo'],
                    ['text' => '🔗 تست دانلود فایل', 'callback_data' => 'admin_test_download'],
                ]
            ];
            
            $this->sendMessage($chatId, $text, $keyboard);
            
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ خطا در بررسی ساختار عکس: " . $e->getMessage());
        }
    }

    /**
     * Test save photo without file_id
     */
    public function testSavePhoto($chatId, $message): void
    {
        try {
            $text = "📸 تست ذخیره عکس بدون file_id...\n\n";
            
            // Try to save a dummy image
            $dummyImageContent = file_get_contents('https://via.placeholder.com/300x200/FF0000/FFFFFF?text=Test+Image');
            
            if ($dummyImageContent === false) {
                throw new \Exception('خطا در دانلود عکس تست');
            }
            
            $fileName = 'test_' . time() . '.jpg';
            $imagePath = 'stories/' . $fileName;
            
            // Try to save
            $result = Storage::disk('public')->put($imagePath, $dummyImageContent);
            
            if ($result) {
                $text .= "✅ عکس تست با موفقیت ذخیره شد!\n";
                $text .= "مسیر: {$imagePath}\n";
                $text .= "حجم: " . strlen($dummyImageContent) . " بایت\n\n";
                $text .= "مشکل از ذخیره عکس نیست، احتمالاً از دریافت file_id است.";
            } else {
                $text .= "❌ خطا در ذخیره عکس تست";
            }
            
            $this->sendMessage($chatId, $text);
            
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ خطا در تست ذخیره عکس: " . $e->getMessage());
        }
    }

    /**
     * Test file download URL
     */
    public function testFileDownload($chatId, $message): void
    {
        try {
            $text = "🔗 تست URL دانلود فایل...\n\n";
            
            // Get file_id from message
            $fileId = null;
            $messageArray = $message->toArray();
            
            if (isset($messageArray['photo']) && is_array($messageArray['photo'])) {
                $photos = $messageArray['photo'];
                if (!empty($photos)) {
                    $largestPhoto = end($photos);
                    if (isset($largestPhoto['file_id'])) {
                        $fileId = $largestPhoto['file_id'];
                    }
                }
            }
            
            if (!$fileId) {
                $text .= "❌ file_id یافت نشد\n";
                $this->sendMessage($chatId, $text);
                return;
            }
            
            $text .= "✅ file_id یافت شد: {$fileId}\n\n";
            
            // Try to get file info
            try {
                $file = $this->telegram->getFile(['file_id' => $fileId]);
                $text .= "📋 اطلاعات فایل:\n";
                $text .= json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                
                if (isset($file['file_path'])) {
                    $filePath = $file['file_path'];
                    $text .= "✅ file_path یافت شد: {$filePath}\n\n";
                    
                    // Test URL
                    $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";
                    $text .= "🔗 URL دانلود: {$imageUrl}\n\n";
                    
                    // Test download
                    $imageContent = file_get_contents($imageUrl);
                    if ($imageContent !== false) {
                        $text .= "✅ دانلود موفق! حجم: " . strlen($imageContent) . " بایت\n";
                    } else {
                        $text .= "❌ دانلود ناموفق\n";
                    }
                } else {
                    $text .= "❌ file_path یافت نشد\n";
                }
                
            } catch (\Exception $e) {
                $text .= "❌ خطا در دریافت اطلاعات فایل: " . $e->getMessage() . "\n";
            }
            
            $this->sendMessage($chatId, $text);
            
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "❌ خطا در تست دانلود: " . $e->getMessage());
        }
    }

    /**
     * Save photo without file_id (fallback method)
     */
    private function savePhotoWithoutFileId($chatId, $message): void
    {
        try {
            $state = $this->getAdminState($chatId);
            
            // Create a placeholder image
            $imageContent = file_get_contents('https://via.placeholder.com/400x300/CCCCCC/666666?text=Photo+Not+Available');
            
            if ($imageContent === false) {
                throw new \Exception('خطا در ایجاد عکس جایگزین');
            }
            
            $fileName = 'story_' . time() . '_' . ($state['current_story'] ?? 1) . '_placeholder.jpg';
            $imagePath = 'stories/' . $fileName;
            
            // Try to save
            $result = Storage::disk('public')->put($imagePath, $imageContent);
            
            if ($result) {
                $storyData = $state['current_story_data'] ?? [];
                $storyData['image_path'] = $imagePath;
                $storyData['is_placeholder'] = true;
                
                $this->updateAdminState($chatId, 'current_story_data', $storyData);
                $this->updateAdminState($chatId, 'waiting_for', 'correct_choice');
                
                $this->sendMessage($chatId, "⚠️ عکس جایگزین ذخیره شد. ادامه می‌دهیم...");
                $this->askForCorrectChoice($chatId);
            } else {
                throw new \Exception('خطا در ذخیره عکس جایگزین');
            }
            
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در ذخیره عکس جایگزین: ' . $e->getMessage());
        }
    }

    /**
     * Reset story creation state
     */
    public function resetStoryCreation($chatId): void
    {
        $nextStageNumber = Stage::getHighestStageNumber() + 1;
        
        $stateData = [
            'mode' => 'story_creation',
            'stage_number' => $nextStageNumber,
            'current_story' => 1,
            'stories' => [],
            'points' => null,
            'current_story_data' => [],
            'waiting_for' => 'points'
        ];
        
        $this->setAdminState($chatId, $stateData);
        
        \Log::info("Story creation state reset", [
            'chat_id' => $chatId,
            'stage_number' => $nextStageNumber,
            'state_data' => $stateData
        ]);
        
        $text = "📚 ساخت داستان جدید\n\n";
        $text .= "شما در حال ساخت مرحله {$nextStageNumber} هستید.\n\n";
        $text .= "برای شروع، ابتدا امتیاز این مرحله را وارد کنید:";
        
        $keyboard = [
            [
                ['text' => 'لغو', 'callback_data' => 'admin_story_settings'],
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
        $text = "📚 تنظیمات داستان‌ها\n\nگزینه مورد نظر را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.story_settings'));
    }

    /**
     * Send reward settings menu
     */
    public function sendRewardSettingsMenu($chatId): void
    {
        $text = "🎁 مدیریت جایزه‌ها\n\nگزینه مورد نظر را انتخاب کنید:";
        $this->sendMessage($chatId, $text, config('telegram.keyboards.reward_settings'));
    }

    /**
     * Ask for code count
     */
    public function askForCodeCount($chatId): void
    {
        $text = "🔧 ایجاد کد های جدید\n\nتعداد کدهایی که می‌خواهید ایجاد شود را انتخاب کنید:";
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
        try {
            $codes = Code::with('user')->orderBy('created_at', 'desc')->get();
            
            if ($codes->isEmpty()) {
                $text = config('telegram.messages.no_codes_found');
            } else {
                $text = "📋 لیست کدها\n\n";
                foreach ($codes as $code) {
                    $status = $code->is_active ? "✅ فعال" : "❌ غیرفعال";
                    $usedBy = $code->user ? "👤 {$code->user->name}" : "🔓 استفاده نشده";
                    $text .= "🔑 {$code->code} - {$status} - {$usedBy}\n";
                }

                $text .= "\nبرای دریافت فایل CSV، روی دکمه زیر بزنید.";
            }

            $keyboard = [
                [
                    ['text' => 'بازگشت به تنظیمات کدها', 'callback_data' => 'admin_code_settings'],
                ]
            ];
            // اضافه کردن دکمه دریافت اکسل در صورت وجود کد
            if (!empty($codes) && $codes->count() > 0) {
                $keyboard[] = [
                    ['text' => '📤 ارسال فایل CSV', 'callback_data' => 'admin_export_codes_csv'],
                ];
            }

            $this->sendMessage($chatId, $text, $keyboard);
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در نمایش لیست کدها یا تولید فایل اکسل: ' . $e->getMessage());
        }
    }

    /**
     * Generate Excel file from codes and send it to the admin chat as a document
     */
    public function exportCodesCsvAndSend($chatId): void
    {
        try {
            $codes = Code::query()->limit(1)->get();
            if ($codes->isEmpty()) {
                $this->sendMessage($chatId, 'هیچ کدی برای اکسپورت وجود ندارد.');
                return;
            }
            // ساخت CSV با استفاده از تابع موجود در CodeController
            $fileName = 'codes_' . now()->format('Ymd_His') . '.csv';
            $relativePath = 'exports/' . $fileName;
            $fullPath = storage_path('app/public/' . $relativePath);
            CodeController::writeCodesCsvToPath($fullPath);

            // ارسال فایل CSV به تلگرام به عنوان document
            $this->telegram->sendDocument([
                'chat_id' => $chatId,
                'document' => InputFile::create($fullPath, $fileName),
                'caption' => 'فایل CSV کدها',
            ]);

            $this->sendSuccessMessage($chatId, 'فایل CSV ارسال شد.');

        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در اکسپورت و ارسال فایل CSV: ' . $e->getMessage());
        }
    }
    /**
     * Start story creation
     */
    public function startStoryCreation($chatId): void
    {
        $nextStageNumber = Stage::getHighestStageNumber() + 1;
        
        if ($nextStageNumber > 170) {
            $this->sendMessage($chatId, config('telegram.messages.all_stages_completed'));
            return;
        }

        $stateData = [
            'mode' => 'story_creation',
            'stage_number' => $nextStageNumber,
            'current_story' => 1,
            'stories' => [],
            'points' => null,
            'current_story_data' => [],
            'waiting_for' => 'points'
        ];
        
        $this->setAdminState($chatId, $stateData);
        
        // Debug logging
        \Log::info("Story creation started", [
            'chat_id' => $chatId,
            'stage_number' => $nextStageNumber,
            'state_data' => $stateData
        ]);

        $text = "📚 ساخت داستان جدید\n\n";
        $text .= "شما در حال ساخت مرحله {$nextStageNumber} هستید.\n\n";
        $text .= "برای شروع، ابتدا امتیاز این مرحله را وارد کنید:";
        
        $keyboard = [
            [
                ['text' => 'لغو', 'callback_data' => 'admin_story_settings'],
            ]
        ];
        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Ask for story details
     */
    public function askForStoryDetails($chatId, $storyNumber): void
    {
        $text = "📖 داستان {$storyNumber}\n\n";
        $text .= "لطفاً اطلاعات داستان {$storyNumber} را وارد کنید:\n\n";
        $text .= "1️⃣ عنوان داستان\n";
        $text .= "2️⃣ متن داستان\n";
        $text .= "3️⃣ عکس داستان\n";
        $text .= "4️⃣ انتخاب درست/اشتباه بودن\n\n";
        $text .= "ابتدا عنوان داستان را ارسال کنید:";

        $this->sendMessage($chatId, $text);
        $this->updateAdminState($chatId, 'waiting_for', 'title');
    }

    /**
     * Update admin state
     */
    private function updateAdminState($chatId, string $key, $value): void
    {
        $state = $this->getAdminState($chatId);
        if ($state) {
            $state[$key] = $value;
            $this->setAdminState($chatId, $state);
        }
    }

    /**
     * Handle text message during story creation or reward creation
     */
    public function handleStoryTextMessage($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        
        // Debug logging
        \Log::info("Text message received", [
            'chat_id' => $chatId,
            'text' => $text,
            'state' => $state,
            'has_state' => !empty($state),
            'mode' => $state['mode'] ?? 'no_mode',
            'waiting_for' => $state['waiting_for'] ?? 'no_waiting'
        ]);
        
        if (!$state) {
            $this->sendMessage($chatId, "🔍 پیام دریافت شد اما در هیچ حالتی نیستید.\nمتن: {$text}");
            return;
        }

        $mode = $state['mode'] ?? '';
        $waitingFor = $state['waiting_for'] ?? '';

        switch ($mode) {
            case 'story_creation':
                switch ($waitingFor) {
                    case 'points':
                        $this->handlePointsInput($chatId, $text);
                        break;
                    case 'title':
                        $this->handleTitleInput($chatId, $text);
                        break;
                    case 'description':
                        $this->handleDescriptionInput($chatId, $text);
                        break;
                }
                break;
            case 'reward_creation':
                $this->handleRewardTextMessage($chatId, $text);
                break;
            default:
                $this->sendMessage($chatId, "🔍 پیام دریافت شد اما در حالت مناسبی نیستید.\nمتن: {$text}");
                break;
        }
    }

    /**
     * Handle points input
     */
    private function handlePointsInput($chatId, $text): void
    {
        if (is_numeric($text) && $text > 0) {
            $this->updateAdminState($chatId, 'points', (int) $text);
            $this->updateAdminState($chatId, 'waiting_for', 'title');
            
            $state = $this->getAdminState($chatId);
            $this->sendSuccessMessage($chatId, "امتیاز مرحله {$state['stage_number']} ثبت شد: {$text}");
            $this->askForStoryDetails($chatId, 1);
        } else {
            $this->sendErrorMessage($chatId, 'لطفاً یک عدد مثبت وارد کنید.');
        }
    }

    /**
     * Handle title input
     */
    private function handleTitleInput($chatId, $text): void
    {
        $this->updateAdminState($chatId, 'current_story_data', ['title' => $text]);
        $this->updateAdminState($chatId, 'waiting_for', 'description');
        $this->sendMessage($chatId, '📝 حالا متن داستان را وارد کنید:');
    }

    /**
     * Handle description input
     */
    private function handleDescriptionInput($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        $storyData = $state['current_story_data'] ?? [];
        $storyData['description'] = $text;
        
        $this->updateAdminState($chatId, 'current_story_data', $storyData);
        $this->updateAdminState($chatId, 'waiting_for', 'image');
        $this->sendMessage($chatId, '🖼️ حالا عکس داستان را ارسال کنید:');
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
    
        // Handle story creation photos
        if ($mode === 'story_creation' && $waitingFor === 'image') {
            $this->handleStoryPhotoMessage($chatId, $message);
            return;
        }
        
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
            'chat_id' => $chatId,
            'state' => $state,
            'mode' => $mode,
            'waiting_for' => $waitingFor
        ]);
    
        $this->debugPhotoStructure($chatId, $message);
    }

    /**
     * Handle story photo message (extracted from original handlePhotoMessage)
     */
    private function handleStoryPhotoMessage($chatId, $message): void
    {
        $state = $this->getAdminState($chatId);
        
        try {
            // Convert message to array for better debugging
            $messageArray = $message->toArray();
            \Log::info('Full message structure', ['message' => $messageArray]);
    
            $fileId = null;
            $fileType = null;
    
            // Check for photo in message (new approach)
            if (isset($messageArray['photo']) && is_array($messageArray['photo'])) {
                // Get the highest resolution photo (last in array)
                $largestPhoto = end($messageArray['photo']);
                if (isset($largestPhoto['file_id'])) {
                    $fileId = $largestPhoto['file_id'];
                    $fileType = 'photo';
                    \Log::info('Found photo file_id', ['file_id' => $fileId]);
                }
            }
            // Check for document (compressed image)
            elseif (isset($messageArray['document'])) {
                $document = $messageArray['document'];
                if ($this->isImageDocument($document)) {
                    $fileId = $document['file_id'];
                    $fileType = 'document';
                    \Log::info('Found document file_id', ['file_id' => $fileId]);
                } else {
                    throw new \Exception('لطفاً یک عکس معتبر (JPG/PNG) ارسال کنید. فایل ارسالی از نوع تصویر نیست.');
                }
            }
    
            if (!$fileId) {
                \Log::error('No file_id found in message', ['message' => $messageArray]);
                throw new \Exception('شناسه فایل عکس یافت نشد. لطفاً یک عکس معتبر (JPG/PNG) ارسال کنید.');
            }
    
            // Get file info from Telegram
            $fileResponse = $this->telegram->getFile(['file_id' => $fileId]);
            \Log::info('Telegram getFile response', ['response' => $fileResponse]);
    
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
    
            // Save to storage
            $fileName = 'story_' . time() . '_' . $state['current_story'] . '.jpg';
            $relativePath = 'stories/' . $fileName;
            $baseUrl = 'https://api.daom.ir/storage/'; // URL پایه
            $imagePath = $baseUrl . $relativePath;
            $saved = Storage::disk('public')->put($imagePath, $imageContent);
            if (!$saved) {
                throw new \Exception('خطا در ذخیره عکس در سرور.');
            }
    
            // Update state
            $storyData = $state['current_story_data'] ?? [];
            $storyData['image_path'] = $imagePath;
            $this->updateAdminState($chatId, 'current_story_data', $storyData);
            $this->updateAdminState($chatId, 'waiting_for', 'correct_choice');
    
            $this->askForCorrectChoice($chatId);
    
        } catch (\Exception $e) {
            \Log::error('Photo handling error', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            $this->sendErrorMessage($chatId, '❌ خطا در پردازش عکس: ' . $e->getMessage());
        }
    }
    
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
     * Ask for correct choice
     */
    private function askForCorrectChoice($chatId): void
    {
        $state = $this->getAdminState($chatId);
        $storyNumber = $state['current_story'];
        
        $text = "✅ عکس داستان {$storyNumber} ذخیره شد!\n\n";
        $text .= "حالا انتخاب کنید که آیا این داستان درست است یا اشتباه:";
        
        $this->sendMessage($chatId, $text, config('telegram.keyboards.story_correct'));
    }

    /**
     * Handle story correct choice
     */
    public function handleStoryCorrectChoice($chatId, $isCorrect): void
    {
        $state = $this->getAdminState($chatId);
        $storyNumber = $state['current_story'];
        
        $storyData = $state['current_story_data'] ?? [];
        $storyData['is_correct'] = $isCorrect;
        $storyData['order'] = $storyNumber;
        
        $stories = $state['stories'] ?? [];
        $stories[] = $storyData;
        
        $this->updateAdminState($chatId, 'stories', $stories);
        
        // Clear current_story_data from state
        $state = $this->getAdminState($chatId);
        if ($state && isset($state['current_story_data'])) {
            unset($state['current_story_data']);
            $this->setAdminState($chatId, $state);
        }
        
        $status = $isCorrect ? "✅ درست" : "❌ اشتباه";
        $text = "✅ داستان {$storyNumber} با موفقیت ذخیره شد!\n";
        $text .= "وضعیت: {$status}\n\n";
        
        if ($storyNumber < 3) {
            $text .= "حالا داستان " . ($storyNumber + 1) . " را شروع می‌کنیم...";
            $this->sendMessage($chatId, $text);
            
            $this->updateAdminState($chatId, 'current_story', $storyNumber + 1);
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
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Finalize stage
     */
    public function finalizeStage($chatId): void
    {
        $state = $this->getAdminState($chatId);
        
        try {
            $stage = Stage::create([
                'stage_number' => $state['stage_number'],
                'points' => $state['points'],
                'is_completed' => true
            ]);

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
            $this->sendMessage($chatId, $text, $keyboard);

            $this->clearAdminState($chatId);

        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در ایجاد مرحله: ' . $e->getMessage());
        }
    }

    /**
     * Show stages list
     */
    public function showStagesList($chatId): void
    {
        $stages = Stage::with('stories')->orderBy('stage_number')->get();
        
        if ($stages->isEmpty()) {
            $text = config('telegram.messages.no_stages_found');
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

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Show stage details
     */
    public function showStageDetails($chatId, $stageId): void
    {
        $stage = Stage::with('stories')->find($stageId);
        
        if (!$stage) {
            $this->sendErrorMessage($chatId, 'مرحله یافت نشد.');
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
        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Start reward creation
     */
    public function startRewardCreation($chatId): void
    {
        $stateData = [
            'mode' => 'reward_creation',
            'waiting_for' => 'title'
        ];
        
        $this->setAdminState($chatId, $stateData);
        
        $text = "🎁 ساخت جایزه جدید\n\n";
        $text .= "برای شروع، عنوان جایزه را وارد کنید:";
        
        $keyboard = [
            [
                ['text' => 'لغو', 'callback_data' => 'admin_reward_settings'],
            ]
        ];
        $this->sendMessage($chatId, $text, $keyboard);
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
                $this->handleRewardTitleInput($chatId, $text);
                break;
            case 'description':
                $this->handleRewardDescriptionInput($chatId, $text);
                break;
            case 'score':
                $this->handleRewardScoreInput($chatId, $text);
                break;
        }
    }

    /**
     * Handle reward title input
     */
    private function handleRewardTitleInput($chatId, $text): void
    {
        $this->updateAdminState($chatId, 'current_reward_data', ['title' => $text]);
        $this->updateAdminState($chatId, 'waiting_for', 'description');
        $this->sendMessage($chatId, '📝 حالا توضیحات جایزه را وارد کنید:');
    }

    /**
     * Handle reward description input
     */
    private function handleRewardDescriptionInput($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        $rewardData = $state['current_reward_data'] ?? [];
        $rewardData['description'] = $text;
        
        $this->updateAdminState($chatId, 'current_reward_data', $rewardData);
        $this->updateAdminState($chatId, 'waiting_for', 'score');
        $this->sendMessage($chatId, '🎯 حالا امتیاز جایزه را وارد کنید:');
    }

    /**
     * Handle reward score input
     */
    private function handleRewardScoreInput($chatId, $text): void
    {
        if (is_numeric($text) && $text > 0) {
            $state = $this->getAdminState($chatId);
            $rewardData = $state['current_reward_data'] ?? [];
            $rewardData['score'] = (int) $text;
            
            $this->updateAdminState($chatId, 'current_reward_data', $rewardData);
            $this->updateAdminState($chatId, 'waiting_for', 'image');
            $this->sendMessage($chatId, '🖼️ حالا عکس جایزه را ارسال کنید:');
        } else {
            $this->sendErrorMessage($chatId, 'لطفاً یک عدد مثبت وارد کنید.');
        }
    }

    /**
     * Handle reward photo message
     */
    public function handleRewardPhotoMessage($chatId, $message): void
    {
        $state = $this->getAdminState($chatId);
    
        if (!$state || $state['mode'] !== 'reward_creation' || $state['waiting_for'] !== 'image') {
            return;
        }
    
        try {
            $messageArray = $message->toArray();
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
                throw new \Exception('شناسه فایل عکس یافت نشد. لطفاً یک عکس معتبر ارسال کنید.');
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
    
            // Save to storage
            $fileName = 'reward_' . time() . '.jpg';
            $relativePath = 'rewards/' . $fileName;
            $baseUrl = 'https://api.daom.ir/storage/';
            $imagePath = $baseUrl . $relativePath;
            $saved = Storage::disk('public')->put($imagePath, $imageContent);
            
            if (!$saved) {
                throw new \Exception('خطا در ذخیره عکس در سرور.');
            }
    
            // Create reward
            $state = $this->getAdminState($chatId);
            $rewardData = $state['current_reward_data'] ?? [];
            $rewardData['image_path'] = $imagePath;
            
            $reward = Reward::create([
                'title' => $rewardData['title'],
                'description' => $rewardData['description'],
                'image_path' => $rewardData['image_path'],
                'score' => $rewardData['score'],
                'is_active' => true
            ]);
    
            $text = "✅ جایزه جدید با موفقیت ایجاد شد!\n\n";
            $text .= "🎁 عنوان: {$reward->title}\n";
            $text .= "📝 توضیحات: {$reward->description}\n";
            $text .= "🎯 امتیاز: {$reward->score}\n";
            $text .= "🖼️ عکس: ذخیره شد\n\n";
            $text .= "آیا می‌خواهید جایزه دیگری بسازید؟";
    
            $keyboard = [
                [
                    ['text' => 'بله، جایزه دیگر', 'callback_data' => 'admin_create_reward'],
                    ['text' => 'بازگشت به منو', 'callback_data' => 'admin_reward_settings'],
                ]
            ];
            $this->sendMessage($chatId, $text, $keyboard);
    
            $this->clearAdminState($chatId);
    
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, '❌ خطا در پردازش عکس جایزه: ' . $e->getMessage());
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

            // Generate unique codes for this photo
            $codes = StagePhoto::generateUniqueCodes();

            // Save photo data to state
            $state['stage_data']['photos'][] = [
                'photo_order' => $state['current_photo'],
                'original_path' => $photoData['original_path'],
                'blurred_path' => $photoData['blurred_path'],
                'code_1' => $codes[0],
                'code_2' => $codes[1]
            ];

            $state['photos_uploaded']++;
            $state['current_photo']++;

            if ($state['photos_uploaded'] < 6) {
                $state['waiting_for'] = 'photo_' . $state['current_photo'];
                $this->setAdminState($chatId, $state);
                
                $text = "✅ عکس شماره {$state['photos_uploaded']} از ۶ ذخیره شد!\n\n";
                $text .= "کدهای این عکس:\n";
                $text .= "🔑 کد ۱: {$codes[0]}\n";
                $text .= "🔑 کد ۲: {$codes[1]}\n\n";
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
                    'code_1' => $photoData['code_1'],
                    'code_2' => $photoData['code_2'],
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
            $text .= "✅ همه عکس‌ها و کدهای مربوطه ذخیره شدند.";

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
} 