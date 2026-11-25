<?php

declare(strict_types=1);

namespace App\Modules\Production\Console;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Production\Events\OrdersOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OverdueAlertsCommand extends Command
{
    /** @var string */
    protected $signature = 'production:overdue-alerts';

    /** @var string */
    protected $description = 'Flag orders that have just passed their due time on the production boards';

    private const string LAST_RUN = 'production:overdue-alerts:last-run';

    public function handle(): int
    {
        $now = now();
        // From the previous run (or the last quarter hour) up to now, so no order is missed or flagged twice.
        $since = Carbon::parse(Cache::get(self::LAST_RUN, $now->copy()->subMinutes(15)->toIso8601String()));

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [OrderStatus::Received, OrderStatus::InProcess, OrderStatus::PartiallyReady])
            ->where('due_at', '>', $since)
            ->where('due_at', '<=', $now)
            ->get(['id', 'branch_id', 'ticket_number']);

        /** @var array<int, list<string>> $byBranch */
        $byBranch = [];
        foreach ($orders as $order) {
            $branches = DB::table('pieces')->where('order_id', $order->id)->whereNotNull('branch_id')->distinct()->pluck('branch_id')->all();
            foreach (array_unique([$order->branch_id, ...$branches]) as $branchId) {
                $byBranch[(int) $branchId][] = $order->ticket_number;
            }
        }

        foreach ($byBranch as $branchId => $tickets) {
            try {
                broadcast(new OrdersOverdue($branchId, $tickets));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Cache::forever(self::LAST_RUN, $now->toIso8601String());
        $this->info(count($orders).' order(s) became overdue.');

        return self::SUCCESS;
    }
}
