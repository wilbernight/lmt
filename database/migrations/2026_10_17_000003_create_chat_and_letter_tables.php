<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // direct | group
            $table->string('name', 80)->nullable();
            $table->string('direct_key', 60)->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'last_message_at']);
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member'); // admin | member
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('muted')->default(false);
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->ulid('reply_to_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('letters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->ulid('reply_to_id')->nullable()->index();
            $table->string('subject', 200);
            $table->text('body');
            $table->string('priority', 10)->default('normal'); // normal | urgent | instant
            $table->boolean('confidential')->default(false);
            $table->timestamp('sender_archived_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
        });

        Schema::create('letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('to'); // to | cc | referral
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['letter_id', 'user_id']);
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('letter_referrals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_referrals');
        Schema::dropIfExists('letter_recipients');
        Schema::dropIfExists('letters');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
