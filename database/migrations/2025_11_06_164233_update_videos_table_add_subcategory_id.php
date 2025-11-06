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
         Schema::table('videos', function (Blueprint $table) {
            // Drop old subcategory column
            if (Schema::hasColumn('videos', 'subcategory')) {
                $table->dropColumn('subcategory');
            }

            // Add new subcategory_id column with foreign key
            $table->unsignedBigInteger('subcategory_id')->nullable()->after('category_id');
            $table->foreign('subcategory_id')
                  ->references('id')
                  ->on('subcategories')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('videos', function (Blueprint $table) {
            // Remove foreign key and column
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn('subcategory_id');

            // Restore old subcategory column
            $table->string('subcategory')->nullable()->after('category_id');
        });
    }
};
