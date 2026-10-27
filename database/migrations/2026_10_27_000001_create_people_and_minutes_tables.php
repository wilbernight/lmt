<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Directory of colleagues (or anyone) without an account, referenced by name.
        Schema::create('people', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            // Encrypted at rest: ciphertext is longer than the name it hides.
            $table->mediumText('name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        // External assignees on tasks.
        Schema::create('task_person', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['task_id', 'person_id']);
            $table->index('person_id');
        });

        // External letter recipients and copies.
        Schema::create('letter_recipient_people', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('type', 10)->default('to'); // to | cc
            $table->timestamps();

            $table->unique(['letter_id', 'person_id']);
            $table->index('person_id');
        });

        // Meeting minutes (صورت‌جلسه). subject/body/location are encrypted at rest.
        Schema::create('minutes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->mediumText('subject')->nullable();
            $table->mediumText('body')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->mediumText('location')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('draft'); // draft | final
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index('held_at');
        });

        // Invitees of a minute: either a member (user) or an external person; attended marks حاضر/غایب.
        Schema::create('minute_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('minute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->cascadeOnDelete();
            $table->boolean('attended')->default(true);
            $table->timestamps();

            $table->unique(['minute_id', 'user_id']);
            $table->unique(['minute_id', 'person_id']);
            $table->index('user_id');
            $table->index('person_id');
        });

        // Action items (اقدامات) carved out of a minute; may become a real task.
        Schema::create('minute_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('minute_id')->constrained()->cascadeOnDelete();
            $table->mediumText('title')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('person_id')->nullable()->constrained('people')->cascadeOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->foreignUlid('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['minute_id', 'position']);
        });

        // Electronic sign-off (تأیید) of a finalized minute by attending members.
        Schema::create('minute_approvals', function (Blueprint $table) {
            $table->foreignUlid('minute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['minute_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_approvals');
        Schema::dropIfExists('minute_actions');
        Schema::dropIfExists('minute_participants');
        Schema::dropIfExists('minutes');
        Schema::dropIfExists('letter_recipient_people');
        Schema::dropIfExists('task_person');
        Schema::dropIfExists('people');
    }
};
