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
        // Areas served by pickup and delivery, drawn on the map, each served by one branch.
        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            // Corners as [[latitude, longitude], …]; the edge back to the first is implied.
            $table->jsonb('polygon');
            // Bounding box of the polygon, so a point lookup only tests nearby zones.
            $table->decimal('min_latitude', 10, 7);
            $table->decimal('max_latitude', 10, 7);
            $table->decimal('min_longitude', 10, 7);
            $table->decimal('max_longitude', 10, 7);
            // Rials. The fee is waived from free_delivery_over; min_order is the smallest online order.
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('free_delivery_over')->nullable();
            $table->unsignedBigInteger('min_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        // The weekly pattern of pickup and delivery windows of a branch.
        Schema::create('slot_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // 0 = Sunday … 6 = Saturday, as in hours.closed_days.
            $table->unsignedSmallInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            // How many pickups and deliveries the window takes.
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'weekday']);
        });

        DB::statement('ALTER TABLE slot_templates ADD CONSTRAINT slot_templates_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE slot_templates ADD CONSTRAINT slot_templates_times_check CHECK (ends_at > starts_at)');

        // Dated windows made from the templates for the days customers can book.
        Schema::create('slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('slot_template_id')->nullable()->constrained()->nullOnDelete();
            // Local date and times in the organization's time zone.
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked')->default(0);
            // Closed by hand, e.g. a holiday; keeps its bookings.
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'date', 'starts_at']);
        });

        DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_times_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
        Schema::dropIfExists('slot_templates');
        Schema::dropIfExists('zones');
    }
};
