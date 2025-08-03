<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Code;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CodeController extends Controller
{
    /**
     * وضعیت‌های HTTP برای خوانایی بیشتر
     */
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_NOT_FOUND = 404;
    const HTTP_SERVER_ERROR = 500;

    /**
     * Debug endpoint to check request data
     */
    public function debugRequest(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'all_inputs' => $request->all(),
                'code' => $request->input('code'),
                'telegram_user_id' => $request->input('telegram_user_id'),
                'telegram_user_id_type' => gettype($request->input('telegram_user_id')),
                'headers' => $request->headers->all(),
                'method' => $request->method(),
                'url' => $request->url()
            ]
        ]);
    }

    /**
     * اعتبارسنجی و استفاده از کد داستان
     */
    public function validateCode(Request $request)
    {
        // اعتبارسنجی اولیه ورودی - موقتاً ساده‌تر
        $request->validate([
            'code' => 'required|string|max:6',
            'telegram_user_id' => 'required'
        ], [
            'code.required' => 'وارد کردن کد الزامی است',
            'code.string' => 'کد باید به صورت رشته باشد',
            'code.max' => 'کد باید حداکثر ۶ کاراکتر باشد',
            'telegram_user_id.required' => 'شناسه تلگرام کاربر الزامی است',
        ]);

        $code = $request->input('code');
        $telegramUserId = $request->input('telegram_user_id');
        Log::info('Code validation called', [
            'code' => $code,
            'telegram_user_id' => $telegramUserId,
            'telegram_user_id_type' => gettype($telegramUserId),
            'all_inputs' => $request->all()
        ]);

     

        // جستجوی کد
        $codeModel = Code::where('code', strtoupper($code))->first();
        if (!$codeModel) {
            return response()->json([
                'success' => false,
                'message' => 'کد وارد شده معتبر نیست'
            ], self::HTTP_NOT_FOUND);
        }

        // بررسی فعال بودن کد
        if (!$codeModel->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'این کد غیرفعال شده است'
            ], self::HTTP_BAD_REQUEST);
        }

        // بررسی استفاده شدن کد
        if ($codeModel->user_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'این کد قبلاً استفاده شده است'
            ], self::HTTP_BAD_REQUEST);
        }

        // استفاده از کد و ثبت برای کاربر فعلی
        try {
            // Find user by telegram_user_id from the request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'شناسه تلگرام کاربر الزامی است'
                ], self::HTTP_BAD_REQUEST);
            }

            $user = User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'کاربر یافت نشد'
                ], self::HTTP_NOT_FOUND);
            }

            $codeModel->update([
                'is_active' => false,
                'user_id' => $user->id
            ]);

            // Create token for authentication
            $token = $user->createToken('story-game-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'کد با موفقیت اعمال شد',
                'code' => $codeModel->code,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'level_story' => $user->level_story ?? 1,
                    'score' => $user->score ?? 0
                ]
            ], self::HTTP_OK);
        } catch (\Exception $e) {
            // Log the error with detailed context
            Log::error('Failed to apply code', [
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در اعمال کد، لطفاً بعداً تلاش کنید'
            ], self::HTTP_SERVER_ERROR);
        }
    }

    /**
     * Get user's used codes
     */
    public function getUserCodes(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], self::HTTP_UNAUTHORIZED);
        }

        $codes = Code::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'codes' => $codes
        ], self::HTTP_OK);
    }
}