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
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
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
                'telegram_user_id' => $telegramUserId,
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
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
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
                    'telegram_id' => $user->telegram_user_id
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
     * Debug photo codes
     */
    public function debugPhotoCodes(Request $request)
    {
        try {
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
            }

            // Get next incomplete stage for user
            $stage = UserStageProgress::getNextIncompleteStage($user->id);
            
            if (!$stage) {
                return response()->json(['error' => 'مرحله‌ای یافت نشد'], 404);
            }

            // Get photos for this stage
            $photos = StagePhoto::getPhotosForStage($stage->id);
            
            $photosData = $photos->map(function($photo) {
                return [
                    'id' => $photo->id,
                    'photo_order' => $photo->photo_order,
                    'code_1' => $photo->code_1,
                    'code_2' => $photo->code_2,
                    'is_unlocked' => $photo->is_unlocked
                ];
            });

            return response()->json([
                'stage_id' => $stage->id,
                'stage_number' => $stage->stage_number,
                'photos' => $photosData,
                'debug_info' => [
                    'user_id' => $user->id,
                    'telegram_user_id' => $telegramUserId,
                    'total_photos' => $photos->count()
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
     * Get raw database codes for debugging
     */
    public function getRawDatabaseCodes(Request $request)
    {
        try {
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
            }

            $stage = UserStageProgress::getNextIncompleteStage($user->id);
            
            if (!$stage) {
                return response()->json(['error' => 'مرحله‌ای یافت نشد'], 404);
            }

            // Get raw data from database
            $photos = \DB::table('stage_photos')
                ->where('stage_id', $stage->id)
                ->orderBy('photo_order')
                ->get();

            $rawData = [];
            foreach ($photos as $photo) {
                $rawData[] = [
                    'id' => $photo->id,
                    'photo_order' => $photo->photo_order,
                    'code_1_raw' => $photo->code_1,
                    'code_2_raw' => $photo->code_2,
                    'code_1_length' => strlen($photo->code_1),
                    'code_2_length' => strlen($photo->code_2),
                    'code_1_bytes' => array_values(unpack('C*', $photo->code_1)),
                    'code_2_bytes' => array_values(unpack('C*', $photo->code_2)),
                    'code_1_hex' => bin2hex($photo->code_1),
                    'code_2_hex' => bin2hex($photo->code_2),
                    'is_unlocked' => $photo->is_unlocked
                ];
            }

            return response()->json([
                'stage_id' => $stage->id,
                'stage_number' => $stage->stage_number,
                'raw_photos' => $rawData,
                'debug_info' => [
                    'user_id' => $user->id,
                    'telegram_user_id' => $telegramUserId,
                    'total_photos' => count($rawData)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Raw data failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test code validation directly
     */
    public function testCodeValidation(Request $request)
    {
        try {
            $telegramUserId = $request->input('telegram_user_id');
            $photoId = $request->input('photo_id');
            $code1 = $request->input('code_1');
            $code2 = $request->input('code_2');
            
            if (!$telegramUserId || !$photoId || !$code1 || !$code2) {
                return response()->json(['error' => 'تمام پارامترها الزامی است'], 400);
            }

            $photo = StagePhoto::find($photoId);
            if (!$photo) {
                return response()->json(['error' => 'عکس یافت نشد'], 404);
            }

            // Clean and normalize codes
            $inputCode1 = strtoupper(trim($code1));
            $inputCode2 = strtoupper(trim($code2));
            $storedCode1 = strtoupper(trim($photo->code_1));
            $storedCode2 = strtoupper(trim($photo->code_2));

            // Test different validation methods
            $method1 = ($storedCode1 === $inputCode1 && $storedCode2 === $inputCode2);
            $method2 = ($storedCode1 === $inputCode2 && $storedCode2 === $inputCode1);
            $method3 = $photo->validateCodes($code1, $code2);

            return response()->json([
                'photo_id' => $photo->id,
                'photo_order' => $photo->photo_order,
                'input_codes' => [
                    'raw_1' => $code1,
                    'raw_2' => $code2,
                    'processed_1' => $inputCode1,
                    'processed_2' => $inputCode2,
                    'length_1' => strlen($inputCode1),
                    'length_2' => strlen($inputCode2)
                ],
                'stored_codes' => [
                    'raw_1' => $photo->code_1,
                    'raw_2' => $photo->code_2,
                    'processed_1' => $storedCode1,
                    'processed_2' => $storedCode2,
                    'length_1' => strlen($storedCode1),
                    'length_2' => strlen($storedCode2)
                ],
                'validation_results' => [
                    'method_1' => $method1,
                    'method_2' => $method2,
                    'method_3' => $method3,
                    'final_result' => $method1 || $method2
                ],
                'debug' => [
                    'code_1_match' => $storedCode1 === $inputCode1,
                    'code_2_match' => $storedCode2 === $inputCode2,
                    'code_1_swap_match' => $storedCode1 === $inputCode2,
                    'code_2_swap_match' => $storedCode2 === $inputCode1
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Regenerate codes for all photos in current stage
     */
    public function regenerateCodes(Request $request)
    {
        try {
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
            }

            $stage = UserStageProgress::getNextIncompleteStage($user->id);
            
            if (!$stage) {
                return response()->json(['error' => 'مرحله‌ای یافت نشد'], 404);
            }

            $photos = StagePhoto::getPhotosForStage($stage->id);
            $regeneratedCodes = [];

            foreach ($photos as $photo) {
                $oldCode1 = $photo->code_1;
                $oldCode2 = $photo->code_2;
                
                // Generate new codes
                $newCodes = StagePhoto::generateUniqueCodes();
                
                $photo->code_1 = $newCodes[0];
                $photo->code_2 = $newCodes[1];
                $photo->is_unlocked = false; // Reset unlock status
                $photo->save();
                
                $regeneratedCodes[] = [
                    'photo_id' => $photo->id,
                    'photo_order' => $photo->photo_order,
                    'old_code_1' => $oldCode1,
                    'old_code_2' => $oldCode2,
                    'new_code_1' => $newCodes[0],
                    'new_code_2' => $newCodes[1]
                ];
            }

            return response()->json([
                'message' => 'کدها با موفقیت بازتولید شدند',
                'stage_id' => $stage->id,
                'stage_number' => $stage->stage_number,
                'regenerated_codes' => $regeneratedCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Regenerate failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Clean existing codes in database
     */
    public function cleanExistingCodes(Request $request)
    {
        try {
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
            }

            $stage = UserStageProgress::getNextIncompleteStage($user->id);
            
            if (!$stage) {
                return response()->json(['error' => 'مرحله‌ای یافت نشد'], 404);
            }

            $photos = StagePhoto::getPhotosForStage($stage->id);
            $cleanedCodes = [];

            foreach ($photos as $photo) {
                $oldCode1 = $photo->code_1;
                $oldCode2 = $photo->code_2;
                
                // Clean codes - convert to lowercase
                $cleanCode1 = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $photo->code_1)));
                $cleanCode2 = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $photo->code_2)));
                
                // Ensure codes are exactly 6 characters
                if (strlen($cleanCode1) < 6) {
                    $cleanCode1 = str_pad($cleanCode1, 6, '0', STR_PAD_RIGHT);
                }
                if (strlen($cleanCode2) < 6) {
                    $cleanCode2 = str_pad($cleanCode2, 6, '0', STR_PAD_RIGHT);
                }
                
                $photo->code_1 = $cleanCode1;
                $photo->code_2 = $cleanCode2;
                $photo->save();
                
                $cleanedCodes[] = [
                    'photo_id' => $photo->id,
                    'photo_order' => $photo->photo_order,
                    'old_code_1' => $oldCode1,
                    'old_code_2' => $oldCode2,
                    'new_code_1' => $cleanCode1,
                    'new_code_2' => $cleanCode2
                ];
            }

            return response()->json([
                'message' => 'کدها با موفقیت تمیز شدند',
                'stage_id' => $stage->id,
                'stage_number' => $stage->stage_number,
                'cleaned_codes' => $cleanedCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Clean failed: ' . $e->getMessage(),
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
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
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
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
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
            
            $photosData = $photos->map(function($photo) use ($progress, $voiceRecordings, $user) {
                $hasRecording = $voiceRecordings->where('stage_photo_id', $photo->id)->isNotEmpty();
                
                // Check if this specific user has unlocked this photo
                $userUnlockedPhoto = \App\Models\UserUnlockedPhoto::where('user_id', $user->id)
                                                                 ->where('stage_photo_id', $photo->id)
                                                                 ->first();
                
                $isUnlockedByUser = $userUnlockedPhoto && !$userUnlockedPhoto->is_partial_unlock;
                $isPartiallyUnlockedByUser = $userUnlockedPhoto && $userUnlockedPhoto->is_partial_unlock;
                
                // Use original image if fully unlocked, blurred image if locked, original image if partially unlocked
                $imageUrl = $isUnlockedByUser || $isPartiallyUnlockedByUser
                    ? Storage::disk('public')->url($photo->image_path)
                    : Storage::disk('public')->url($photo->blurred_image_path);
                
                return [
                    'id' => $photo->id,
                    'photo_order' => $photo->photo_order,
                    'image_url' => $imageUrl,
                    'is_unlocked' => $isUnlockedByUser,
                    'partially_unlocked' => $isPartiallyUnlockedByUser,
                    'has_voice_recording' => $hasRecording,
                    'needs_codes' => !$isUnlockedByUser
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
                    'total_photos' => $photosCount,
                    'completed_voice_recordings' => $progress->completed_voice_recordings,
                    'stage_completed' => $progress->stage_completed
                ],
                'debug' => [
                    'total_stages' => $totalStages,
                    'photos_count' => $photos->count(),
                    'user_id' => $user->id,
                    'telegram_user_id' => $telegramUserId,
                    'stage_id' => $stage->id,
                    'user_progress_count' => UserStageProgress::where('user_id', $user->id)->count(),
                    'method_result' => 'Success - stage found'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting current stage photos', [
                'telegram_user_id' => $request->input('telegram_user_id'),
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
     * Partially unlock photo with first code
     */
    public function partiallyUnlockPhoto(Request $request)
    {
        try {
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
            }

            $request->validate([
                'photo_id' => 'required|exists:stage_photos,id',
                'code' => 'required|string|size:6'
            ]);

            $photo = StagePhoto::find($request->photo_id);
            
            // Check if photo is already fully unlocked
            if ($photo->is_unlocked) {
                return response()->json(['message' => 'این عکس قبلاً کاملاً باز شده است'], 200);
            }

            // Clean and normalize code
            $inputCode = strtolower(trim(preg_replace('/\s+/', '', $request->code)));

            // Debug: Log the code validation attempt
            \Log::info('PARTIAL UNLOCK - Code validation attempt', [
                'photo_id' => $photo->id,
                'photo_order' => $photo->photo_order,
                'raw_input_code' => $request->code,
                'cleaned_input_code' => $inputCode,
                'input_code_length' => strlen($inputCode),
                'telegram_user_id' => $telegramUserId,
                'user_id' => $user->id,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Validate code using the global codes table
            $codeValidation = \App\Models\Code::validateAndUseCode($inputCode, $user->id);
            
            if (!$codeValidation['success']) {
                \Log::warning('PARTIAL UNLOCK - Code validation failed', [
                    'photo_id' => $photo->id,
                    'input_code' => $inputCode,
                    'telegram_user_id' => $telegramUserId,
                    'error_message' => $codeValidation['message'],
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                return response()->json([
                    'error' => $codeValidation['message']
                ], 400);
            }

            // Record user partial unlock (don't unlock globally)
            \App\Models\UserUnlockedPhoto::recordPartialUnlock($user->id, $photo->id);

            // Update user progress based on user's unlocked photos
            $progress = UserStageProgress::getOrCreateProgress($user->id, $photo->stage_id);
            $userUnlockedCount = \App\Models\UserUnlockedPhoto::where('user_id', $user->id)
                                                             ->whereHas('stagePhoto', function($query) use ($photo) {
                                                                 $query->where('stage_id', $photo->stage_id);
                                                             })
                                                             ->count();
            $progress->updateUnlockedPhotos($userUnlockedCount);

            return response()->json([
                'message' => 'کد اول صحیح است! عکس کمی باز شد.',
                'partially_unlocked_image_url' => Storage::disk('public')->url($photo->image_path),
                'needs_second_code' => true,
                'progress' => $progress
            ]);

        } catch (\Exception $e) {
            \Log::error('Error partially unlocking photo', [
                'telegram_user_id' => $request->input('telegram_user_id'),
                'photo_id' => $request->photo_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در باز کردن جزئی عکس'], 500);
        }
    }

    /**
     * Fully unlock photo with second code
     */
    public function fullyUnlockPhoto(Request $request)
    {
        try {
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
            }

            $request->validate([
                'photo_id' => 'required|exists:stage_photos,id',
                'code' => 'required|string|size:6'
            ]);

            $photo = StagePhoto::find($request->photo_id);
            
            // Check if photo is already fully unlocked
            if ($photo->is_unlocked) {
                return response()->json(['message' => 'این عکس قبلاً کاملاً باز شده است'], 200);
            }

            // Check if photo is partially unlocked
            if (!$photo->partially_unlocked) {
                return response()->json(['error' => 'ابتدا باید کد اول را وارد کنید'], 400);
            }

            // Clean and normalize code
            $inputCode = strtolower(trim(preg_replace('/\s+/', '', $request->code)));

            // Debug: Log the code validation attempt
            \Log::info('FULL UNLOCK - Code validation attempt', [
                'photo_id' => $photo->id,
                'photo_order' => $photo->photo_order,
                'raw_input_code' => $request->code,
                'cleaned_input_code' => $inputCode,
                'input_code_length' => strlen($inputCode),
                'telegram_user_id' => $telegramUserId,
                'user_id' => $user->id,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Validate code using the global codes table
            $codeValidation = \App\Models\Code::validateAndUseCode($inputCode, $user->id);
            
            if (!$codeValidation['success']) {
                \Log::warning('FULL UNLOCK - Code validation failed', [
                    'photo_id' => $photo->id,
                    'input_code' => $inputCode,
                    'telegram_user_id' => $telegramUserId,
                    'error_message' => $codeValidation['message'],
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                return response()->json([
                    'error' => $codeValidation['message']
                ], 400);
            }

            // Record user unlock (don't unlock globally)
            \App\Models\UserUnlockedPhoto::recordUnlock($user->id, $photo->id);

            // Update user progress based on user's unlocked photos
            $progress = UserStageProgress::getOrCreateProgress($user->id, $photo->stage_id);
            $userUnlockedCount = \App\Models\UserUnlockedPhoto::where('user_id', $user->id)
                                                              ->whereHas('stagePhoto', function($query) use ($photo) {
                                                                  $query->where('stage_id', $photo->stage_id);
                                                              })
                                                              ->count();
            $progress->updateUnlockedPhotos($userUnlockedCount);

            $totalPhotos = StagePhoto::where('stage_id', $photo->stage_id)->count();
            
            return response()->json([
                'message' => 'عکس با موفقیت کاملاً باز شد!',
                'unlocked_image_url' => Storage::disk('public')->url($photo->image_path),
                'progress' => [
                    'unlocked_photos_count' => $progress->unlocked_photos_count,
                    'total_photos' => $totalPhotos,
                    'completed_voice_recordings' => $progress->completed_voice_recordings
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fully unlocking photo', [
                'telegram_user_id' => $request->input('telegram_user_id'),
                'photo_id' => $request->photo_id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'خطا در باز کردن کامل عکس'], 500);
        }
    }

    /**
     * Unlock photo with codes (legacy method - kept for backward compatibility)
     */
    public function unlockPhoto(Request $request)
    {
        try {
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
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

            // Clean and normalize codes
            $inputCode1 = strtolower(trim(preg_replace('/\s+/', '', $request->code_1)));
            $inputCode2 = strtolower(trim(preg_replace('/\s+/', '', $request->code_2)));

            // Debug: Log the codes for troubleshooting
            \Log::info('LEGACY UNLOCK - Code validation attempt', [
                'photo_id' => $photo->id,
                'photo_order' => $photo->photo_order,
                'raw_input_code_1' => $request->code_1,
                'raw_input_code_2' => $request->code_2,
                'cleaned_input_code_1' => $inputCode1,
                'cleaned_input_code_2' => $inputCode2,
                'telegram_user_id' => $telegramUserId,
                'user_id' => $user->id,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Validate both codes using the global codes table
            $codeValidation1 = \App\Models\Code::validateAndUseCode($inputCode1, $user->id);
            $codeValidation2 = \App\Models\Code::validateAndUseCode($inputCode2, $user->id);
            
            if (!$codeValidation1['success']) {
                \Log::warning('LEGACY UNLOCK - First code validation failed', [
                    'photo_id' => $photo->id,
                    'input_code_1' => $inputCode1,
                    'error_message' => $codeValidation1['message'],
                    'telegram_user_id' => $telegramUserId,
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                return response()->json([
                    'error' => 'کد اول: ' . $codeValidation1['message']
                ], 400);
            }
            
            if (!$codeValidation2['success']) {
                \Log::warning('LEGACY UNLOCK - Second code validation failed', [
                    'photo_id' => $photo->id,
                    'input_code_2' => $inputCode2,
                    'error_message' => $codeValidation2['message'],
                    'telegram_user_id' => $telegramUserId,
                    'timestamp' => now()->toDateTimeString()
                ]);
                
                return response()->json([
                    'error' => 'کد دوم: ' . $codeValidation2['message']
                ], 400);
            }

            // Record user unlock (don't unlock globally)
            \App\Models\UserUnlockedPhoto::recordUnlock($user->id, $photo->id);

            // Update user progress based on user's unlocked photos
            $progress = UserStageProgress::getOrCreateProgress($user->id, $photo->stage_id);
            $userUnlockedCount = \App\Models\UserUnlockedPhoto::where('user_id', $user->id)
                                                              ->whereHas('stagePhoto', function($query) use ($photo) {
                                                                  $query->where('stage_id', $photo->stage_id);
                                                              })
                                                              ->count();
            $progress->updateUnlockedPhotos($userUnlockedCount);

            $totalPhotos = StagePhoto::where('stage_id', $photo->stage_id)->count();
            
            return response()->json([
                'message' => 'عکس با موفقیت باز شد!',
                'unlocked_image_url' => Storage::disk('public')->url($photo->image_path),
                'progress' => [
                    'unlocked_photos_count' => $progress->unlocked_photos_count,
                    'total_photos' => $totalPhotos,
                    'completed_voice_recordings' => $progress->completed_voice_recordings
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error unlocking photo', [
                'telegram_user_id' => $request->input('telegram_user_id'),
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
            // دریافت telegram_user_id از request
            $telegramUserId = $request->input('telegram_user_id');
            
            if (!$telegramUserId) {
                return response()->json(['error' => 'شناسه کاربر تلگرام الزامی است'], 400);
            }

            // پیدا کردن کاربر بر اساس telegram_user_id
            $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();
            
            if (!$user) {
                return response()->json(['error' => 'کاربر یافت نشد'], 404);
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
            $totalPhotos = StagePhoto::where('stage_id', $photo->stage_id)->count();
            
            return response()->json([
                'message' => 'ضبط صوتی با موفقیت ذخیره شد!',
                'recording_id' => $recording->id,
                'progress' => [
                    'unlocked_photos_count' => $progress->unlocked_photos_count,
                    'total_photos' => $totalPhotos,
                    'completed_voice_recordings' => $progress->completed_voice_recordings,
                    'stage_completed' => $progress->stage_completed
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error uploading voice recording', [
                'telegram_user_id' => $request->input('telegram_user_id'),
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
