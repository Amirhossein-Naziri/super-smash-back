<?php

namespace App\Http\Controllers;

use App\Models\Code;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
                'regex:/^[A-Z0-9]{6}$/',
                Rule::exists('codes', 'code')->where('is_active', true)
            ],
            'telegram_user_id' => 'required|integer|exists:users,telegram_user_id'
        ], [
            'code.regex' => 'کد باید شامل حداقل یک حرف انگلیسی و یک عدد باشد (مثال: AB12CD)',
            'code.size' => 'کد باید دقیقاً 6 کاراکتر باشد'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'valid_examples' => ['AB12CD', '1A2B3C'] // مثال‌های معتبر
            ], 400);
        }

        // 2. یافتن کد و کاربر
        try {
            $code = Code::where('code', strtoupper($request->code))->first();
            $user = User::where('telegram_user_id', $request->telegram_user_id)->first();

            // 3. بررسی استفاده نشدن کد
            if ($code->user_id != null) {
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

    /**
     * Write all codes to a CSV file at the given absolute path
     */
    public static function writeCodesCsvToPath(string $fullPath): string
    {
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($fullPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open file for writing: ' . $fullPath);
        }

        // UTF-8 BOM for Excel (Windows)
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header
        fputcsv($handle, [
            'ID',
            'Code',
            'Is Active',
            'User ID',
            'User Name',
            'Telegram Username',
            'Telegram User ID',
            'Created At',
            'Updated At',
        ]);

        foreach (Code::with('user')->cursor() as $code) {
            $user = $code->user;
            fputcsv($handle, [
                $code->id,
                $code->code,
                $code->is_active ? '1' : '0',
                $code->user_id,
                $user ? $user->name : '',
                $user ? $user->telegram_username : '',
                $user ? $user->telegram_user_id : '',
                optional($code->created_at)->toDateTimeString(),
                optional($code->updated_at)->toDateTimeString(),
            ]);
        }

        fclose($handle);

        return $fullPath;
    }

    /**
     * Export codes as CSV without external packages
     */
    public function exportCodesCsv()
    {
        $fileName = 'codes_' . now()->format('Ymd_His') . '.csv';
        $relativePath = 'exports/' . $fileName;
        $fullPath = storage_path('app/public/' . $relativePath);

        self::writeCodesCsvToPath($fullPath);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ];

        return response()->download($fullPath, $fileName, $headers)->deleteFileAfterSend(true);
    }
}