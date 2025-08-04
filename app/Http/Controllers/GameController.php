<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Stage;
use App\Models\Story;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    /**
     * Get current stage and stories for the user
     */
    public function getCurrentStage(Request $request)
    {
        $telegramUserId = (int) $request->query('telegram_user_id'); // Cast to integer
        Log::info('getCurrentStage - telegram_user_id: ' . $telegramUserId);
        if (!$telegramUserId) { // Check if it's 0 or invalid
            return response()->json([
                'success' => false,
                'message' => 'آی‌دی تلگرام نامعتبر است'
            ], 400);
        }

        $user = User::where('telegram_user_id', $telegramUserId)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر یافت نشد'
            ], 404);
        }

        // Get current level_story
        $currentLevel = $user->level_story ?? 1;
        
        // Find the stage for current level
        $stage = Stage::where('stage_number', $currentLevel)->first();
        
        if (!$stage) {
            return response()->json([
                'success' => false,
                'message' => 'مرحله مورد نظر یافت نشد'
            ], 404);
        }

        // Get stories for this stage in random order
        $stories = Story::where('stage_id', $stage->id)
                       ->inRandomOrder()
                       ->get(['id', 'title', 'description', 'image_path', 'is_correct', 'order']);

        return response()->json([
            'success' => true,
            'data' => [
                'stage' => [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points,
                    'is_completed' => $stage->is_completed
                ],
                'stories' => $stories,
                'user_level' => $currentLevel
            ]
        ]);
    }

    /**
     * Submit answer and update user progress
     */
    public function submitAnswer(Request $request)
    {
        $request->validate([
            'story_id' => 'required|integer|exists:stories,id',
            'telegram_user_id' => 'required|numeric'
        ], [
            'story_id.required' => 'انتخاب داستان الزامی است',
            'story_id.integer' => 'شناسه داستان باید عدد باشد',
            'story_id.exists' => 'داستان مورد نظر یافت نشد',
            'telegram_user_id.required' => 'آی‌دی تلگرام الزامی است',
            'telegram_user_id.numeric' => 'آی‌دی تلگرام باید عدد باشد'
        ]);

        $user = User::where('telegram_user_id', $request->telegram_user_id)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر یافت نشد'
            ], 404);
        }

        $storyId = $request->input('story_id');
        $story = Story::find($storyId);
        
        if (!$story) {
            return response()->json([
                'success' => false,
                'message' => 'داستان مورد نظر یافت نشد'
            ], 404);
        }

        // Check if answer is correct
        $isCorrect = $story->is_correct;
        
        if ($isCorrect) {
            // Update user score and level
            $user->score = ($user->score ?? 0) + $story->stage->points;
            $user->level_story = $user->level_story + 1;
            $user->save();

            // Mark stage as completed
            $story->stage->update(['is_completed' => true]);

            return response()->json([
                'success' => true,
                'message' => 'پاسخ درست! به مرحله بعدی می‌روید',
                'data' => [
                    'is_correct' => true,
                    'points_earned' => $story->stage->points,
                    'new_score' => $user->score,
                    'new_level' => $user->level_story,
                    'stage_completed' => true
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'پاسخ اشتباه! لطفاً دوباره تلاش کنید',
                'data' => [
                    'is_correct' => false,
                    'correct_story_id' => Story::where('stage_id', $story->stage_id)
                                               ->where('is_correct', true)
                                               ->first()->id ?? null
                ]
            ]);
        }
    }

    /**
     * Get user progress
     */
    public function getUserProgress(Request $request)
    {
        $telegramUserId = $request->query('telegram_user_id');
        if (!$telegramUserId || !is_numeric($telegramUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'آی‌دی تلگرام نامعتبر است'
            ], 400);
        }

        $user = User::where('telegram_user_id', $telegramUserId)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر یافت نشد'
            ], 404);
        }

        $totalStages = Stage::count();
        $completedStages = Stage::where('is_completed', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'current_level' => $user->level_story ?? 1,
                'score' => $user->score ?? 0,
                'total_stages' => $totalStages,
                'completed_stages' => $completedStages,
                'progress_percentage' => $totalStages > 0 ? round(($completedStages / $totalStages) * 100, 2) : 0
            ]
        ]);
    }
}