<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpinnerImage;
use App\Models\SpinnerResult;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SpinnerController extends Controller
{
    /**
     * دریافت لیست تصاویر اسپینر برای کاربران
     */
    public function getSpinnerImages()
    {
        $images = SpinnerImage::active()
            ->ordered()
            ->get(['id', 'name', 'image_url', 'image_path']);

        return response()->json([
            'success' => true,
            'images' => $images
        ]);
    }

    /**
     * دریافت لیست کامل تصاویر اسپینر برای ادمین
     */
    public function getAllSpinnerImages()
    {
        $images = SpinnerImage::ordered()->get();

        return response()->json([
            'success' => true,
            'images' => $images
        ]);
    }

    /**
     * اضافه کردن تصویر جدید به اسپینر
     */
    public function addSpinnerImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات وارد شده نامعتبر است',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // آپلود تصویر
            $imagePath = $request->file('image')->store('spinner-images', 'public');
            $imageUrl = Storage::url($imagePath);

            // ایجاد رکورد جدید
            $spinnerImage = SpinnerImage::create([
                'name' => $request->name,
                'image_path' => $imagePath,
                'image_url' => $imageUrl,
                'order' => $request->order ?? 0,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تصویر با موفقیت اضافه شد',
                'image' => $spinnerImage
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در آپلود تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف تصویر از اسپینر
     */
    public function deleteSpinnerImage($id)
    {
        try {
            $spinnerImage = SpinnerImage::findOrFail($id);
            
            // حذف فایل از storage
            if ($spinnerImage->image_path && Storage::disk('public')->exists($spinnerImage->image_path)) {
                Storage::disk('public')->delete($spinnerImage->image_path);
            }

            $spinnerImage->delete();

            return response()->json([
                'success' => true,
                'message' => 'تصویر با موفقیت حذف شد'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * فعال/غیرفعال کردن تصویر
     */
    public function toggleSpinnerImageStatus($id)
    {
        try {
            $spinnerImage = SpinnerImage::findOrFail($id);
            $spinnerImage->is_active = !$spinnerImage->is_active;
            $spinnerImage->save();

            return response()->json([
                'success' => true,
                'message' => $spinnerImage->is_active ? 'تصویر فعال شد' : 'تصویر غیرفعال شد',
                'image' => $spinnerImage
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تغییر وضعیت تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تغییر ترتیب تصاویر
     */
    public function updateImageOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*.id' => 'required|integer',
            'images.*.order' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات وارد شده نامعتبر است',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->images as $imageData) {
                SpinnerImage::where('id', $imageData['id'])
                    ->update(['order' => $imageData['order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'ترتیب تصاویر با موفقیت تغییر کرد'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تغییر ترتیب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * اسپین کردن توسط کاربر
     */
    public function spin(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        // بررسی اینکه آیا کاربر امروز اسپین کرده یا نه
        if (SpinnerResult::hasUserSpunToday($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'شما امروز قبلاً اسپین کرده‌اید. فردا دوباره امتحان کنید.'
            ], 400);
        }

        // دریافت تصاویر فعال
        $activeImages = SpinnerImage::active()->get();
        
        if ($activeImages->count() < 3) {
            return response()->json([
                'success' => false,
                'message' => 'تعداد تصاویر کافی برای اسپین موجود نیست'
            ], 400);
        }

        try {
            // انتخاب تصادفی 3 تصویر
            $selectedImages = $activeImages->random(3)->pluck('id')->toArray();
            
            // بررسی برنده شدن (همه 3 تصویر یکسان باشند)
            $isWin = count(array_unique($selectedImages)) === 1;
            $pointsEarned = $isWin ? 300 : 0;

            // ذخیره نتیجه
            $spinnerResult = SpinnerResult::create([
                'user_id' => $user->id,
                'result_images' => $selectedImages,
                'is_win' => $isWin,
                'points_earned' => $pointsEarned,
                'spin_date' => today()
            ]);

            // دریافت اطلاعات کامل تصاویر انتخاب شده
            $selectedImagesData = SpinnerImage::whereIn('id', $selectedImages)->get();

            return response()->json([
                'success' => true,
                'result' => [
                    'images' => $selectedImagesData,
                    'is_win' => $isWin,
                    'points_earned' => $pointsEarned,
                    'message' => $isWin ? 'تبریک! شما 300 امتیاز برنده شدید!' : 'متأسفانه برنده نشدید. فردا دوباره امتحان کنید.'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در انجام اسپین: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * دریافت وضعیت اسپین کاربر
     */
    public function getSpinStatus()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        $hasSpunToday = SpinnerResult::hasUserSpunToday($user->id);
        $lastSpin = SpinnerResult::getLastUserSpin($user->id);

        return response()->json([
            'success' => true,
            'has_spun_today' => $hasSpunToday,
            'last_spin' => $lastSpin,
            'can_spin' => !$hasSpunToday
        ]);
    }

    /**
     * دریافت تاریخچه اسپین‌های کاربر
     */
    public function getSpinHistory()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر احراز هویت نشده است'
            ], 401);
        }

        $history = SpinnerResult::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->with(['user:id,username'])
            ->get();

        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }
}
