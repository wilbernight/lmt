<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named groups of colleagues for sending one SMS to many people.
        Schema::create('sms_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('color', 20)->default('indigo');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sms_group_user', function (Blueprint $table) {
            $table->foreignId('sms_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['sms_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_group_user');
        Schema::dropIfExists('sms_groups');
    }
};
