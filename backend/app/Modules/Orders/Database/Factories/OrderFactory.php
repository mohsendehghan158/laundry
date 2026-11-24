<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare order without lines; tests that need lines go through the API.
 *
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'organization_id' => fn (array $attributes): int => Branch::query()->withoutGlobalScopes()->findOrFail($attributes['branch_id'])->organization_id,
            'customer_id' => fn (array $attributes): int => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'ticket_number' => 'X-'.fake()->unique()->numerify('#####'),
            'status' => OrderStatus::Received,
            'payment_status' => PaymentStatus::Unpaid,
            'received_at' => now(),
            'due_at' => now()->addDays(3),
            'subtotal' => 1_000_000,
            'total' => 1_000_000,
        ];
    }
}
