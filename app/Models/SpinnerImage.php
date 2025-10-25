<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpinnerImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_path',
        'image_url',
        'is_active',
        'order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * Scope برای دریافت تصاویر فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope برای مرتب کردن بر اساس order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
