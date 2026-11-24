<?php

declare(strict_types=1);

use App\Support\Modules\ModuleServiceProvider;

arch('no debugging or environment calls outside config')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'env']);

arch('controllers stay thin: no direct database access')
    ->expect(array_map(
        fn (string $module): string => "App\\Modules\\{$module}\\Http\\Controllers",
        ModuleServiceProvider::MODULES,
    ))
    ->toHaveSuffix('Controller')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('shared support code does not depend on HTTP internals of a module')
    ->expect('App\Support')
    ->not->toUse(array_map(
        fn (string $module): string => "App\\Modules\\{$module}\\Http",
        ModuleServiceProvider::MODULES,
    ));

// A module's Http layer (controllers, requests, resources) and policies
// are private. Other modules may use its models, enums, events and actions.
foreach (ModuleServiceProvider::MODULES as $module) {
    $others = array_values(array_diff(ModuleServiceProvider::MODULES, [$module]));

    arch("the HTTP layer and policies of {$module} are private to it")
        ->expect(array_map(fn (string $other): string => "App\\Modules\\{$other}", $others))
        ->not->toUse(["App\\Modules\\{$module}\\Http", "App\\Modules\\{$module}\\Policies"])
        ->ignoring("App\\Modules\\{$module}\\Models");
}
