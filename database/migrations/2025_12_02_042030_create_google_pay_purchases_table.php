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
        Schema::create('google_pay_purchases', function (Blueprint $table) {
            $table->id();

            // User who purchased
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Plan purchased
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');

            // Google Pay transaction token / ID
            $table->string('googlepay_transaction_id')->nullable();

            // User email used in Google Pay
            $table->string('googlepay_email')->nullable();

            // Full payment response JSON from Google Pay SDK
            $table->json('payment_response')->nullable();

            // Status of the purchase (optional)
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_pay_purchases');
    }
};
