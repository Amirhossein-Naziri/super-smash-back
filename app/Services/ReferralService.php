<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    /**
     * Process referral during user registration
     */
    public function processReferral($referralCode, $newUserId)
    {
        try {
            DB::beginTransaction();

            // Find the referrer by their referral code
            $referrer = User::where('referral_code', $referralCode)->first();
            
            if (!$referrer) {
                return [
                    'success' => false,
                    'message' => 'کد دعوت نامعتبر است'
                ];
            }

            // Check if user is trying to refer themselves
            if ($referrer->id == $newUserId) {
                return [
                    'success' => false,
                    'message' => 'نمی‌توانید خودتان را دعوت کنید'
                ];
            }

            // Get the next referral order for this referrer
            $nextOrder = $referrer->referral_count + 1;
            
            // Calculate reward amount (progressive: 100, 200, 300, etc.)
            $rewardAmount = Referral::calculateReward($nextOrder);

            // Create referral record
            $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $newUserId,
                'reward_amount' => $rewardAmount,
                'referral_order' => $nextOrder,
                'is_active' => true
            ]);

            // Update referrer's stats
            $referrer->incrementReferralCount();
            $referrer->addReferralReward($rewardAmount);

            // Update referred user
            $referredUser = User::find($newUserId);
            $referredUser->update(['referred_by' => $referrer->id]);

            DB::commit();

            Log::info('Referral processed successfully', [
                'referrer_id' => $referrer->id,
                'referred_id' => $newUserId,
                'reward_amount' => $rewardAmount,
                'referral_order' => $nextOrder
            ]);

            return [
                'success' => true,
                'message' => 'کد دعوت با موفقیت اعمال شد',
                'referral' => $referral,
                'reward_amount' => $rewardAmount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Referral processing failed', [
                'error' => $e->getMessage(),
                'referral_code' => $referralCode,
                'new_user_id' => $newUserId
            ]);

            return [
                'success' => false,
                'message' => 'خطا در پردازش کد دعوت'
            ];
        }
    }

    /**
     * Validate referral code without processing
     */
    public function validateReferralCode($referralCode)
    {
        $referrer = User::where('referral_code', $referralCode)->first();
        
        if (!$referrer) {
            return [
                'valid' => false,
                'message' => 'کد دعوت نامعتبر است'
            ];
        }

        return [
            'valid' => true,
            'message' => 'کد دعوت معتبر است',
            'referrer_name' => $referrer->name ?? $referrer->telegram_username
        ];
    }

    /**
     * Get referral statistics for a user
     */
    public function getReferralStats($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return null;
        }

        $referrals = $user->referralRecords()->with('referred')->get();
        
        return [
            'total_referrals' => $user->referral_count,
            'total_rewards' => $user->referral_rewards,
            'referral_code' => $user->referral_code,
            'referrals' => $referrals->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'referred_user' => $referral->referred->name ?? $referral->referred->telegram_username,
                    'reward_amount' => $referral->reward_amount,
                    'referral_order' => $referral->referral_order,
                    'created_at' => $referral->created_at
                ];
            })
        ];
    }

    /**
     * Generate referral code for existing user
     */
    public function generateReferralCodeForUser($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'کاربر یافت نشد'
            ];
        }

        if ($user->referral_code) {
            return [
                'success' => true,
                'message' => 'کد دعوت قبلاً ایجاد شده است',
                'referral_code' => $user->referral_code
            ];
        }

        $referralCode = $user->generateReferralCode();
        
        return [
            'success' => true,
            'message' => 'کد دعوت با موفقیت ایجاد شد',
            'referral_code' => $referralCode
        ];
    }
}
