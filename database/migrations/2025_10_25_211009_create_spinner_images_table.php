<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpinnerImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spinner_images', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام تصویر
            $table->string('image_path'); // مسیر فایل تصویر
            $table->string('image_url')->nullable(); // URL تصویر
            $table->boolean('is_active')->default(true); // فعال/غیرفعال
            $table->integer('order')->default(0); // ترتیب نمایش
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spinner_images');
    }
}
