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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
  $table->string('name');           // Plan name e.g., Basic, Premium
            $table->decimal('price', 8, 2);  // Price
            $table->integer('duration_days'); // Subscription duration in days
            $table->boolean('ads_enabled')->default(true); // Show ads if true
            $table->timestamps();        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
