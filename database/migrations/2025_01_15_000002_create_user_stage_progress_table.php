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
        Schema::create('user_stage_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stage_id')->constrained()->onDelete('cascade');
            $table->integer('unlocked_photos_count')->default(0); // تعداد عکس‌های باز شده
            $table->integer('completed_voice_recordings')->default(0); // تعداد ضبط‌های صوتی کامل شده
            $table->boolean('stage_completed')->default(false); // آیا مرحله کامل شده
            $table->timestamps();
            
            // اطمینان از اینکه هر کاربر فقط یک رکورد برای هر مرحله دارد
            $table->unique(['user_id', 'stage_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_stage_progress');
    }
};
