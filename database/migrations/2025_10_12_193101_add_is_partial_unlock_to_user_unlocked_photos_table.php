<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsPartialUnlockToUserUnlockedPhotosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_unlocked_photos', function (Blueprint $table) {
            $table->boolean('is_partial_unlock')->default(false)->after('unlocked_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_unlocked_photos', function (Blueprint $table) {
            $table->dropColumn('is_partial_unlock');
        });
    }
}
