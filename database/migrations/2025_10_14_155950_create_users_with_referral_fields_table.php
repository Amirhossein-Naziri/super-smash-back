<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersWithReferralFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First, let's check if users table exists and has data
        if (Schema::hasTable('users')) {
            // If table exists, we'll use raw SQL to add columns
            DB::statement('ALTER TABLE users ADD COLUMN referral_code VARCHAR(8) UNIQUE NULL');
            DB::statement('ALTER TABLE users ADD COLUMN referred_by BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE users ADD COLUMN referral_count INT DEFAULT 0');
            DB::statement('ALTER TABLE users ADD COLUMN referral_rewards INT DEFAULT 0');
            
            // Add foreign key constraint
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_referred_by_foreign FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL');
        } else {
            // If table doesn't exist, create it with all fields
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('city')->nullable();
                $table->string('telegram_user_id')->nullable();
                $table->string('telegram_username')->nullable();
                $table->string('telegram_first_name')->nullable();
                $table->string('telegram_last_name')->nullable();
                $table->string('telegram_language_code')->nullable();
                $table->integer('score')->nullable();
                $table->string('level_story')->nullable();
                
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('users')) {
            // Drop foreign key constraint first
            DB::statement('ALTER TABLE users DROP FOREIGN KEY users_referred_by_foreign');
            
            // Drop columns
            DB::statement('ALTER TABLE users DROP COLUMN referral_code');
            DB::statement('ALTER TABLE users DROP COLUMN referred_by');
            DB::statement('ALTER TABLE users DROP COLUMN referral_count');
            DB::statement('ALTER TABLE users DROP COLUMN referral_rewards');
        }
    }
}