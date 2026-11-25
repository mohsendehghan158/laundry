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
        // Work stations of a branch's workshop: sorting, spotting, cleaning, pressing, quality control…
        Schema::create('stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 10);
            // process: scanning records progress; qc: each scan passes or fails the piece.
            $table->string('kind', 10)->default('process');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX stations_branch_code_unique ON stations (branch_id, code) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE stations ADD CONSTRAINT stations_kind_check CHECK (kind IN ('process', 'qc'))");

        Schema::table('pieces', function (Blueprint $table): void {
            // Where the piece physically is, and the station that last scanned it.
            $table->foreignId('branch_id')->nullable()->after('order_line_id')->constrained()->nullOnDelete();
            $table->foreignId('station_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            // A side state on top of the status: rework, on_hold, outsourced, in_transit, damaged, lost.
            $table->string('flag', 20)->nullable()->after('status');
            $table->string('flag_note', 500)->nullable()->after('flag');
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('assembled_at')->nullable();

            $table->index(['branch_id', 'status']);
            $table->index('station_id');
        });

        DB::statement('UPDATE pieces SET branch_id = orders.branch_id FROM orders WHERE orders.id = pieces.order_id');
        DB::statement('ALTER TABLE pieces DROP CONSTRAINT pieces_status_check');
        DB::statement("ALTER TABLE pieces ADD CONSTRAINT pieces_status_check CHECK (status IN ('received', 'in_process', 'assembled', 'ready', 'handed_over'))");
        DB::statement("ALTER TABLE pieces ADD CONSTRAINT pieces_flag_check CHECK (flag IS NULL OR flag IN ('rework', 'on_hold', 'outsourced', 'in_transit', 'damaged', 'lost'))");

        // Append-only history of each piece: scans, quality checks, assembly, moves.
        Schema::create('piece_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['piece_id', 'id']);
            $table->index(['station_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('qc_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('passed');
            $table->string('reason', 500)->nullable();
            // Names of the defects found, copied from the catalog.
            $table->json('defects')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('piece_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspections');
        Schema::dropIfExists('piece_scans');
        DB::statement('ALTER TABLE pieces DROP CONSTRAINT IF EXISTS pieces_flag_check');
        DB::statement('ALTER TABLE pieces DROP CONSTRAINT pieces_status_check');
        DB::statement("ALTER TABLE pieces ADD CONSTRAINT pieces_status_check CHECK (status IN ('received', 'ready', 'handed_over'))");
        Schema::table('pieces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('station_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['flag', 'flag_note', 'last_scanned_at', 'assembled_at']);
        });
        Schema::dropIfExists('stations');
    }
};
