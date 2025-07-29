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
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->onDelete('cascade');
            $table->string('title'); // عنوان داستان
            $table->text('description'); // متن داستان
            $table->string('image_path'); // مسیر عکس داستان
            $table->boolean('is_correct')->default(false); // آیا این داستان درست است
            $table->integer('order')->default(0); // ترتیب نمایش (1, 2, 3)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
}; 