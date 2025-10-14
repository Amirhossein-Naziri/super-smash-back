<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserUnlockedPhotosTableConsolidated extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_unlocked_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stage_photo_id')->constrained()->onDelete('cascade');
            $table->timestamp('unlocked_at');
            $table->boolean('is_partial_unlock')->default(false);
            $table->timestamps();
            
            // اطمینان از اینکه هر کاربر فقط یک بار هر عکس را باز کرده
            $table->unique(['user_id', 'stage_photo_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_unlocked_photos');
    }
}
