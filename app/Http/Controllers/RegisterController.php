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
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $exists = User::where('telegram_username', $request->username)->exists();
        if ($exists) {
            return response()->json(['error' => 'این کاربر قبلاً ثبت‌نام کرده است.'], 409);
        }

        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'city' => $request->city,
            'password' => Hash::make(uniqid()), // random password
            'telegram_user_id' => $request->telegram_user_id,
            'telegram_username' => $request->username,
            'telegram_first_name' => $request->telegram_first_name,
            'telegram_last_name' => $request->telegram_last_name,
            'telegram_language_code' => $request->telegram_language_code,
        ]);

        return response()->json(['success' => true, 'user' => $user], 201);
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