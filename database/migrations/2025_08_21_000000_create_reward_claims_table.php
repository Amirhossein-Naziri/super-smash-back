<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reward_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('reward_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('stage_number');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'stage_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_claims');
    }
};

