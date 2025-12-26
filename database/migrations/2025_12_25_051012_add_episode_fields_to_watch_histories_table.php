<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('watch_histories', function (Blueprint $table) {
        $table->string('episode_title')->nullable();
        $table->date('episode_release_date')->nullable();
    });
}

public function down()
{
    Schema::table('watch_histories', function (Blueprint $table) {
        $table->dropColumn(['episode_title', 'episode_release_date']);
    });
}

};
