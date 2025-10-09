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
        Schema::table('stage_photos', function (Blueprint $table) {
            $table->boolean('partially_unlocked')->default(false)->after('is_unlocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stage_photos', function (Blueprint $table) {
            $table->dropColumn('partially_unlocked');
        });
    }
};