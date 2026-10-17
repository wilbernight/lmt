<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per message handed to the gateway (or refused before it).
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('batch_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // recipient, when a member
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 32);                 // masked: 0912****4567
            $table->string('body', 1000);
            $table->string('status', 12);                // sent|failed|limited|invalid|logged
            $table->string('error', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Atomic per-day counter for the daily cap (the SIM operator limits it).
        Schema::create('sms_daily_counts', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_daily_counts');
        Schema::dropIfExists('sms_messages');
    }
};
