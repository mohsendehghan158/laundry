<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery notes (حواله) between the shop and a company: what came in
 * and what went back, counted by both sides. An incoming note becomes an
 * order once confirmed; an outgoing one hands its order over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // The company's customer the order is placed for.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 3);
            $table->unsignedBigInteger('number');
            $table->string('status', 10)->default('draft');
            // Who signed for the company.
            $table->string('counterpart_name', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['company_id', 'created_at']);
        });

        DB::statement("ALTER TABLE delivery_notes ADD CONSTRAINT delivery_notes_type_check CHECK (type IN ('in', 'out'))");
        DB::statement("ALTER TABLE delivery_notes ADD CONSTRAINT delivery_notes_status_check CHECK (status IN ('draft', 'confirmed'))");

        // Per line: what the other side stated and what was counted here
        // (in), or what was sent and what the company counted (out).
        Schema::create('delivery_note_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name', 100);
            $table->string('service_name', 100)->nullable();
            $table->decimal('expected_quantity', 10, 3);
            $table->decimal('counted_quantity', 10, 3);
            $table->unsignedSmallInteger('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
        Schema::dropIfExists('delivery_notes');
    }
};
