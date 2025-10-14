<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    /**
     * Validate referral code
     */
    public function validateReferralCode(Request $request)
    {
        $referralCode = $request->input('referral_code');
        
        if (empty($referralCode)) {
            return response()->json([
                'success' => false,
                'message' => 'کد دعوت الزامی است'
            ], 400);
        }

        $result = $this->referralService->validateReferralCode($referralCode);
        
        return response()->json($result);
    }

    /**
     * Get referral statistics for authenticated user
     */
    public function getReferralStats(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        $stats = $this->referralService->getReferralStats($user->id);
        
        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Generate referral code for authenticated user
     */
    public function generateReferralCode(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        $result = $this->referralService->generateReferralCodeForUser($user->id);
        
        return response()->json($result);
    }

    /**
     * Get user's referral code
     */
    public function getReferralCode(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'referral_code' => $user->referral_code,
            'message' => $user->referral_code ? 'کد دعوت شما' : 'کد دعوت هنوز ایجاد نشده است'
        ]);
    }

    /**
     * Get list of users referred by authenticated user
     */
    public function getReferredUsers(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        $referrals = $user->referralRecords()
            ->with('referred')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'referred_user_name' => $referral->referred->name ?? $referral->referred->telegram_username,
                    'referred_user_id' => $referral->referred->id,
                    'reward_amount' => $referral->reward_amount,
                    'referral_order' => $referral->referral_order,
                    'created_at' => $referral->created_at->format('Y-m-d H:i:s'),
                    'is_active' => $referral->is_active
                ];
            });

        return response()->json([
            'success' => true,
            'referrals' => $referrals,
            'total_count' => $referrals->count(),
            'total_rewards' => $referrals->sum('reward_amount')
        ]);
    }

    /**
     * Get referral leaderboard (top referrers)
     */
    public function getLeaderboard(Request $request)
    {
        $limit = $request->input('limit', 10);
        
        $leaderboard = \App\Models\User::select('id', 'name', 'telegram_username', 'referral_count', 'referral_rewards')
            ->where('referral_count', '>', 0)
            ->orderBy('referral_count', 'desc')
            ->orderBy('referral_rewards', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'user_id' => $user->id,
                    'name' => $user->name ?? $user->telegram_username,
                    'referral_count' => $user->referral_count,
                    'referral_rewards' => $user->referral_rewards
                ];
            });

        return response()->json([
            'success' => true,
            'leaderboard' => $leaderboard
        ]);
    }
}