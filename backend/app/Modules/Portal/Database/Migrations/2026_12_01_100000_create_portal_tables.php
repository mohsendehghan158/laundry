<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-time sign-in codes texted to customers; only a hash is kept.
        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('mobile', 11);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'mobile', 'created_at']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            // Keeps customers signed in to the portal on their phone.
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['remember_token', 'last_login_at']);
        });
        Schema::dropIfExists('otp_codes');
    }
};
