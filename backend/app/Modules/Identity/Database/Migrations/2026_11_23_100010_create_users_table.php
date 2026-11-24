<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('username', 30);
            $table->string('email')->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('locale', 5)->default('fa');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        // Usernames identify a person at login, so they are unique across
        // all organizations; deleting a user frees the name.
        DB::statement('CREATE UNIQUE INDEX users_username_unique ON users (username) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('users');
    }
};
