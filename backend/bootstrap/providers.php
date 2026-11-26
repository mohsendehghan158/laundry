<?php

use App\Modules\Logistics\LogisticsServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Production\ProductionServiceProvider;
use App\Providers\AppServiceProvider;
use App\Support\Modules\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    LogisticsServiceProvider::class,
    NotificationsServiceProvider::class,
    ProductionServiceProvider::class,
];
