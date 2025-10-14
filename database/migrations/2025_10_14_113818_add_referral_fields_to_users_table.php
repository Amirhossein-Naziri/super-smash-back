<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 8)->unique()->nullable(); // User's unique referral code
            $table->unsignedBigInteger('referred_by')->nullable(); // ID of user who referred this user
            $table->integer('referral_count')->default(0); // Number of successful referrals
            $table->integer('referral_rewards')->default(0); // Total rewards earned from referrals
            
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn(['referral_code', 'referred_by', 'referral_count', 'referral_rewards']);
        });
    }
}
