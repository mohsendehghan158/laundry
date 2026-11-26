<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CloseShift;
use App\Modules\Billing\Actions\OpenShift;
use App\Modules\Billing\Actions\RecordCashMovement;
use App\Modules\Billing\Enums\CashMovementType;
use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Http\Requests\CashMovementRequest;
use App\Modules\Billing\Http\Requests\CloseShiftRequest;
use App\Modules\Billing\Http\Requests\OpenShiftRequest;
use App\Modules\Billing\Http\Resources\CashShiftResource;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashShiftController
{
    private const array DETAIL = ['branch', 'opener', 'closer', 'payments.user', 'payments.order', 'movements.user'];

    /**
     * List cash shifts
     *
     * Newest first, for the branches the user works in.
     */
    #[QueryParameter('filter[branch_id]', type: 'integer')]
    #[QueryParameter('filter[status]', 'open or closed.', type: 'string')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $this->authorizeAny($actor);

        $shifts = CashShift::query()
            ->with(['branch', 'opener', 'closer'])
            ->whereIn('branch_id', $this->visibleBranches($actor))
            ->when($request->input('filter.branch_id'), fn (Builder $query, mixed $id) => $query->where('branch_id', (int) $id))
            ->when($request->input('filter.status'), fn (Builder $query, mixed $status) => $query->where('status', (string) $status))
            ->latest('opened_at')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return CashShiftResource::collection($shifts);
    }

    /**
     * Show the open shift of a branch
     *
     * `data` is null when the branch's register is closed.
     *
     * @response array{data: CashShiftResource|null}
     */
    #[QueryParameter('branch_id', required: true, type: 'integer')]
    public function current(Request $request, #[CurrentUser] User $actor): JsonResponse
    {
        $this->authorizeAny($actor);

        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        abort_unless($actor->canAccessBranch($branch), 404);

        $shift = CashShift::query()->where('branch_id', $branch->id)->where('status', ShiftStatus::Open)->with(self::DETAIL)->first();

        return response()->json(['data' => $shift === null ? null : (new CashShiftResource($shift))->resolve($request)]);
    }

    /**
     * Open a branch's register
     *
     * Fails with 409 `shift_already_open` when it is open already.
     */
    public function store(OpenShiftRequest $request, OpenShift $openShift): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $shift = $openShift->handle($request->actor(), $branch, $request->integer('opening_float'), $request->validated('notes'));

        return (new CashShiftResource($shift->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    /**
     * Show a cash shift
     *
     * With its payments, cash movements and totals by method.
     */
    public function show(CashShift $cashShift, #[CurrentUser] User $actor): CashShiftResource
    {
        $this->authorizeAny($actor);
        abort_unless(in_array($cashShift->branch_id, $this->visibleBranches($actor), true), 404);

        return new CashShiftResource($cashShift->load(self::DETAIL));
    }

    /**
     * Close a register
     *
     * Records the cash counted and the difference from what the shift's
     * payments, refunds and movements say should be there.
     */
    public function close(CloseShiftRequest $request, CashShift $cashShift, CloseShift $closeShift): CashShiftResource
    {
        abort_unless(in_array($cashShift->branch_id, $this->visibleBranches($request->actor()), true), 404);

        $shift = $closeShift->handle($request->actor(), $cashShift, $request->integer('counted_cash'), $request->validated('notes'));

        return new CashShiftResource($shift->load(self::DETAIL));
    }

    /**
     * Put cash into or take it out of the drawer
     *
     * For anything other than an order (change from the bank, buying
     * supplies). Taking out more than is in the drawer fails with 409.
     */
    public function movement(CashMovementRequest $request, CashShift $cashShift, RecordCashMovement $recordMovement): CashShiftResource
    {
        abort_unless(in_array($cashShift->branch_id, $this->visibleBranches($request->actor()), true), 404);

        $recordMovement->handle(
            $request->actor(),
            $cashShift,
            CashMovementType::from((string) $request->validated('type')),
            $request->integer('amount'),
            (string) $request->validated('reason'),
        );

        return new CashShiftResource($cashShift->refresh()->load(self::DETAIL));
    }

    /** Operating the register or reviewing shifts both allow reading them. */
    private function authorizeAny(User $actor): void
    {
        abort_unless($actor->can('shifts.operate') || $actor->can('shifts.view'), 403);
    }

    /**
     * @return list<int>
     */
    private function visibleBranches(User $actor): array
    {
        return array_values(array_map(intval(...), Branch::query()->visibleTo($actor)->pluck('id')->all()));
    }
}
