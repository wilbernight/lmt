<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('color', 20)->default('indigo');
            $table->string('visibility', 20)->default('members'); // members | workspace
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'archived_at']);
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // manager | member | viewer
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('board_columns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 20)->default('slate');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained('task_categories')->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('priority')->default(1); // 0 low .. 3 urgent
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->double('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'completed_at', 'due_at']);
            $table->index(['project_id', 'column_id', 'position']);
            $table->index(['creator_id', 'completed_at']);
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['task_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['task_id', 'user_id']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->string('body', 300);
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->unsignedTinyInteger('progress')->nullable();
            $table->unsignedInteger('spent_minutes')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('board_columns');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
