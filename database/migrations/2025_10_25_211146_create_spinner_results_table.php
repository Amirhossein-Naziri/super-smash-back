<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpinnerResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spinner_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('result_images'); // آرایه ای از ID های تصاویر انتخاب شده
            $table->boolean('is_win')->default(false); // آیا برنده شده یا نه
            $table->integer('points_earned')->default(0); // امتیاز کسب شده
            $table->date('spin_date'); // تاریخ اسپین
            $table->timestamps();
            
            // ایندکس برای جلوگیری از اسپین مکرر در یک روز
            $table->unique(['user_id', 'spin_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spinner_results');
    }
}
