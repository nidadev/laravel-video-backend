<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('video_files', function (Blueprint $table) {
        $table->unsignedBigInteger('season_id')->nullable()->after('variant');

        // Drop the old season string column if it exists
        if (Schema::hasColumn('video_files', 'season')) {
            $table->dropColumn('season');
        }
    });
}

};
