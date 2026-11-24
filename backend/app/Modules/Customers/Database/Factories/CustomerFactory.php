<?php

declare(strict_types=1);

namespace App\Modules\Customers\Database\Factories;

use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'mobile' => '09'.fake()->unique()->numerify('#########'),
            'phone' => null,
            'notes' => null,
            'alert' => null,
        ];
    }
}
