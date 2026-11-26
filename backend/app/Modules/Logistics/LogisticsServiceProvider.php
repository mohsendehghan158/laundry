<?php

declare(strict_types=1);

namespace App\Modules\Logistics;

use App\Modules\Logistics\Listeners\ReleasePickupSlot;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class LogisticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, ReleasePickupSlot::class);
    }
}
