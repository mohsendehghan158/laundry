<?php

declare(strict_types=1);

namespace App\Modules\Organization\Database\Factories;

use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'شعبه '.fake()->unique()->city(),
            'code' => Str::upper(Str::random(4)),
            'type' => BranchType::Hybrid,
            'phone' => null,
            'address' => null,
            'is_active' => true,
        ];
    }
}
