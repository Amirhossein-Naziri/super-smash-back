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
        Schema::create('user_voice_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stage_photo_id')->constrained()->onDelete('cascade');
            $table->string('voice_file_path'); // مسیر فایل صوتی
            $table->integer('duration_seconds'); // مدت زمان ضبط (40 ثانیه)
            $table->boolean('is_completed')->default(false); // آیا ضبط کامل شده
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_voice_recordings');
    }
};
