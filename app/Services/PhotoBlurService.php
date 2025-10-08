<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PhotoBlurService
{
    /**
     * Create a blurred version of an image using GD library
     */
    public static function createBlurredImage($originalImagePath, $blurIntensity = 15)
    {
        try {
            // Check if GD extension is loaded
            if (!extension_loaded('gd')) {
                throw new \Exception('GD extension is not loaded');
            }
            
            // Get image info
            $imageInfo = getimagesize($originalImagePath);
            if (!$imageInfo) {
                throw new \Exception('Invalid image file');
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];
            
            // Create image resource based on type
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($originalImagePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($originalImagePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($originalImagePath);
                    break;
                default:
                    throw new \Exception('Unsupported image type');
            }
            
            if (!$sourceImage) {
                throw new \Exception('Failed to create image resource');
            }
            
            // Create blurred image
            $blurredImage = self::applyBlurEffect($sourceImage, $blurIntensity);
            
            // Generate blurred image path
            $pathInfo = pathinfo($originalImagePath);
            $blurredPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_blurred.' . $pathInfo['extension'];
            
            // Save blurred image
            $success = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $success = imagejpeg($blurredImage, $blurredPath, 90);
                    break;
                case IMAGETYPE_PNG:
                    $success = imagepng($blurredImage, $blurredPath, 9);
                    break;
                case IMAGETYPE_GIF:
                    $success = imagegif($blurredImage, $blurredPath);
                    break;
            }
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($blurredImage);
            
            if (!$success) {
                throw new \Exception('Failed to save blurred image');
            }
            
            return $blurredPath;
            
        } catch (\Exception $e) {
            \Log::error('Error creating blurred image: ' . $e->getMessage());
            throw new \Exception('خطا در ایجاد عکس تار: ' . $e->getMessage());
        }
    }
    
    /**
     * Apply blur effect using GD library
     */
    private static function applyBlurEffect($image, $intensity)
    {
        // Create a copy of the image
        $blurred = imagecreatetruecolor(imagesx($image), imagesy($image));
        
        // Apply multiple blur passes for better effect
        for ($i = 0; $i < $intensity; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }
        
        // Copy the blurred image
        imagecopy($blurred, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        
        return $blurred;
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
