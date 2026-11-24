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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Unguessable id for links that leave the system (tracking page, SMS).
            $table->ulid('public_id')->unique();
            // Short, readable number printed on the receipt: branch code + counter, e.g. T1-04217.
            $table->string('ticket_number', 20);
            $table->string('status', 20);
            $table->string('payment_status', 20)->default('unpaid');

            // Turnaround tier as chosen at intake (copied, so later catalog edits do not change it).
            $table->foreignId('turnaround_tier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('turnaround_name', 100)->nullable();
            $table->unsignedSmallInteger('surcharge_percent')->default(0);

            $table->timestamp('received_at');
            $table->timestamp('due_at');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('rack_location', 50)->nullable();
            $table->text('notes')->nullable();

            // Money, all in whole rials.
            $table->unsignedBigInteger('subtotal');
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid_amount')->default(0);

            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Raised on every change; an edit sent with an older version is refused.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['organization_id', 'ticket_number']);
            $table->index(['branch_id', 'status', 'due_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index('customer_id');
        });

        DB::statement('CREATE UNIQUE INDEX orders_organization_idempotency_unique ON orders (organization_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('draft', 'pending_pickup', 'picked_up', 'received', 'in_process', 'partially_ready', 'ready', 'out_for_delivery', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('unpaid', 'partially_paid', 'paid', 'refunded'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_discount_type_check CHECK (discount_type IS NULL OR discount_type IN ('amount', 'percent'))");

        // What was ordered and at what price; names and prices are copies.
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name', 100);
            $table->string('service_name', 100);
            $table->string('service_code', 10);
            $table->string('unit', 10);
            $table->decimal('quantity', 10, 3);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('base_amount');
            $table->unsignedBigInteger('upcharge_amount')->default(0);
            $table->unsignedBigInteger('surcharge_amount')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->string('notes', 500)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('order_line_upcharges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upcharge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('type', 10);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('amount');
        });

        // The physical, tagged things: one per garment, or one bag/rug for weighed and measured lines.
        Schema::create('pieces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            // Printed as a barcode on the tag: ticket number + piece number, e.g. T1-04217-03.
            $table->string('tag_code', 30);
            $table->unsignedSmallInteger('number');
            $table->string('status', 20)->default('received');
            $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete();
            $table->string('color_name', 50)->nullable();
            $table->string('brand', 50)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'tag_code']);
            $table->index('order_id');
        });

        DB::statement("ALTER TABLE pieces ADD CONSTRAINT pieces_status_check CHECK (status IN ('received', 'ready', 'handed_over'))");

        Schema::create('piece_defects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->foreignId('defect_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('kind', 10);
        });

        // Append-only: every status change, who made it and why.
        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at');

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('piece_defects');
        Schema::dropIfExists('pieces');
        Schema::dropIfExists('order_line_upcharges');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
