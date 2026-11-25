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
        // A cash register session at a branch, from opening float to the count at closing.
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('opening_float')->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            // What should be in the drawer and what was counted, both in rials.
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'opened_at']);
        });

        DB::statement("ALTER TABLE cash_shifts ADD CONSTRAINT cash_shifts_status_check CHECK (status IN ('open', 'closed'))");
        // One open register per branch.
        DB::statement("CREATE UNIQUE INDEX cash_shifts_one_open_per_branch ON cash_shifts (branch_id) WHERE status = 'open'");

        // Append-only: money received for an order, or given back. Never updated or deleted.
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('method', 20);
            $table->unsignedBigInteger('amount');
            // Card terminal trace number or bank transfer reference.
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->index('order_id');
            $table->index(['organization_id', 'created_at']);
            $table->index('cash_shift_id');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_kind_check CHECK (kind IN ('payment', 'refund'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'card', 'transfer'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');

        // Cash put into or taken out of the drawer for something other than an order.
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_shift_id')->constrained()->restrictOnDelete();
            $table->string('type', 5);
            $table->unsignedBigInteger('amount');
            $table->string('reason', 255);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
        });

        DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_check CHECK (type IN ('in', 'out'))");
        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_check CHECK (amount > 0)');

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('description', 255)->nullable();
            $table->string('type', 10);
            // Rials for "amount", hundredths of a percent for "percent" (1250 = 12.5%).
            $table->unsignedBigInteger('value');
            // Upper limit of a percent discount, in rials.
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX promo_codes_organization_code_unique ON promo_codes (organization_id, code) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE promo_codes ADD CONSTRAINT promo_codes_type_check CHECK (type IN ('amount', 'percent'))");

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('promo_code_id')->nullable()->after('discount_reason')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promo_code_id');
        });
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_shifts');
    }
};
