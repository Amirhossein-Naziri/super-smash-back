<?php

namespace App\Services;

use App\Models\Code;
use App\Models\Stage;
use App\Models\Story;
use App\Models\AdminState;
use App\Traits\TelegramMessageTrait;
use Illuminate\Support\Facades\Storage;

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
            }

            $keyboard = [
                [
                    ['text' => 'بازگشت به تنظیمات کدها', 'callback_data' => 'admin_code_settings'],
                ]
            ];
            $this->sendMessage($chatId, $text, $keyboard);
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در نمایش لیست کدها: ' . $e->getMessage());
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
     * Handle text message during story creation
     */
    public function handleStoryTextMessage($chatId, $text): void
    {
        $state = $this->getAdminState($chatId);
        
        // Debug logging
        \Log::info("Story text message received", [
            'chat_id' => $chatId,
            'text' => $text,
            'state' => $state,
            'has_state' => !empty($state),
            'mode' => $state['mode'] ?? 'no_mode',
            'waiting_for' => $state['waiting_for'] ?? 'no_waiting'
        ]);
        
        if (!$state || $state['mode'] !== 'story_creation') {
            $this->sendMessage($chatId, "🔍 پیام دریافت شد اما در حالت ساخت داستان نیستید.\nمتن: {$text}");
            return;
        }

        $waitingFor = $state['waiting_for'] ?? '';

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
        if (!$state || $state['mode'] !== 'story_creation' || $state['waiting_for'] !== 'image') {
            return;
        }

        try {
            $photos = $message->getPhoto();
            $largestPhoto = end($photos);
            $fileId = $largestPhoto['file_id'];

            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file['file_path'];

            $imageUrl = "https://api.telegram.org/file/bot{$this->telegram->getAccessToken()}/{$filePath}";
            $imageContent = file_get_contents($imageUrl);
            
            $fileName = 'story_' . time() . '_' . $state['current_story'] . '.jpg';
            $imagePath = 'stories/' . $fileName;
            
            Storage::disk('public')->put($imagePath, $imageContent);
            
            $storyData = $state['current_story_data'] ?? [];
            $storyData['image_path'] = $imagePath;
            
            $this->updateAdminState($chatId, 'current_story_data', $storyData);
            $this->updateAdminState($chatId, 'waiting_for', 'correct_choice');
            
            $this->askForCorrectChoice($chatId);
            
        } catch (\Exception $e) {
            $this->sendErrorMessage($chatId, 'خطا در ذخیره عکس: ' . $e->getMessage());
        }
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
} 