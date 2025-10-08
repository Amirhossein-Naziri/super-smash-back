<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Models\UserVoiceRecording;
use App\Models\UserStageProgress;

class VoiceRecordingService
{
    /**
     * Save voice recording file
     */
    public static function saveVoiceRecording($audioContent, $userId, $stagePhotoId)
    {
        try {
            // Generate unique filename
            $fileName = 'voice_' . $userId . '_photo_' . $stagePhotoId . '_' . time() . '.webm';
            $filePath = 'voice_recordings/' . $fileName;
            
            // Save audio file
            $saved = Storage::disk('public')->put($filePath, $audioContent);
            if (!$saved) {
                throw new \Exception('خطا در ذخیره فایل صوتی');
            }
            
            return $filePath;
            
        } catch (\Exception $e) {
            \Log::error('Error saving voice recording: ' . $e->getMessage());
            throw new \Exception('خطا در ذخیره ضبط صوتی: ' . $e->getMessage());
        }
    }

    /**
     * Create voice recording record
     */
    public static function createVoiceRecording($userId, $stagePhotoId, $voiceFilePath, $durationSeconds = 40)
    {
        try {
            $recording = UserVoiceRecording::create([
                'user_id' => $userId,
                'stage_photo_id' => $stagePhotoId,
                'voice_file_path' => $voiceFilePath,
                'duration_seconds' => $durationSeconds,
                'is_completed' => true
            ]);
            
            // Update user progress
            self::updateUserProgress($userId, $stagePhotoId);
            
            return $recording;
            
        } catch (\Exception $e) {
            \Log::error('Error creating voice recording record: ' . $e->getMessage());
            throw new \Exception('خطا در ثبت ضبط صوتی: ' . $e->getMessage());
        }
    }

    /**
     * Update user progress after voice recording
     */
    private static function updateUserProgress($userId, $stagePhotoId)
    {
        try {
            $stagePhoto = \App\Models\StagePhoto::find($stagePhotoId);
            if (!$stagePhoto) {
                throw new \Exception('عکس مرحله یافت نشد');
            }
            
            $progress = UserStageProgress::getOrCreateProgress($userId, $stagePhoto->stage_id);
            
            // Count completed recordings for this stage
            $completedRecordings = UserVoiceRecording::where('user_id', $userId)
                ->whereHas('stagePhoto', function($query) use ($stagePhoto) {
                    $query->where('stage_id', $stagePhoto->stage_id);
                })
                ->where('is_completed', true)
                ->count();
            
            $progress->updateCompletedRecordings($completedRecordings);
            
        } catch (\Exception $e) {
            \Log::error('Error updating user progress: ' . $e->getMessage());
            throw new \Exception('خطا در به‌روزرسانی پیشرفت کاربر: ' . $e->getMessage());
        }
    }

    /**
     * Get voice recording URL
     */
    public static function getVoiceRecordingUrl($filePath)
    {
        return Storage::disk('public')->url($filePath);
    }

    /**
     * Check if user has completed all recordings for a stage
     */
    public static function hasCompletedAllRecordings($userId, $stageId)
    {
        return UserVoiceRecording::hasCompletedAllRecordings($userId, $stageId);
    }
}
