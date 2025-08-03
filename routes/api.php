<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;

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

// Story Game Routes (requires authentication)
Route::middleware('auth:sanctum')->prefix('story')->group(function () {
    Route::get('/current-stage', [GameController::class, 'getCurrentStage']);
    Route::post('/submit-answer', [GameController::class, 'submitAnswer']);
    Route::get('/progress', [GameController::class, 'getUserProgress']);
});
