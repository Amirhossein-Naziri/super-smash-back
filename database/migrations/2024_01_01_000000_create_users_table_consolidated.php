<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTableConsolidated extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // Made nullable for optional registration step 2
            $table->string('password');
            // Optional fields for registration step 2 and Telegram
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->string('telegram_language_code')->nullable();
            $table->integer('score')->nullable();
            $table->integer('level_story')->default(1)->after('score');
            
            // Referral fields
            $table->string('referral_code', 8)->unique()->nullable();
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->integer('referral_count')->default(0);
            $table->integer('referral_rewards')->default(0);
            
            $table->rememberToken();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('referred_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
