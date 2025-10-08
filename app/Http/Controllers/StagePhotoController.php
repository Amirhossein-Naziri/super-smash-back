<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stage;
use App\Models\StagePhoto;
use App\Models\UserStageProgress;
use App\Models\UserVoiceRecording;
use App\Services\VoiceRecordingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StagePhotoController extends Controller
{
    /**
     * Get current stage photos for user
     */
    public function getCurrentStagePhotos(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            // Get next incomplete stage for user
            $stage = UserStageProgress::getNextIncompleteStage($user->id);
            
            if (!$stage) {
                return response()->json(['message' => 'همه مراحل تکمیل شده‌اند'], 200);
            }

            // Get photos for this stage
            $photos = StagePhoto::getPhotosForStage($stage->id);
            
            // Get user progress for this stage
            $progress = UserStageProgress::getOrCreateProgress($user->id, $stage->id);
            
            // Get user's voice recordings for this stage
            $voiceRecordings = UserVoiceRecording::getRecordingsForUserStage($user->id, $stage->id);
            
            $photosData = $photos->map(function($photo) use ($progress, $voiceRecordings) {
                $hasRecording = $voiceRecordings->where('stage_photo_id', $photo->id)->isNotEmpty();
                
                return [
                    'id' => $photo->id,
                    'photo_order' => $photo->photo_order,
                    'image_url' => Storage::disk('public')->url($photo->blurred_image_path),
                    'is_unlocked' => $photo->is_unlocked,
                    'has_voice_recording' => $hasRecording,
                    'needs_codes' => !$photo->is_unlocked
                ];
            });

            return response()->json([
                'stage' => [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points
                ],
                'photos' => $photosData,
                'progress' => [
                    'unlocked_photos_count' => $progress->unlocked_photos_count,
                    'completed_voice_recordings' => $progress->completed_voice_recordings,
                    'stage_completed' => $progress->stage_completed
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting current stage photos', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در دریافت عکس‌های مرحله'], 500);
        }
    }

    /**
     * Unlock photo with codes
     */
    public function unlockPhoto(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            $request->validate([
                'photo_id' => 'required|exists:stage_photos,id',
                'code_1' => 'required|string|size:6',
                'code_2' => 'required|string|size:6'
            ]);

            $photo = StagePhoto::find($request->photo_id);
            
            // Check if photo is already unlocked
            if ($photo->is_unlocked) {
                return response()->json(['message' => 'این عکس قبلاً باز شده است'], 200);
            }

            // Validate codes
            if (!$photo->validateCodes($request->code_1, $request->code_2)) {
                return response()->json(['error' => 'کدهای وارد شده اشتباه است'], 400);
            }

            // Unlock photo
            $photo->is_unlocked = true;
            $photo->save();

            // Update user progress
            $progress = UserStageProgress::getOrCreateProgress($user->id, $photo->stage_id);
            $unlockedCount = StagePhoto::where('stage_id', $photo->stage_id)
                                      ->where('is_unlocked', true)
                                      ->count();
            $progress->updateUnlockedPhotos($unlockedCount);

            return response()->json([
                'message' => 'عکس با موفقیت باز شد!',
                'unlocked_image_url' => Storage::disk('public')->url($photo->image_path),
                'progress' => [
                    'unlocked_photos_count' => $progress->unlocked_photos_count,
                    'completed_voice_recordings' => $progress->completed_voice_recordings
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error unlocking photo', [
                'user_id' => Auth::id(),
                'photo_id' => $request->photo_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در باز کردن عکس'], 500);
        }
    }

    /**
     * Upload voice recording
     */
    public function uploadVoiceRecording(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            $request->validate([
                'photo_id' => 'required|exists:stage_photos,id',
                'audio_file' => 'required|file|mimes:webm,mp3,wav|max:10240' // 10MB max
            ]);

            $photo = StagePhoto::find($request->photo_id);
            
            // Check if photo is unlocked
            if (!$photo->is_unlocked) {
                return response()->json(['error' => 'ابتدا باید عکس را با کد باز کنید'], 400);
            }

            // Check if user already has a recording for this photo
            $existingRecording = UserVoiceRecording::where('user_id', $user->id)
                                                   ->where('stage_photo_id', $photo->id)
                                                   ->first();
            
            if ($existingRecording) {
                return response()->json(['error' => 'شما قبلاً برای این عکس ضبط صوتی ارسال کرده‌اید'], 400);
            }

            // Save voice recording
            $audioFile = $request->file('audio_file');
            $audioContent = file_get_contents($audioFile->getPathname());
            
            $voiceFilePath = VoiceRecordingService::saveVoiceRecording($audioContent, $user->id, $photo->id);
            
            // Create voice recording record
            $recording = VoiceRecordingService::createVoiceRecording(
                $user->id, 
                $photo->id, 
                $voiceFilePath, 
                40 // 40 seconds duration
            );

            // Get updated progress
            $progress = UserStageProgress::getOrCreateProgress($user->id, $photo->stage_id);
            
            return response()->json([
                'message' => 'ضبط صوتی با موفقیت ذخیره شد!',
                'recording_id' => $recording->id,
                'progress' => [
                    'unlocked_photos_count' => $progress->unlocked_photos_count,
                    'completed_voice_recordings' => $progress->completed_voice_recordings,
                    'stage_completed' => $progress->stage_completed
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error uploading voice recording', [
                'user_id' => Auth::id(),
                'photo_id' => $request->photo_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در آپلود ضبط صوتی'], 500);
        }
    }

    /**
     * Get stage completion status
     */
    public function getStageCompletionStatus(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            $request->validate([
                'stage_id' => 'required|exists:stages,id'
            ]);

            $stageId = $request->stage_id;
            $progress = UserStageProgress::getOrCreateProgress($user->id, $stageId);
            
            $totalPhotos = StagePhoto::where('stage_id', $stageId)->count();
            $completedRecordings = UserVoiceRecording::where('user_id', $user->id)
                                                     ->whereHas('stagePhoto', function($query) use ($stageId) {
                                                         $query->where('stage_id', $stageId);
                                                     })
                                                     ->where('is_completed', true)
                                                     ->count();

            return response()->json([
                'stage_id' => $stageId,
                'total_photos' => $totalPhotos,
                'unlocked_photos_count' => $progress->unlocked_photos_count,
                'completed_voice_recordings' => $completedRecordings,
                'stage_completed' => $progress->stage_completed,
                'can_proceed_to_next_stage' => $progress->stage_completed
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting stage completion status', [
                'user_id' => Auth::id(),
                'stage_id' => $request->stage_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در دریافت وضعیت مرحله'], 500);
        }
    }
}
