<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Http\Resources\ActivityResource;
use App\Modules\Audit\Models\Activity;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController
{
    /**
     * List audit log entries
     *
     * Newest first.
     */
    #[QueryParameter('filter[log_name]', 'One of auth, user, branch, role, settings, organization, catalog.', type: 'string')]
    #[QueryParameter('filter[event]', 'e.g. created, updated, deleted, login.', type: 'string')]
    #[QueryParameter('filter[causer_id]', 'User who performed the action.', type: 'integer')]
    #[QueryParameter('filter[from]', 'Entries at or after this moment.', type: 'string', format: 'date-time')]
    #[QueryParameter('filter[to]', 'Entries at or before this moment.', type: 'string', format: 'date-time')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('activity.view');

        $activities = QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('causer_id'),
                AllowedFilter::callback('from', fn (Builder $query, mixed $value) => $query
                    ->where('created_at', '>=', Carbon::parse((string) $value))),
                AllowedFilter::callback('to', fn (Builder $query, mixed $value) => $query
                    ->where('created_at', '<=', Carbon::parse((string) $value))),
            )
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return ActivityResource::collection($activities);
    }
}
