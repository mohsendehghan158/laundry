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
        // What is done to an item: dry cleaning, washing, pressing only …
        Schema::create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 10);
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX service_types_organization_code_unique ON service_types (organization_id, code) WHERE deleted_at IS NULL');

        // Groups on the counter's touch grid: shirts, suits, home textiles …
        Schema::create('item_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            // How quantity is counted and priced.
            $table->string('unit', 10)->default('piece');
            // Physical pieces one unit makes, each with its own tag (a suit is 2).
            $table->unsignedSmallInteger('pieces_per_unit')->default(1);
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'item_category_id']);
        });
        DB::statement("ALTER TABLE items ADD CONSTRAINT items_unit_check CHECK (unit IN ('piece', 'kg', 'sqm'))");
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_pieces_per_unit_check CHECK (pieces_per_unit BETWEEN 1 AND 50)');

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX price_lists_one_default ON price_lists (organization_id) WHERE is_default');

        // The item × service price matrix. Amounts are whole rials per unit.
        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->unique(['price_list_id', 'item_id', 'service_type_id']);
        });

        // Extra charges: silk, pleats, heavy stain … fixed rials or a percent of the line.
        Schema::create('upcharges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 10);
            // Rials for "fixed", whole percent for "percent".
            $table->unsignedBigInteger('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE upcharges ADD CONSTRAINT upcharges_type_check CHECK (type IN ('fixed', 'percent'))");
        DB::statement("ALTER TABLE upcharges ADD CONSTRAINT upcharges_percent_check CHECK (type <> 'percent' OR value <= 1000)");

        // Normal / express / same day: how long the work takes and what it adds to the price.
        Schema::create('turnaround_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('duration_hours');
            $table->unsignedSmallInteger('surcharge_percent')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX turnaround_tiers_one_default ON turnaround_tiers (organization_id) WHERE is_default AND deleted_at IS NULL');

        // Ready-made choices for describing a piece at intake.
        Schema::create('defect_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 10)->default('defect');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE defect_types ADD CONSTRAINT defect_types_kind_check CHECK (kind IN ('defect', 'stain'))");

        Schema::create('colors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('hex', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colors');
        Schema::dropIfExists('defect_types');
        Schema::dropIfExists('turnaround_tiers');
        Schema::dropIfExists('upcharges');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('service_types');
    }
};
