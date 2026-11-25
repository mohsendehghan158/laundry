<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Http\Resources\NotificationLogResource;
use App\Modules\Notifications\Jobs\SendSms;
use App\Modules\Notifications\Models\NotificationLog;
use App\Support\Exceptions\ApiException;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Throwable;

class NotificationLogController
{
    /**
     * List sent messages
     *
     * Newest first.
     */
    #[QueryParameter('filter[order_id]', type: 'integer')]
    #[QueryParameter('filter[status]', 'queued, sent or failed.', type: 'string')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('notifications.view');

        $logs = NotificationLog::query()
            ->with('order')
            ->when($request->input('filter.order_id'), fn (Builder $query, mixed $id) => $query->where('order_id', (int) $id))
            ->when($request->input('filter.status'), fn (Builder $query, mixed $status) => $query->where('status', (string) $status))
            ->latest('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return NotificationLogResource::collection($logs);
    }

    /**
     * Send a failed message again
     */
    public function retry(NotificationLog $notificationLog): NotificationLogResource
    {
        Gate::authorize('notifications.manage');

        if ($notificationLog->status !== 'failed') {
            throw ApiException::conflict('notification_not_failed');
        }

        $notificationLog->update(['status' => 'queued', 'error' => null]);

        try {
            SendSms::dispatch($notificationLog->id);
        } catch (Throwable $e) {
            // Synchronous queue: the failure is already on the log row.
            report($e);
        }

        return new NotificationLogResource($notificationLog->refresh()->load('order'));
    }
}
