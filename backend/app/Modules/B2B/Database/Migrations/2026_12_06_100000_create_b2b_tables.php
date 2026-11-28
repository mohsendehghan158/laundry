<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corporate customers (phase 5): hotels, restaurants, clinics. A company
 * has a contract (its own price list, credit limit and payment terms),
 * departments that orders are charged to, and the customers who order
 * for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            // Printed on invoices as the buyer.
            $table->string('national_id', 11)->nullable();
            $table->string('economic_code', 14)->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_mobile', 11)->nullable();
            // The contract.
            $table->string('contract_number', 50)->nullable();
            $table->date('contract_starts_on')->nullable();
            $table->date('contract_ends_on')->nullable();
            // Prices of this list replace the default ones while the contract runs.
            $table->foreignId('price_list_id')->nullable()->constrained()->nullOnDelete();
            // Rials the company may owe; empty means it pays like any customer.
            $table->unsignedBigInteger('credit_limit')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        Schema::create('company_departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // The company's own code for it (cost centre), printed on statements.
            $table->string('code', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            // Set when the customer orders for a company: charged to it (and its department).
            $table->foreignId('company_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_department_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'received_at']);
        });

        DB::statement('ALTER TABLE companies ADD CONSTRAINT companies_contract_dates_check CHECK (contract_ends_on IS NULL OR contract_starts_on IS NULL OR contract_ends_on >= contract_starts_on)');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_department_id');
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::dropIfExists('company_departments');
        Schema::dropIfExists('companies');
    }
};
