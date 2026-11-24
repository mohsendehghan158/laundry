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
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->string('type', 20);
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        // The code prefixes ticket numbers, so it must be unique among live
        // branches; a deleted branch frees its code for reuse.
        DB::statement(
            'CREATE UNIQUE INDEX branches_organization_code_unique ON branches (organization_id, code) WHERE deleted_at IS NULL'
        );

        DB::statement(
            "ALTER TABLE branches ADD CONSTRAINT branches_type_check CHECK (type IN ('store', 'plant', 'hybrid'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
