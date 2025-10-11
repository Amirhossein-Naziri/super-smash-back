<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\StagePhotoController;
use Illuminate\Support\Facades\Response;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Authentication routes
Route::post('/auth/telegram', [AuthController::class, 'telegramAuth']);
Route::post('/auth/test-login', [AuthController::class, 'testLogin']); // Temporary test route
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// Code validation routes (no auth required)
Route::post('/code/validate', [CodeController::class, 'validateCode']);
Route::post('/code/debug', [CodeController::class, 'debugRequest']);
Route::get('/code/user-codes', [CodeController::class, 'getUserCodes']);
Route::get('/code/export/csv', [CodeController::class, 'exportCodesCsv']);

// Simple test route
Route::get('/test/codes', function() {
    $codes = \App\Models\Code::select('code', 'is_active', 'user_id')->get();
    return response()->json(['codes' => $codes]);
});

// Telegram Bot Routes
Route::prefix('telegram')->group(function () {
    Route::post('/webhook', [TelegramBotController::class, 'webhook']);
    Route::get('/set-webhook', [TelegramBotController::class, 'setWebhook']);
    Route::get('/bot-info', [TelegramBotController::class, 'getBotInfo']);
    Route::post('/get-chat-id', [TelegramBotController::class, 'getChatId']);
    Route::post('/test-admin', [TelegramBotController::class, 'testAdmin']);
});

// User Registration Route
Route::post('/register', [RegisterController::class, 'register']);
// Check if user exists by Telegram username
Route::get('/user/exists', [RegisterController::class, 'userExists']);
Route::post('/log-error', [\App\Http\Controllers\TelegramBotController::class, 'logError']);

// Game Routes
Route::prefix('game')->group(function () {
    Route::get('/stages', [GameController::class, 'getStages']);
    Route::get('/stages/count', [GameController::class, 'getStageCount']);
    Route::get('/stages/{stageNumber}', [GameController::class, 'getStage']);
    Route::get('/stages/{stageNumber}/stories', [GameController::class, 'getStageStories']);
    Route::post('/check-answer', [GameController::class, 'checkAnswer']);
});

// Rewards Routes
Route::prefix('rewards')->group(function () {
    Route::get('/', [GameController::class, 'getRewards']);
    Route::get('/active', [GameController::class, 'getActiveRewards']);
    Route::get('/eligibility', [RewardController::class, 'eligibility']);
    Route::post('/claim', [RewardController::class, 'claim']);
});

// Stage Photos Routes (new system) - بدون authentication middleware
Route::prefix('stage-photos')->group(function () {
    Route::get('/current-stage', [StagePhotoController::class, 'getCurrentStagePhotos'])->middleware('cache.api:300');
    Route::post('/unlock', [StagePhotoController::class, 'unlockPhoto']);
    Route::post('/partially-unlock', [StagePhotoController::class, 'partiallyUnlockPhoto']);
    Route::post('/fully-unlock', [StagePhotoController::class, 'fullyUnlockPhoto']);
    Route::post('/upload-voice', [StagePhotoController::class, 'uploadVoiceRecording']);
    Route::get('/completion-status', [StagePhotoController::class, 'getStageCompletionStatus'])->middleware('cache.api:300');
    Route::post('/create-test-stage', [StagePhotoController::class, 'createTestStage']);
});

// Admin routes
Route::prefix('admin')->group(function () {
    Route::get('/stages', [App\Http\Controllers\AdminController::class, 'getStages'])->middleware('cache.api:600');
    Route::get('/stages/{stageId}/users', [App\Http\Controllers\AdminController::class, 'getStageUsers'])->middleware('cache.api:300');
    Route::get('/stages/{stageId}/users/{userId}/recordings', [App\Http\Controllers\AdminController::class, 'getUserStageRecordings'])->middleware('cache.api:300');
    Route::get('/stages/{stageId}/users/{userId}/combined', [App\Http\Controllers\AdminController::class, 'getCombinedVoiceRecording'])->middleware('cache.api:600');
});
