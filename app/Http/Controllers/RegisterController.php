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
        
        $validator = Validator::make($request->all(), [
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

        $exists = User::where('telegram_username', $request->username)->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => 'این کاربر قبلاً ثبت‌نام کرده است.'
            ], 409);
        }

        try {
            $user = User::create([
                'name' => $request->name ?: 'کاربر', // Default name if not provided
                'phone' => $request->phone,
                'city' => $request->city,
                'password' => Hash::make(uniqid()), // random password
                'telegram_user_id' => $request->telegram_user_id,
                'telegram_username' => $request->username,
                'telegram_first_name' => $request->telegram_first_name,
                'telegram_last_name' => $request->telegram_last_name,
                'telegram_language_code' => $request->telegram_language_code,
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
        $exists = false;
        if ($username) {
            $exists = User::where('telegram_username', $username)->exists();
        }
        return response()->json(['exists' => $exists]);
    }
} 