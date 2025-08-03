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
     */
    public function validateCode(Request $request)
    {
        // 1. اعتبارسنجی پیشرفته
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'size:6',
                'regex:/^[A-Z0-9]+$/',
                Rule::exists('codes', 'code')->where('is_active', true)
            ],
            'telegram_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'telegram_user_id')
            ]
        ], [
            'code.required' => 'وارد کردن کد الزامی است',
            'code.size' => 'کد باید دقیقاً 6 کاراکتر باشد',
            'code.regex' => 'کد فقط می‌تواند شامل حروف انگلیسی بزرگ و اعداد باشد',
            'code.exists' => 'کد نامعتبر یا غیرفعال است',
            'telegram_user_id.required' => 'شناسه تلگرام الزامی است',
            'telegram_user_id.integer' => 'شناسه تلگرام باید عدد باشد',
            'telegram_user_id.exists' => 'کاربر یافت نشد'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی داده‌ها',
                'errors' => $validator->errors()
            ], self::HTTP_BAD_REQUEST);
        }

        // 2. یافتن کد و کاربر
        try {
            $code = Code::where('code', strtoupper($request->code))->first();
            $user = User::where('telegram_user_id', $request->telegram_user_id)->first();

            // 3. بررسی استفاده نشدن کد
            if ($code->user_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'این کد قبلاً استفاده شده است'
                ], self::HTTP_BAD_REQUEST);
            }

            // 4. ثبت استفاده از کد
            $code->update([
                'user_id' => $user->id,
                'used_at' => now(),
                'is_active' => false
            ]);

            // 5. ایجاد توکن دسترسی
            $token = $user->createToken('story-access-token', ['read-story'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'کد با موفقیت اعمال شد',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name
                    ],
                    'code' => $code->code
                ]
            ], self::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Code redemption error', [
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