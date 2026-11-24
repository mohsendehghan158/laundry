<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Actions\SyncPermissions;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development and the staging environment.
 * Production starts from `php artisan laundry:install` instead.
 */
class DatabaseSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    public function run(SyncPermissions $syncPermissions, DemoCatalogSeeder $catalog): void
    {
        $syncPermissions->handle();

        $organization = Organization::query()->create(['name' => 'خشکشویی نمونه', 'slug' => 'demo']);

        $central = $this->branch($organization, 'شعبه مرکزی', 'T1', BranchType::Hybrid, '021-88001122', 'تهران، خیابان ولیعصر، پلاک ۱۲۰');
        $west = $this->branch($organization, 'شعبه غرب', 'T2', BranchType::Store, '021-44002233', 'تهران، بلوار فردوس، پلاک ۴۵');
        $plant = $this->branch($organization, 'کارگاه مرکزی', 'P1', BranchType::Plant, null, 'تهران، جاده قدیم کرج، کیلومتر ۸');

        $this->user($organization, 'مدیر سامانه', 'admin', PermissionRegistry::OWNER, []);
        $this->user($organization, 'سارا احمدی', 'manager', 'branch_manager', [$central]);
        $this->user($organization, 'علی رضایی', 'cashier', 'cashier', [$central]);
        $this->user($organization, 'مریم کریمی', 'cashier2', 'cashier', [$west]);
        $this->user($organization, 'حسین موسوی', 'operator', 'plant_operator', [$plant]);
        $this->user($organization, 'رضا نوری', 'driver', 'driver', [$central, $west]);
        $this->user($organization, 'نرگس صادقی', 'accountant', 'accountant', []);

        $catalog->run($organization->id);
    }

    private function branch(
        Organization $organization,
        string $name,
        string $code,
        BranchType $type,
        ?string $phone,
        string $address,
    ): Branch {
        return Branch::query()->forceCreate([
            'organization_id' => $organization->id,
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'phone' => $phone,
            'address' => $address,
        ]);
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function user(Organization $organization, string $name, string $username, string $role, array $branches): void
    {
        $user = User::query()->forceCreate([
            'organization_id' => $organization->id,
            'name' => $name,
            'username' => $username,
            'password' => self::PASSWORD,
        ]);

        $user->assignRole($role);
        $user->branches()->sync(array_map(fn (Branch $branch): int => $branch->id, $branches));
    }
}
