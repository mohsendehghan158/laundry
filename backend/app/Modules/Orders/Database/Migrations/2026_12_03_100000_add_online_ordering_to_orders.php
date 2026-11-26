<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders placed by customers in the portal (phase 4): picked up from an
 * address in a booked window. Until the pickup an order has not been
 * received, so received_at may be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable()->change();

            $table->string('source', 10)->default('counter')->after('payment_status');
            // The booked window; the times are kept in case the slot changes.
            $table->foreignId('pickup_slot_id')->nullable()->after('source')->constrained('slots')->nullOnDelete();
            $table->timestamp('pickup_starts_at')->nullable()->after('pickup_slot_id');
            $table->timestamp('pickup_ends_at')->nullable()->after('pickup_starts_at');
            // A copy of the customer's address, so later edits do not move the pickup.
            $table->string('pickup_address', 500)->nullable()->after('pickup_ends_at');
            $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_address');
            $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            // The customer left the items for the driver to list at the door.
            $table->boolean('items_by_driver')->default(false)->after('pickup_longitude');
            $table->unsignedBigInteger('delivery_fee')->default(0)->after('tax_amount');

            $table->index(['branch_id', 'source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'source', 'status']);
            $table->dropConstrainedForeignId('pickup_slot_id');
            $table->dropColumn(['source', 'pickup_starts_at', 'pickup_ends_at', 'pickup_address', 'pickup_latitude', 'pickup_longitude', 'items_by_driver', 'delivery_fee']);
        });
    }
};
