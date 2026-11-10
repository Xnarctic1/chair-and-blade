<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_status')->default('not_required')->index();
            $table->string('checkout_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->dateTime('hold_expires_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('confirmation_sent_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['payment_status', 'checkout_session_id', 'checkout_url', 'payment_intent_id', 'hold_expires_at', 'paid_at', 'confirmation_sent_at', 'reminder_sent_at']);
        });
    }
};
