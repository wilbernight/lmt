<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Work logged against a task, by timer or by hand.
        Schema::create('time_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('seconds')->default(0);
            $table->string('note', 300)->nullable();
            $table->string('source', 10)->default('timer'); // timer | manual
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['project_id', 'started_at']);
            $table->index(['task_id', 'started_at']);
        });

        // Staff presence: opened at login, closed at logout or after inactivity.
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 12)->nullable(); // logout | timeout | relogin
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('session_id', 100)->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['ended_at', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('time_entries');
    }
};
