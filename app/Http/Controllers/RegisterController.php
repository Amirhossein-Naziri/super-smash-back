<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        \Log::info('Register called', $request->all());
        
        // Custom validation messages in Persian
        $messages = [
            'username.required' => 'نام کاربری الزامی است',
            'username.string' => 'نام کاربری باید متن باشد',
            'username.max' => 'نام کاربری نمی‌تواند بیش از 255 کاراکتر باشد',
            'name.string' => 'نام باید متن باشد',
            'name.max' => 'نام نمی‌تواند بیش از 255 کاراکتر باشد',
            'phone.string' => 'شماره تلفن باید متن باشد',
            'phone.max' => 'شماره تلفن نمی‌تواند بیش از 20 کاراکتر باشد',
            'city.string' => 'نام شهر باید متن باشد',
            'city.max' => 'نام شهر نمی‌تواند بیش از 255 کاراکتر باشد',
        ];
        
        // Clean empty strings to null for optional fields
        $data = $request->all();
        
        // Ensure username is not null or empty
        if (empty($data['username'])) {
            $data['username'] = $data['telegram_username'] ?? $data['telegram_user_id'] ?? 'user_' . time();
        }
        
        $data['name'] = !empty(trim($data['name'] ?? '')) ? trim($data['name']) : null;
        $data['phone'] = !empty(trim($data['phone'] ?? '')) ? trim($data['phone']) : null;
        $data['city'] = !empty(trim($data['city'] ?? '')) ? trim($data['city']) : null;
        
        $validator = Validator::make($data, [
            'username' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'telegram_user_id' => 'nullable|string|max:255',
            'telegram_username' => 'nullable|string|max:255',
            'telegram_first_name' => 'nullable|string|max:255',
            'telegram_last_name' => 'nullable|string|max:255',
            'telegram_language_code' => 'nullable|string|max:10',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $exists = User::where('telegram_username', $data['username'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => 'این کاربر قبلاً ثبت‌نام کرده است.'
            ], 409);
        }

        try {
            $user = User::create([
                'name' => $data['name'] ?: 'کاربر', // Default name if not provided
                'phone' => $data['phone'],
                'city' => $data['city'],
                'password' => Hash::make(uniqid()), // random password
                'telegram_user_id' => $data['telegram_user_id'],
                'telegram_username' => $data['username'],
                'telegram_first_name' => $data['telegram_first_name'],
                'telegram_last_name' => $data['telegram_last_name'],
                'telegram_language_code' => $data['telegram_language_code'],
            ]);

            return response()->json([
                'success' => true, 
                'user' => $user,
                'message' => 'ثبت‌نام با موفقیت انجام شد'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.'
            ], 500);
        }
    }

    public function userExists(Request $request)
    {
        $username = $request->query('username');
        $telegramUserId = $request->query('telegram_user_id');
        $exists = false;
        
        if ($telegramUserId) {
            // Check by telegram_user_id (preferred method)
            $exists = User::where('telegram_user_id', $telegramUserId)->exists();
        } elseif ($username) {
            // Check by username (fallback)
            $exists = User::where('telegram_username', $username)->exists();
        }
        
        return response()->json(['exists' => $exists]);
    }
} 