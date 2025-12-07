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
        Schema::create('watch_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('video_file_id'); // episode/video file
            $table->unsignedInteger('watched_seconds')->nullable(); // optional: how many seconds watched
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('video_file_id')->references('id')->on('video_files')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_histories');
    }
};
