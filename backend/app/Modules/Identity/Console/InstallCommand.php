<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\SyncPermissions;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('laundry:install
    {--organization= : Name of the business}
    {--branch= : Name of the first branch}
    {--branch-code=T1 : Short code of the first branch}
    {--name= : Full name of the owner}
    {--username= : Username of the owner}
    {--password= : Password of the owner (prompted when omitted)}')]
#[Description('Set up a fresh installation: organization, first branch and owner account')]
class InstallCommand extends Command
{
    public function handle(SyncPermissions $syncPermissions): int
    {
        if (Organization::query()->exists()) {
            $this->components->error('An organization already exists; nothing was changed.');

            return self::FAILURE;
        }

        $input = [
            'organization' => $this->option('organization') ?? text('Business name', required: true),
            'branch' => $this->option('branch') ?? text('First branch name', required: true),
            'branch_code' => Str::upper((string) $this->option('branch-code')),
            'name' => $this->option('name') ?? text('Owner full name', required: true),
            'username' => Str::lower($this->option('username') ?? text('Owner username', required: true)),
            'password' => $this->option('password') ?? password('Owner password', required: true),
        ];

        $validator = Validator::make($input, [
            'organization' => ['required', 'string', 'max:100'],
            'branch' => ['required', 'string', 'max:100'],
            'branch_code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:/^[a-z0-9._-]{3,30}$/', Rule::unique('users', 'username')],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($input, $syncPermissions): void {
            $syncPermissions->handle();

            $organization = Organization::query()->create([
                'name' => $input['organization'],
                'slug' => Str::lower(Str::random(12)),
            ]);

            Branch::query()->forceCreate([
                'organization_id' => $organization->id,
                'name' => $input['branch'],
                'code' => $input['branch_code'],
                'type' => BranchType::Hybrid,
            ]);

            User::query()->forceCreate([
                'organization_id' => $organization->id,
                'name' => $input['name'],
                'username' => $input['username'],
                'password' => $input['password'],
            ])->assignRole(PermissionRegistry::OWNER);
        });

        $this->components->info("Installed. Sign in as \"{$input['username']}\".");

        return self::SUCCESS;
    }
}
