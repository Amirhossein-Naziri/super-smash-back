<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stage_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->onDelete('cascade');
            $table->string('image_path'); // مسیر عکس اصلی
            $table->string('blurred_image_path'); // مسیر عکس تار شده
            $table->integer('photo_order'); // ترتیب عکس در مرحله (1-6)
            $table->boolean('is_unlocked')->default(false); // آیا عکس باز شده
            $table->boolean('partially_unlocked')->default(false)->after('is_unlocked');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_photos');
    }
};
