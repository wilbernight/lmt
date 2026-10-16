<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->boolean('can_create_workspaces')->default(true)->after('is_admin');
            $table->unsignedSmallInteger('max_workspaces')->nullable()->after('can_create_workspaces');
            $table->unsignedBigInteger('storage_quota')->nullable()->after('max_workspaces');
            $table->string('suspended_reason', 300)->nullable()->after('suspended_at');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('storage_limit');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');

        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('suspended_at'));

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'is_admin', 'can_create_workspaces', 'max_workspaces', 'storage_quota', 'suspended_reason',
        ]));
    }
};
