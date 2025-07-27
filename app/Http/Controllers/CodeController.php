<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Code;
use Illuminate\Support\Facades\Auth;

class CodeController extends Controller
{
    /**
     * Validate and use a code
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:6'
        ]);

        $code = $request->input('code');
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        // Check if code exists
        $codeModel = Code::where('code', strtoupper($code))->first();
        
        if (!$codeModel) {
            return response()->json([
                'success' => false,
                'message' => 'کد وارد شده معتبر نیست'
            ], 404);
        }

        // Check if code is active
        if (!$codeModel->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'این کد غیرفعال شده است'
            ], 400);
        }

        // Check if code has already been used
        if ($codeModel->user_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'این کد قبلاً استفاده شده است'
            ], 400);
        }

        // Use the code
        try {
            $codeModel->update([
                'user_id' => $user->id,
                'is_active' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'کد با موفقیت اعمال شد',
                'code' => $codeModel->code
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعمال کد'
            ], 500);
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
            ], 401);
        }

        $codes = Code::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'codes' => $codes
        ], 200);
    }
} 