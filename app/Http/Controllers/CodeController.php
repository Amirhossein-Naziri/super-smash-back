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
     * اعتبارسنجی و استفاده از کد داستان
     */
    public function validateCode(Request $request)
    {
        // اعتبارسنجی اولیه ورودی
        $request->validate([
            'code' => 'required|string|max:6'
        ], [
            'code.required' => 'وارد کردن کد الزامی است',
            'code.string' => 'کد باید به صورت رشته باشد',
            'code.max' => 'کد باید حداکثر ۶ کاراکتر باشد',
        ]);

        $code = $request->input('code');
        Log::info('Code validation called', ['code' => $code]);

        $user = Auth::user();

        // بررسی احراز هویت کاربر
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'برای استفاده از کد باید وارد حساب کاربری شوید'
            ], self::HTTP_UNAUTHORIZED);
        }

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
            $codeModel->update([
                'user_id' => $user->id,
                'is_active' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'کد با موفقیت اعمال شد',
                'code' => $codeModel->code
            ], self::HTTP_OK);
        } catch (\Exception $e) {
            // Log the error with detailed context
            Log::error('Failed to apply code', [
                'code' => $code,
                'user_id' => $user->id,
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