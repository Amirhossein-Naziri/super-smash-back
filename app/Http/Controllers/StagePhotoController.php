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
     * Debug getNextIncompleteStage method
     */
    public function debugNextStage(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            $userId = $user->id;
            
            // Test each step
            $userProgressCount = UserStageProgress::where('user_id', $userId)->count();
            $firstStage = Stage::orderBy('stage_number')->first();
            $allStages = Stage::orderBy('stage_number')->get();
            
            $nextStage = UserStageProgress::getNextIncompleteStage($userId);
            
            // Additional debugging
            $completedStages = UserStageProgress::where('user_id', $userId)
                                              ->where('stage_completed', true)
                                              ->pluck('stage_id')
                                              ->toArray();

            return response()->json([
                'user_id' => $userId,
                'user_progress_count' => $userProgressCount,
                'first_stage' => $firstStage ? [
                    'id' => $firstStage->id,
                    'stage_number' => $firstStage->stage_number,
                    'points' => $firstStage->points
                ] : null,
                'all_stages' => $allStages->map(function($stage) {
                    return [
                        'id' => $stage->id,
                        'stage_number' => $stage->stage_number,
                        'points' => $stage->points
                    ];
                }),
                'completed_stages' => $completedStages,
                'next_stage_result' => $nextStage ? [
                    'id' => $nextStage->id,
                    'stage_number' => $nextStage->stage_number,
                    'points' => $nextStage->points
                ] : null,
                'debug' => [
                    'method_logic' => $userProgressCount === 0 ? 'Return first stage' : 'Check completed stages',
                    'stages_count' => $allStages->count(),
                    'should_return_first' => $userProgressCount === 0 && $firstStage !== null,
                    'should_return_null' => $userProgressCount > 0 && count($completedStages) >= $allStages->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Debug failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test database connection and data
     */
    public function testDatabase(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            // Test database connection
            $dbTest = \DB::connection()->getPdo();
            $dbStatus = $dbTest ? 'Connected' : 'Failed';

            // Count records in each table
            $stagesCount = Stage::count();
            $photosCount = StagePhoto::count();
            $progressCount = UserStageProgress::count();
            $recordingsCount = UserVoiceRecording::count();
            $usersCount = \App\Models\User::count();

            // Get sample data
            $sampleStage = Stage::first();
            $samplePhoto = StagePhoto::first();
            $userProgress = UserStageProgress::where('user_id', $user->id)->first();

            return response()->json([
                'database_status' => $dbStatus,
                'table_counts' => [
                    'stages' => $stagesCount,
                    'stage_photos' => $photosCount,
                    'user_stage_progress' => $progressCount,
                    'user_voice_recordings' => $recordingsCount,
                    'users' => $usersCount
                ],
                'sample_data' => [
                    'stage' => $sampleStage ? [
                        'id' => $sampleStage->id,
                        'stage_number' => $sampleStage->stage_number,
                        'points' => $sampleStage->points
                    ] : null,
                    'photo' => $samplePhoto ? [
                        'id' => $samplePhoto->id,
                        'stage_id' => $samplePhoto->stage_id,
                        'photo_order' => $samplePhoto->photo_order
                    ] : null,
                    'user_progress' => $userProgress ? [
                        'id' => $userProgress->id,
                        'stage_id' => $userProgress->stage_id,
                        'unlocked_photos_count' => $userProgress->unlocked_photos_count
                    ] : null
                ],
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'telegram_id' => $user->telegram_id
                ],
                'next_stage_test' => UserStageProgress::getNextIncompleteStage($user->id) ? 'Found' : 'Not Found'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Database test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Create a test stage with photos
     */
    public function createTestStage(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            // Create test stage
            $stage = Stage::create([
                'stage_number' => Stage::getHighestStageNumber() + 1,
                'points' => 100,
                'is_completed' => false
            ]);

            // Create 6 test photos for this stage
            $photos = [];
            for ($i = 1; $i <= 6; $i++) {
                $codes = StagePhoto::generateUniqueCodes();
                $photo = StagePhoto::create([
                    'stage_id' => $stage->id,
                    'image_path' => "test/original_photo_{$i}.jpg",
                    'blurred_image_path' => "test/blurred_photo_{$i}.jpg",
                    'photo_order' => $i,
                    'code_1' => $codes[0],
                    'code_2' => $codes[1],
                    'is_unlocked' => false
                ]);
                $photos[] = $photo;
            }

            return response()->json([
                'message' => 'Test stage created successfully',
                'stage' => [
                    'id' => $stage->id,
                    'stage_number' => $stage->stage_number,
                    'points' => $stage->points
                ],
                'photos' => $photos->map(function($photo) {
                    return [
                        'id' => $photo->id,
                        'photo_order' => $photo->photo_order,
                        'code_1' => $photo->code_1,
                        'code_2' => $photo->code_2
                    ];
                }),
                'debug' => [
                    'total_stages' => Stage::count(),
                    'total_photos' => StagePhoto::count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create test stage: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function getCurrentStagePhotos(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'کاربر احراز هویت نشده'], 401);
            }

            // Check if there are any stages in the database
            $totalStages = Stage::count();
            if ($totalStages === 0) {
                return response()->json([
                    'error' => 'هیچ مرحله‌ای در سیستم وجود ندارد',
                    'debug' => 'No stages found in database'
                ], 404);
            }

            // Get next incomplete stage for user
            $stage = UserStageProgress::getNextIncompleteStage($user->id);
            
            if (!$stage) {
                // Check if user has any progress at all
                $userProgressCount = UserStageProgress::where('user_id', $user->id)->count();
                if ($userProgressCount === 0) {
                    // User has no progress, but stages exist - this shouldn't happen
                    // Return the first stage as fallback
                    $stage = Stage::orderBy('stage_number')->first();
                    if (!$stage) {
                        return response()->json([
                            'error' => 'خطا در سیستم: هیچ مرحله‌ای یافت نشد',
                            'debug' => 'Fallback failed - no stages exist'
                        ], 500);
                    }
                } else {
                    // User has progress but all stages are completed
                    return response()->json([
                        'message' => 'همه مراحل تکمیل شده‌اند',
                        'debug' => 'All stages completed for user',
                        'total_stages' => $totalStages,
                        'user_progress_count' => $userProgressCount
                    ], 200);
                }
            }

            // Check if stage has photos
            $photosCount = StagePhoto::where('stage_id', $stage->id)->count();
            if ($photosCount === 0) {
                return response()->json([
                    'error' => 'مرحله ' . $stage->stage_number . ' عکسی ندارد',
                    'debug' => 'Stage ' . $stage->id . ' has no photos',
                    'stage_id' => $stage->id
                ], 404);
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
                ],
                'debug' => [
                    'total_stages' => $totalStages,
                    'photos_count' => $photos->count(),
                    'user_id' => $user->id,
                    'stage_id' => $stage->id,
                    'user_progress_count' => UserStageProgress::where('user_id', $user->id)->count(),
                    'method_result' => 'Success - stage found'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting current stage photos', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'خطا در دریافت عکس‌های مرحله',
                'debug' => 'Exception: ' . $e->getMessage()
            ], 500);
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
