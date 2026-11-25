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
        // Racks of finished orders at a branch, each with numbered slots (A-01, A-02…).
        Schema::create('racks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX racks_branch_code_unique ON racks (branch_id, code) WHERE deleted_at IS NULL');

        Schema::create('rack_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('code', 20);

            $table->unique(['rack_id', 'position']);
        });

        Schema::table('pieces', function (Blueprint $table): void {
            $table->foreignId('rack_slot_id')->nullable()->after('station_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pieces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rack_slot_id');
        });
        Schema::dropIfExists('rack_slots');
        Schema::dropIfExists('racks');
    }
};
