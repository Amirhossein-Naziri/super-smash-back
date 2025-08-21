<?php

namespace App\Http\Controllers;

use App\Models\Reward;
use App\Models\RewardClaim;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    /**
     * Check if the user is eligible to claim a reward at their current stage.
     * Rules: After a correct story, user gets 1 coupon to open a reward box.
     * If they don't use it, it carries over; but only one unclaimed per stage.
     */
    public function eligibility(Request $request)
    {
        $telegramUserId = (int) $request->query('telegram_user_id');
        if (!$telegramUserId) {
            return response()->json(['success' => false, 'message' => 'آی‌دی تلگرام نامعتبر است'], 400);
        }

        $user = User::where('telegram_user_id', $telegramUserId)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'کاربر یافت نشد'], 404);
        }

        $levelStory = (int) ($user->level_story ?? 1);
        // Eligible only after passing at least one stage
        if ($levelStory <= 1) {
            return response()->json([
                'success' => true,
                'data' => [
                    'eligible' => false,
                    'stage_number' => null,
                ],
            ]);
        }

        $completedStageNumber = $levelStory - 1; // just completed stage

        $hasClaim = RewardClaim::where('user_id', $user->id)
            ->where('stage_number', $completedStageNumber)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => !$hasClaim,
                'stage_number' => $completedStageNumber,
            ],
        ]);
    }

    /**
     * Claim a random active reward for the user for a given stage.
     * Ensures only one claim per stage per user.
     */
    public function claim(Request $request)
    {
        $telegramUserId = (int) $request->input('telegram_user_id');
        $stageNumber = (int) $request->input('stage_number');
        if (!$telegramUserId || !$stageNumber) {
            return response()->json(['success' => false, 'message' => 'پارامترهای نامعتبر'], 400);
        }

        $user = User::where('telegram_user_id', $telegramUserId)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'کاربر یافت نشد'], 404);
        }

        // Verify stage exists (optional)
        $stage = Stage::where('stage_number', $stageNumber)->first();
        if (!$stage) {
            return response()->json(['success' => false, 'message' => 'مرحله یافت نشد'], 404);
        }

        // Ensure only one claim per stage per user
        if (RewardClaim::where('user_id', $user->id)->where('stage_number', $stageNumber)->exists()) {
            return response()->json(['success' => false, 'message' => 'قبلاً جایزه این مرحله را دریافت کرده‌اید'], 409);
        }

        // Random active reward that user has not claimed before
        $claimedRewardIds = RewardClaim::where('user_id', $user->id)->pluck('reward_id');
        $query = Reward::active();
        if ($claimedRewardIds->count() > 0) {
            $query->whereNotIn('id', $claimedRewardIds);
        }
        $reward = $query->inRandomOrder()->first();
        if (!$reward) {
            // All rewards claimed
            return response()->json([
                'success' => false,
                'message' => 'تمام جوایز را دریافت کرده‌اید',
                'data' => [
                    'all_claimed' => true,
                ],
            ], 409);
        }

        // Create claim atomically
        $claim = DB::transaction(function () use ($user, $reward, $stageNumber) {
            return RewardClaim::create([
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'stage_number' => $stageNumber,
                'claimed_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'جایزه با موفقیت دریافت شد',
            'data' => [
                'reward' => $reward,
                'claim' => $claim,
            ],
        ]);
    }
}

