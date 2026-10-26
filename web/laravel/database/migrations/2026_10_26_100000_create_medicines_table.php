<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shop's catalogue: medicines that don't need a prescription in Spain.
     */
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('pack');
            $table->string('summary');
            $table->text('description');
            $table->text('dosage');
            $table->json('warnings');
            $table->string('category', 20);
            $table->string('form', 10);
            $table->unsignedInteger('price_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
