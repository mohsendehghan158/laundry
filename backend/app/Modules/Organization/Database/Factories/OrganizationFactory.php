<?php

declare(strict_types=1);

namespace App\Modules\Organization\Database\Factories;

use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::lower(Str::random(12)),
            'is_active' => true,
        ];
    }
}
