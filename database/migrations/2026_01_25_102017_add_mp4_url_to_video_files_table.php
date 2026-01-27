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
    Schema::table('video_files', function (Blueprint $table) {
        $table->string('mp4_url')->nullable()->after('file_url');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down()
{
    Schema::table('video_files', function (Blueprint $table) {
        $table->dropColumn('mp4_url');
    });
}
};
