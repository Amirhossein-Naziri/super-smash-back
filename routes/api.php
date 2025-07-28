<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\AuthController;

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
Route::get('/code/user-codes', [CodeController::class, 'getUserCodes']);

// Simple test route
Route::get('/test/codes', function() {
    $codes = \App\Models\Code::select('code', 'is_active', 'user_id')->get();
    return response()->json(['codes' => $codes]);
});

// Test validation route
Route::post('/test/validate', function(Request $request) {
    $code = $request->input('code');
    
    // Check if code exists
    $codeModel = \App\Models\Code::where('code', strtoupper($code))->first();
    
    if (!$codeModel) {
        return response()->json(['success' => false, 'message' => 'کد وارد شده معتبر نیست']);
    }
    
    if (!$codeModel->is_active) {
        return response()->json(['success' => false, 'message' => 'این کد غیرفعال شده است']);
    }
    
    if ($codeModel->user_id !== null) {
        return response()->json(['success' => false, 'message' => 'این کد قبلاً استفاده شده است']);
    }
    
    return response()->json(['success' => true, 'message' => 'کد معتبر است']);
});

// Telegram Bot Routes
Route::prefix('telegram')->group(function () {
    Route::post('/webhook', [TelegramBotController::class, 'webhook']);
    Route::get('/set-webhook', [TelegramBotController::class, 'setWebhook']);
    Route::get('/bot-info', [TelegramBotController::class, 'getBotInfo']);
});

// User Registration Route
Route::post('/register', [RegisterController::class, 'register']);
// Check if user exists by Telegram username
Route::get('/user/exists', [RegisterController::class, 'userExists']);
Route::post('/log-error', [\App\Http\Controllers\TelegramBotController::class, 'logError']);
