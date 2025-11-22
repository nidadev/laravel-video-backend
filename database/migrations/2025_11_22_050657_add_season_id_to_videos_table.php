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
    Schema::table('videos', function (Blueprint $table) {
        $table->unsignedBigInteger('season_id')->nullable()->after('subcategory_id');
        $table->foreign('season_id')->references('id')->on('seasons')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('videos', function (Blueprint $table) {
        $table->dropForeign(['season_id']);
        $table->dropColumn('season_id');
    });
}
};
