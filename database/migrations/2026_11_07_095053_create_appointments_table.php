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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('email');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_cents');
            $table->string('status')->default('confirmed');
            $table->timestamps();
        });

        Schema::create('appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('appointments');
    }
};
