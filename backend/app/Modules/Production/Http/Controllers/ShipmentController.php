<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Actions\Outsource;
use App\Modules\Production\Concerns\ReachesBranches;
use App\Modules\Production\Http\Requests\StoreShipmentRequest;
use App\Modules\Production\Http\Requests\TagsRequest;
use App\Modules\Production\Http\Resources\ShipmentResource;
use App\Modules\Production\Models\OutsourcingShipment;
use App\Modules\Production\Models\Vendor;
use App\Modules\Production\Support\Board;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ShipmentController
{
    use ReachesBranches;

    private const array DETAIL = ['vendor', 'branch', 'sender', 'pieces.line', 'pieces.order'];

    /**
     * List outsourcing shipments
     *
     * Of the user's branches, newest first: those still at the vendor, or
     * all with `status`.
     */
    #[QueryParameter('status', 'sent or closed; default: sent.', type: 'string')]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        abort_unless($actor->can('production.outsource'), 403);
        $request->validate(['status' => ['nullable', Rule::in(['sent', 'closed'])]]);
        $mine = $this->branchIds($actor);

        $shipments = OutsourcingShipment::query()
            ->with(self::DETAIL)
            ->when($mine !== null, fn ($query) => $query->whereIn('branch_id', $mine))
            ->where('status', $request->input('status', 'sent'))
            ->latest('id')
            ->limit(100)
            ->get();

        return ShipmentResource::collection($shipments);
    }

    /**
     * Send pieces to a vendor
     *
     * All the scanned pieces go, or none: fails with 409
     * `outsourcing_pieces_refused` naming each piece that cannot leave.
     */
    public function store(StoreShipmentRequest $request, Outsource $outsource): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        abort_unless($request->actor()->canAccessBranch($branch), 404);
        $vendor = Vendor::query()->findOrFail($request->integer('vendor_id'));
        /** @var list<string> $tags */
        $tags = $request->validated('tags');

        $shipment = $outsource->send(
            $request->actor(),
            $vendor,
            $branch,
            $tags,
            $request->filled('expected_at') ? Carbon::parse((string) $request->validated('expected_at')) : null,
            $request->validated('cost') === null ? null : (int) $request->validated('cost'),
            $request->validated('note'),
        );
        Board::changed([$branch->id], 'outsourcing');

        return (new ShipmentResource($shipment->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    /**
     * Show a shipment
     */
    public function show(OutsourcingShipment $shipment, #[CurrentUser] User $actor): ShipmentResource
    {
        $this->reach($actor, $shipment);

        return new ShipmentResource($shipment->load(self::DETAIL));
    }

    /**
     * Check pieces back in from the vendor
     *
     * Tag by tag; back pieces return to work at the branch. The shipment
     * closes once every piece is back.
     *
     * @response array{data: ShipmentResource, results: list<array{tag: string, ok: bool, message: string|null}>}
     */
    public function receive(TagsRequest $request, OutsourcingShipment $shipment, Outsource $outsource): JsonResponse
    {
        $this->reach($request->actor(), $shipment);
        $results = $outsource->receive($request->actor(), $shipment->load('vendor'), $request->tags());
        Board::changed([$shipment->branch_id], 'outsourcing');

        return response()->json(['data' => (new ShipmentResource($shipment->refresh()->load(self::DETAIL)))->resolve($request), 'results' => $results]);
    }

    /**
     * Update a shipment's cost, expected return or note
     */
    public function update(Request $request, OutsourcingShipment $shipment, #[CurrentUser] User $actor): ShipmentResource
    {
        $this->reach($actor, $shipment);
        /** @var array{cost?: int|null, expected_at?: string|null, note?: string|null} $input */
        $input = $request->validate([
            'cost' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'expected_at' => ['sometimes', 'nullable', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $shipment->update($input);

        return new ShipmentResource($shipment->load(self::DETAIL));
    }

    private function reach(User $actor, OutsourcingShipment $shipment): void
    {
        abort_unless($actor->can('production.outsource'), 403);
        abort_unless($this->inBranches($actor, $shipment->branch_id), 404);
    }
}
