<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_files', function (Blueprint $table) {
            $table->string('job_id')->nullable()->after('manifest_url');
            $table->string('job_status')->nullable()->after('job_id');
            $table->text('job_error')->nullable()->after('job_status');
        });
    }

    public function down(): void
    {
        Schema::table('video_files', function (Blueprint $table) {
            $table->dropColumn(['job_id', 'job_status', 'job_error']);
        });
    }
};
