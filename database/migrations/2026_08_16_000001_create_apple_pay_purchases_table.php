<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_pay_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->string('apple_transaction_id')->nullable()->index();
            $table->string('apple_original_transaction_id')->nullable()->index();
            $table->string('product_id')->nullable()->index();
            $table->json('payment_response')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->unique(['apple_transaction_id', 'product_id'], 'apple_pay_transaction_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_pay_purchases');
    }
};
