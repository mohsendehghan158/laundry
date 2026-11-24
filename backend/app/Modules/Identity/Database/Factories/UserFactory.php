<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'username' => 'user_'.Str::lower(Str::random(8)),
            'email' => null,
            'mobile' => null,
            'password' => self::$password ??= Hash::make('password'),
            'is_active' => true,
            'locale' => 'fa',
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * @param  string|list<string>  $roles
     */
    public function withRoles(string|array $roles): self
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles((array) $roles));
    }
}
