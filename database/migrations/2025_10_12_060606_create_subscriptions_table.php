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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
 $table->foreignId('user_id')->constrained()->onDelete('cascade'); // FK user
            $table->foreignId('plan_id')->constrained()->onDelete('cascade'); // FK plan
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
