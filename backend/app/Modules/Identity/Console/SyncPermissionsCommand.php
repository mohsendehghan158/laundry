<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Actions\SyncPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('laundry:sync-permissions')]
#[Description('Create missing permissions and built-in roles; run after every deploy')]
class SyncPermissionsCommand extends Command
{
    public function handle(SyncPermissions $syncPermissions): int
    {
        $syncPermissions->handle();

        $this->components->info('Permissions and roles are in sync.');

        return self::SUCCESS;
    }
}
