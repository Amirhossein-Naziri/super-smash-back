<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PhotoBlurService
{
    /**
     * Create a blurred version of an image
     */
    public static function createBlurredImage($originalImagePath, $blurIntensity = 15)
    {
        try {
            // Create ImageManager instance
            $manager = new ImageManager(new Driver());
            
            // Load the original image
            $image = $manager->read($originalImagePath);
            
            // Apply blur effect
            $image->blur($blurIntensity);
            
            // Generate blurred image path
            $pathInfo = pathinfo($originalImagePath);
            $blurredPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_blurred.' . $pathInfo['extension'];
            
            // Save blurred image
            $image->save($blurredPath);
            
            return $blurredPath;
            
        } catch (\Exception $e) {
            \Log::error('Error creating blurred image: ' . $e->getMessage());
            throw new \Exception('خطا در ایجاد عکس تار: ' . $e->getMessage());
        }
    }

    /**
     * Process uploaded photo and create both original and blurred versions
     */
    public static function processUploadedPhoto($imageContent, $stageId, $photoOrder)
    {
        try {
            // Generate unique filename
            $fileName = 'stage_' . $stageId . '_photo_' . $photoOrder . '_' . time() . '.jpg';
            $originalPath = 'stage_photos/' . $fileName;
            
            // Save original image
            $saved = Storage::disk('public')->put($originalPath, $imageContent);
            if (!$saved) {
                throw new \Exception('خطا در ذخیره عکس اصلی');
            }
            
            // Get full path for processing
            $fullOriginalPath = Storage::disk('public')->path($originalPath);
            
            // Create blurred version
            $blurredPath = self::createBlurredImage($fullOriginalPath);
            
            // Get relative path for blurred image
            $blurredRelativePath = 'stage_photos/' . pathinfo($fileName, PATHINFO_FILENAME) . '_blurred.jpg';
            
            // Move blurred image to storage
            $blurredContent = file_get_contents($blurredPath);
            Storage::disk('public')->put($blurredRelativePath, $blurredContent);
            
            // Clean up temporary file
            unlink($blurredPath);
            
            return [
                'original_path' => $originalPath,
                'blurred_path' => $blurredRelativePath
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error processing uploaded photo: ' . $e->getMessage());
            throw new \Exception('خطا در پردازش عکس: ' . $e->getMessage());
        }
    }

    /**
     * Get image URL for frontend
     */
    public static function getImageUrl($path)
    {
        return Storage::disk('public')->url($path);
    }
}
