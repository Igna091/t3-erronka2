<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop orders and what is in them. The pharmacist's health questions are stored as text
     * because the Order model encrypts them; the card is kept as its last 4 digits only.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('taker', 10);
            $table->text('age');
            $table->text('pregnant');
            $table->text('health_notes')->nullable();
            $table->text('allergies')->nullable();
            $table->timestamp('health_consent_at');
            $table->string('payment_method', 10);
            $table->string('payment_status', 20);
            $table->string('card_last4', 4)->nullable();
            $table->unsignedInteger('total_cents');
            $table->string('status', 20)->default('received');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
