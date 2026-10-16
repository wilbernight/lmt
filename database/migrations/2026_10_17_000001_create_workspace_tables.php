<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('color', 20)->default('indigo');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('storage_used')->default(0);
            $table->unsignedBigInteger('storage_limit')->default(2 * 1024 * 1024 * 1024);
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('job_title', 80)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20)->default('member');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20); // task | letter | note | customer
            $table->string('name', 40);
            $table->string('color', 20)->default('slate');
            $table->timestamps();

            $table->unique(['workspace_id', 'scope', 'name']);
        });

        Schema::create('labelables', function (Blueprint $table) {
            $table->foreignUlid('label_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('labelable');

            $table->primary(['label_id', 'labelable_id', 'labelable_type']);
        });

        Schema::create('task_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('teal');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulidMorphs('bookmarkable');
            $table->timestamps();

            $table->unique(['user_id', 'bookmarkable_id', 'bookmarkable_type']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('workspace_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_id', 26)->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('task_categories');
        Schema::dropIfExists('labelables');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
