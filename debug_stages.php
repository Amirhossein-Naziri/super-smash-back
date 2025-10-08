<?php

// کد tinker برای دیباگ کامل سیستم مرحله‌بندی
// این کد رو روی سرور اجرا کن: php artisan tinker --execute="require 'debug_stages.php';"

echo "=== دیباگ کامل سیستم مرحله‌بندی ===\n\n";

// 1. بررسی اتصال دیتابیس
echo "1. بررسی اتصال دیتابیس:\n";
try {
    $pdo = DB::connection()->getPdo();
    echo "✅ اتصال دیتابیس: OK\n";
} catch (Exception $e) {
    echo "❌ اتصال دیتابیس: FAILED - " . $e->getMessage() . "\n";
    exit;
}

// 2. بررسی جدول‌ها
echo "\n2. بررسی جدول‌ها:\n";
$stagesCount = App\Models\Stage::count();
$photosCount = App\Models\StagePhoto::count();
$progressCount = App\Models\UserStageProgress::count();
$recordingsCount = App\Models\UserVoiceRecording::count();
$usersCount = App\Models\User::count();

echo "📊 Stages: $stagesCount\n";
echo "📊 Stage Photos: $photosCount\n";
echo "📊 User Progress: $progressCount\n";
echo "📊 Voice Recordings: $recordingsCount\n";
echo "📊 Users: $usersCount\n";

// 3. بررسی مراحل موجود
echo "\n3. بررسی مراحل موجود:\n";
$stages = App\Models\Stage::orderBy('stage_number')->get();
if ($stages->count() > 0) {
    foreach ($stages as $stage) {
        $photosInStage = App\Models\StagePhoto::where('stage_id', $stage->id)->count();
        echo "🎯 Stage {$stage->id}: Number={$stage->stage_number}, Points={$stage->points}, Photos={$photosInStage}\n";
    }
} else {
    echo "❌ هیچ مرحله‌ای یافت نشد!\n";
}

// 4. بررسی کاربران
echo "\n4. بررسی کاربران:\n";
$users = App\Models\User::all();
foreach ($users as $user) {
    echo "👤 User {$user->id}: Name={$user->name}, Telegram ID={$user->telegram_id}, Level Story={$user->level_story}\n";
    
    // بررسی progress کاربر
    $userProgress = App\Models\UserStageProgress::where('user_id', $user->id)->get();
    if ($userProgress->count() > 0) {
        echo "   📈 Progress Records:\n";
        foreach ($userProgress as $progress) {
            echo "      - Stage {$progress->stage_id}: Unlocked={$progress->unlocked_photos_count}, Recordings={$progress->completed_voice_recordings}, Completed=" . ($progress->stage_completed ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "   📈 Progress Records: None\n";
    }
    
    // تست getNextIncompleteStage
    echo "   🔍 Testing getNextIncompleteStage:\n";
    $nextStage = App\Models\UserStageProgress::getNextIncompleteStage($user->id);
    if ($nextStage) {
        echo "      ✅ Next Stage: ID={$nextStage->id}, Number={$nextStage->stage_number}, Points={$nextStage->points}\n";
    } else {
        echo "      ❌ Next Stage: NULL (همه مراحل تکمیل شده)\n";
    }
    
    // تست getOrCreateProgress
    if ($nextStage) {
        echo "   🔍 Testing getOrCreateProgress:\n";
        $progress = App\Models\UserStageProgress::getOrCreateProgress($user->id, $nextStage->id);
        echo "      📊 Progress: ID={$progress->id}, Unlocked={$progress->unlocked_photos_count}, Recordings={$progress->completed_voice_recordings}\n";
    }
    
    echo "\n";
}

// 5. بررسی عکس‌های مرحله
echo "5. بررسی عکس‌های مرحله:\n";
$photos = App\Models\StagePhoto::with('stage')->get();
foreach ($photos as $photo) {
    echo "🖼️ Photo {$photo->id}: Stage={$photo->stage_id}, Order={$photo->photo_order}, Codes={$photo->code_1},{$photo->code_2}, Unlocked=" . ($photo->is_unlocked ? 'Yes' : 'No') . "\n";
}

// 6. تست API endpoint
echo "\n6. تست API endpoint (getCurrentStagePhotos):\n";
if ($users->count() > 0) {
    $testUser = $users->first();
    echo "🧪 Testing with User ID: {$testUser->id}\n";
    
    try {
        // شبیه‌سازی درخواست API
        $controller = new App\Http\Controllers\StagePhotoController();
        $request = new Illuminate\Http\Request();
        
        // شبیه‌سازی authentication
        Auth::login($testUser);
        
        $response = $controller->getCurrentStagePhotos($request);
        $data = $response->getData(true);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ API Response: SUCCESS\n";
            echo "   📊 Stage: " . json_encode($data['stage'] ?? 'null') . "\n";
            echo "   📊 Photos Count: " . count($data['photos'] ?? []) . "\n";
            echo "   📊 Progress: " . json_encode($data['progress'] ?? 'null') . "\n";
            echo "   📊 Debug: " . json_encode($data['debug'] ?? 'null') . "\n";
        } else {
            echo "❌ API Response: ERROR {$response->getStatusCode()}\n";
            echo "   📊 Error: " . json_encode($data) . "\n";
        }
        
        Auth::logout();
    } catch (Exception $e) {
        echo "❌ API Test Failed: " . $e->getMessage() . "\n";
    }
}

echo "\n=== پایان دیباگ ===\n";
