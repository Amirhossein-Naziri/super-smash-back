<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user via Telegram
     */
    public function telegramAuth(Request $request)
    {
        $request->validate([
            'initData' => 'required|string'
        ]);

        // For now, we'll use a simple approach
        // In production, you should validate the Telegram init data
        $initData = $request->input('initData');
        
        // Parse the init data to get user information
        parse_str($initData, $data);
        
        if (!isset($data['user'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Telegram data'
            ], 400);
        }

        $userData = json_decode($data['user'], true);
        
        // Find or create user
        $user = User::where('telegram_user_id', $userData['id'])->first();
        
        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $userData['first_name'] . ' ' . ($userData['last_name'] ?? ''),
                'telegram_user_id' => $userData['id'],
                'telegram_username' => $userData['username'] ?? null,
                'telegram_first_name' => $userData['first_name'],
                'telegram_last_name' => $userData['last_name'] ?? null,
                'telegram_language_code' => $userData['language_code'] ?? null,
                'password' => Hash::make(uniqid()), // Random password
            ]);
        }

        // Create token
        $token = $user->createToken('telegram-auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }
} 