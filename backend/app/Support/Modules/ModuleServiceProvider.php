<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Modules\Organization\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires every domain module into the framework: each module owns its
 * migrations and its route file, both discovered by convention.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    /** @var list<string> */
    public const array MODULES = ['Organization', 'Identity', 'Audit', 'Catalog', 'Customers'];

    public const string API_PREFIX = 'api/v1';

    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class);
    }

    public function boot(): void
    {
        foreach (self::MODULES as $module) {
            $path = app_path("Modules/{$module}");

            if (is_dir("{$path}/Database/Migrations")) {
                $this->loadMigrationsFrom("{$path}/Database/Migrations");
            }

            if (is_file("{$path}/routes.php") && ! $this->app->routesAreCached()) {
                Route::middleware('api')
                    ->prefix(self::API_PREFIX)
                    ->group("{$path}/routes.php");
            }
        }
    }
}
