<?php

use App\Modules\Notifications\NotificationsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Support\Modules\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    NotificationsServiceProvider::class,
];
