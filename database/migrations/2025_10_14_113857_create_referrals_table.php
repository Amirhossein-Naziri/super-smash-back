<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferralsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id'); // User who made the referral
            $table->unsignedBigInteger('referred_id'); // User who was referred
            $table->integer('reward_amount')->default(0); // Reward amount for this referral
            $table->integer('referral_order')->default(1); // Order of referral (1st, 2nd, 3rd, etc.)
            $table->boolean('is_active')->default(true); // Whether this referral is active
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_id')->references('id')->on('users')->onDelete('cascade');
            
            // Ensure unique referral relationship
            $table->unique(['referrer_id', 'referred_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrals');
    }
}
