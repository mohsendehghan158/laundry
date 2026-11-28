<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branch expenses (phase 5): rent, wages, utilities, detergent, repairs.
 * An expense paid in cash from the register also takes the money out of
 * the open shift, so the drawer count still matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX expense_categories_organization_name_unique ON expense_categories (organization_id, name) WHERE deleted_at IS NULL');

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            // The local day the money was spent.
            $table->date('spent_on');
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('description', 255);
            // Invoice or receipt number of the supplier, or a transfer reference.
            $table->string('reference', 100)->nullable();
            // Set when the cash came out of the register.
            $table->foreignId('cash_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'spent_on']);
            $table->index(['branch_id', 'spent_on']);
        });

        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_method_check CHECK (method IN ('cash', 'card', 'transfer'))");
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
