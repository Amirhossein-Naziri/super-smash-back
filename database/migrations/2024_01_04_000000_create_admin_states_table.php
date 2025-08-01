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
        Schema::create('admin_states', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id');
            $table->json('state_data');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('chat_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_states');
    }
}; 