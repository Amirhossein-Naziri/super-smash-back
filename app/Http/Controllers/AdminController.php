<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserVoiceRecording;
use App\Models\StagePhoto;
use App\Models\UserStageProgress;

class AdminController extends Controller
{
    /**
     * Get all stages with statistics
     */
    public function getStages()
    {
        try {
            $stages = Stage::with(['photos', 'userProgress'])
                          ->orderBy('stage_number')
                          ->get();

            $stagesData = $stages->map(function($stage) {
                $totalPhotos = $stage->photos->count();
                $usersWithRecordings = UserVoiceRecording::whereHas('stagePhoto', function($query) use ($stage) {
                    $query->where('stage_id', $stage->id);
                })->distinct('user_id')->count();

                return [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points,
                    'total_photos' => $totalPhotos,
                    'users_with_recordings' => $usersWithRecordings,
                    'is_completed' => $stage->is_completed
                ];
            });

            return response()->json([
                'stages' => $stagesData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting stages for admin', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در دریافت مراحل'], 500);
        }
    }

    /**
     * Get users with voice recordings for a specific stage
     */
    public function getStageUsers($stageId)
    {
        try {
            $stage = Stage::findOrFail($stageId);
            
            // Get users who have voice recordings for this stage
            $users = User::whereHas('voiceRecordings', function($query) use ($stageId) {
                $query->whereHas('stagePhoto', function($subQuery) use ($stageId) {
                    $subQuery->where('stage_id', $stageId);
                });
            })->with(['voiceRecordings' => function($query) use ($stageId) {
                $query->whereHas('stagePhoto', function($subQuery) use ($stageId) {
                    $subQuery->where('stage_id', $stageId);
                })->with('stagePhoto');
            }])->get();

            $usersData = $users->map(function($user) use ($stageId) {
                $recordings = $user->voiceRecordings->where('stagePhoto.stage_id', $stageId);
                $totalPhotos = StagePhoto::where('stage_id', $stageId)->count();
                $completedRecordings = $recordings->where('is_completed', true)->count();
                
                // Check if user has completed all recordings for this stage
                $isCompleted = $completedRecordings >= $totalPhotos;
                
                return [
                    'id' => $user->id,
                    'telegram_user_id' => $user->telegram_user_id,
                    'telegram_username' => $user->telegram_username,
                    'telegram_first_name' => $user->telegram_first_name,
                    'telegram_last_name' => $user->telegram_last_name,
                    'completed_recordings' => $completedRecordings,
                    'total_photos' => $totalPhotos,
                    'is_completed' => $isCompleted,
                    'recordings_count' => $recordings->count()
                ];
            });

            return response()->json([
                'stage' => [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points
                ],
                'users' => $usersData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting stage users', [
                'stage_id' => $stageId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در دریافت کاربران مرحله'], 500);
        }
    }

    /**
     * Get voice recordings for a specific user and stage
     */
    public function getUserStageRecordings($stageId, $userId)
    {
        try {
            $stage = Stage::findOrFail($stageId);
            $user = User::findOrFail($userId);

            // Get all recordings for this user and stage
            $recordings = UserVoiceRecording::where('user_id', $userId)
                                          ->whereHas('stagePhoto', function($query) use ($stageId) {
                                              $query->where('stage_id', $stageId);
                                          })
                                          ->with('stagePhoto')
                                          ->orderBy('stagePhoto.photo_order')
                                          ->get();

            $recordingsData = $recordings->map(function($recording) {
                return [
                    'id' => $recording->id,
                    'photo_order' => $recording->stagePhoto->photo_order,
                    'voice_file_path' => $recording->voice_file_path,
                    'voice_file_url' => Storage::disk('public')->url($recording->voice_file_path),
                    'duration_seconds' => $recording->duration_seconds,
                    'is_completed' => $recording->is_completed,
                    'created_at' => $recording->created_at
                ];
            });

            return response()->json([
                'stage' => [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points
                ],
                'user' => [
                    'id' => $user->id,
                    'telegram_user_id' => $user->telegram_user_id,
                    'telegram_username' => $user->telegram_username,
                    'telegram_first_name' => $user->telegram_first_name,
                    'telegram_last_name' => $user->telegram_last_name
                ],
                'recordings' => $recordingsData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting user stage recordings', [
                'stage_id' => $stageId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در دریافت ضبط‌های صوتی'], 500);
        }
    }

    /**
     * Get combined voice recording for a user and stage
     */
    public function getCombinedVoiceRecording($stageId, $userId)
    {
        try {
            $stage = Stage::findOrFail($stageId);
            $user = User::findOrFail($userId);

            // Get all recordings for this user and stage
            $recordings = UserVoiceRecording::where('user_id', $userId)
                                          ->whereHas('stagePhoto', function($query) use ($stageId) {
                                              $query->where('stage_id', $stageId);
                                          })
                                          ->where('is_completed', true)
                                          ->with('stagePhoto')
                                          ->orderBy('stagePhoto.photo_order')
                                          ->get();

            if ($recordings->isEmpty()) {
                return response()->json(['error' => 'هیچ ضبط صوتی کامل‌شده‌ای یافت نشد'], 404);
            }

            // Calculate total duration
            $totalDuration = $recordings->sum('duration_seconds');
            
            // Create a combined audio file (this is a simplified approach)
            // In a real implementation, you might want to use FFmpeg to concatenate audio files
            $combinedFileName = 'combined_' . $userId . '_stage_' . $stageId . '_' . time() . '.webm';
            $combinedFilePath = 'voice_recordings/combined/' . $combinedFileName;

            // For now, we'll return the first recording as a placeholder
            // In production, you'd want to actually concatenate the audio files
            $firstRecording = $recordings->first();
            
            return response()->json([
                'stage' => [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points
                ],
                'user' => [
                    'id' => $user->id,
                    'telegram_user_id' => $user->telegram_user_id,
                    'telegram_username' => $user->telegram_username,
                    'telegram_first_name' => $user->telegram_first_name,
                    'telegram_last_name' => $user->telegram_last_name
                ],
                'combined_recording' => [
                    'total_recordings' => $recordings->count(),
                    'total_duration' => $totalDuration,
                    'voice_file_url' => Storage::disk('public')->url($firstRecording->voice_file_path),
                    'recordings' => $recordings->map(function($recording) {
                        return [
                            'photo_order' => $recording->stagePhoto->photo_order,
                            'voice_file_url' => Storage::disk('public')->url($recording->voice_file_path),
                            'duration_seconds' => $recording->duration_seconds
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting combined voice recording', [
                'stage_id' => $stageId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در دریافت ضبط صوتی ترکیبی'], 500);
        }
    }
}
