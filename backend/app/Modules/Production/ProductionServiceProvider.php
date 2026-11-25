<?php

declare(strict_types=1);

namespace App\Modules\Production;

use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Production\Console\OverdueAlertsCommand;
use App\Modules\Production\Listeners\RefreshBoards;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ProductionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, RefreshBoards::class);

        if ($this->app->runningInConsole()) {
            $this->commands([OverdueAlertsCommand::class]);
        }
    }
}
