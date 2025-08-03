<?php

namespace App\Http\Controllers;

use App\Models\Code;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CodeController extends Controller
{
    // HTTP status codes
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_NOT_FOUND = 404;
    const HTTP_SERVER_ERROR = 500;

    /**
     * Validate and redeem a story code
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateCode(Request $request)
    {
        // 1. اعتبارسنجی پیشرفته ورودی‌ها
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'size:6',  // دقیقاً 6 کاراکتر
                'regex:/^[A-Z0-9]+$/'  // فقط حروف بزرگ و اعداد
            ],
            'telegram_user_id' => [
                'required',
                'numeric',
                Rule::exists('users', 'telegram_user_id')  // بررسی وجود کاربر
            ]
        ], [
            'code.required' => 'وارد کردن کد الزامی است',
            'code.size' => 'کد باید دقیقاً 6 کاراکتر باشد',
            'code.regex' => 'کد فقط می‌تواند شامل حروف انگلیسی بزرگ و اعداد باشد',
            'telegram_user_id.required' => 'شناسه تلگرام الزامی است',
            'telegram_user_id.numeric' => 'شناسه تلگرام باید عددی باشد',
            'telegram_user_id.exists' => 'کاربر با این شناسه تلگرام یافت نشد'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'داده‌های ورودی نامعتبر',
                'errors' => $validator->errors()
            ], self::HTTP_BAD_REQUEST);
        }

        // 2. لاگ کامل درخواست
        Log::info('Code validation request', [
            'code' => $request->code,
            'telegram_user_id' => $request->telegram_user_id,
            'ip' => $request->ip()
        ]);

        // 3. جستجوی کد در دیتابیس
        $codeModel = Code::where('code', strtoupper($request->code))->first();

        if (!$codeModel) {
            Log::warning('Invalid code attempt', ['code' => $request->code]);
            return response()->json([
                'success' => false,
                'message' => 'کد وارد شده معتبر نیست'
            ], self::HTTP_NOT_FOUND);
        }

        // 4. بررسی وضعیت کد
        if (!$codeModel->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'این کد غیرفعال شده است'
            ], self::HTTP_BAD_REQUEST);
        }

        if ($codeModel->user_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'این کد قبلاً استفاده شده است'
            ], self::HTTP_BAD_REQUEST);
        }

        // 5. استفاده از کد
        try {
            $user = User::where('telegram_user_id', $request->telegram_user_id)->first();

            $codeModel->update([
                'is_active' => false,
                'user_id' => $user->id,
                'used_at' => now()
            ]);

            // 6. ایجاد توکن احراز هویت
            $token = $user->createToken('story-game-token', ['view-story'])->plainTextToken;

            Log::info('Code successfully redeemed', [
                'code' => $codeModel->code,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'کد با موفقیت اعمال شد',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'level_story' => $user->level_story,
                        'score' => $user->score
                    ],
                    'code' => [
                        'value' => $codeModel->code,
                        'type' => $codeModel->type
                    ]
                ]
            ], self::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Code redemption failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطای سرور در پردازش درخواست'
            ], self::HTTP_SERVER_ERROR);
        }
    }
}