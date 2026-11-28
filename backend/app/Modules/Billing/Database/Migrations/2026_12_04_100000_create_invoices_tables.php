<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Official VAT invoices (phase 5). An invoice is a frozen copy of what was
 * sold, to whom and by whom; it is never edited, only voided with a reason,
 * and its number is never reused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Sequential within the organization, without gaps; voided invoices keep theirs.
            $table->unsignedBigInteger('number');
            $table->string('status', 10)->default('issued');
            $table->timestamp('issued_at');
            // Copies of the seller (from settings) and the buyer at the time of issue.
            $table->jsonb('seller');
            $table->jsonb('buyer');
            $table->decimal('vat_percent', 5, 2);
            // Whole rials: subtotal − discount + tax = total.
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'issued_at']);
            $table->index('customer_id');
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('issued', 'void'))");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_total_check CHECK (total = subtotal - discount_amount + tax_amount)');

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 255);
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 10)->nullable();
            // Before discount and tax.
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('discount');
            $table->unsignedBigInteger('tax');
            $table->unsignedBigInteger('total');
        });

        // The orders an invoice bills. An order is on at most one invoice that is not void.
        Schema::create('invoice_orders', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);

            $table->primary(['invoice_id', 'order_id']);
        });

        DB::statement('CREATE UNIQUE INDEX invoice_orders_one_active_per_order ON invoice_orders (order_id) WHERE active');

        // The buyer details last used on an invoice, offered again next time.
        Schema::table('customers', function (Blueprint $table): void {
            $table->jsonb('invoice_details')->nullable()->after('alert');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('invoice_details');
        });
        Schema::dropIfExists('invoice_orders');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
