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
        // Pieces sent between branches (a counter and its plant) with a manifest and a bag count.
        Schema::create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 10)->default('open');
            $table->unsignedSmallInteger('bags_sent')->nullable();
            $table->unsignedSmallInteger('bags_received')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['to_branch_id', 'status']);
            $table->index(['from_branch_id', 'status']);
        });

        DB::statement("ALTER TABLE transfers ADD CONSTRAINT transfers_status_check CHECK (status IN ('open', 'sent', 'received', 'cancelled'))");

        Schema::create('transfer_pieces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->timestamp('received_at')->nullable();

            $table->unique(['transfer_id', 'piece_id']);
            $table->index('piece_id');
        });

        // Outside workshops for what is not done in-house (rugs, leather, tailoring).
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 30)->nullable();
            $table->string('services', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('outsourcing_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // sent: at the vendor (some pieces may be back); closed: everything is back.
            $table->string('status', 10)->default('sent');
            $table->timestamp('expected_at')->nullable();
            // What the vendor charges, in rials.
            $table->unsignedBigInteger('cost')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });

        DB::statement("ALTER TABLE outsourcing_shipments ADD CONSTRAINT outsourcing_shipments_status_check CHECK (status IN ('sent', 'closed'))");

        Schema::create('outsourcing_shipment_pieces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outsourcing_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['outsourcing_shipment_id', 'piece_id']);
            $table->index('piece_id');
        });

        // Damage and loss cases, and how they were settled.
        Schema::create('claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('status', 10)->default('open');
            $table->string('description', 1000);
            $table->string('resolution', 1000)->nullable();
            // Paid to the customer, in rials.
            $table->unsignedBigInteger('compensation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
            $table->index('piece_id');
        });

        DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_kind_check CHECK (kind IN ('damaged', 'lost'))");
        DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_status_check CHECK (status IN ('open', 'resolved', 'rejected'))");

        // Photos of pieces (at intake, damage, before and after), kept on the private disk.
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('piece_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('outsourcing_shipment_pieces');
        Schema::dropIfExists('outsourcing_shipments');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('transfer_pieces');
        Schema::dropIfExists('transfers');
    }
};
