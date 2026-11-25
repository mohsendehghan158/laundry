<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Logistics\Actions\SaveSlotTemplate;
use App\Modules\Logistics\Http\Requests\SaveSlotTemplateRequest;
use App\Modules\Logistics\Http\Resources\SlotTemplateResource;
use App\Modules\Logistics\Models\SlotTemplate;
use App\Modules\Organization\Models\Branch;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SlotTemplateController
{
    public function __construct(private readonly SaveSlotTemplate $save) {}

    /**
     * List weekly slot templates
     *
     * The weekly pickup and delivery windows of the user's branches, by
     * weekday and start time.
     */
    #[QueryParameter('branch_id', 'Only this branch\'s windows.', type: 'int')]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('logistics.view');

        $templates = SlotTemplate::query()
            ->whereIn('branch_id', Branch::query()->visibleTo($actor)->select('id'))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->orderBy('branch_id')
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();

        return SlotTemplateResource::collection($templates);
    }

    /**
     * Add a weekly window
     *
     * Its slots for the bookable days are made right away.
     */
    public function store(SaveSlotTemplateRequest $request): JsonResponse
    {
        $this->reachBranch($request->actor(), $request->integer('branch_id'));

        $template = $this->save->create($request->validated());

        return (new SlotTemplateResource($template))->response()->setStatusCode(201);
    }

    /**
     * Change a weekly window
     *
     * Future slots made from it that nobody booked follow the change;
     * booked ones keep their time and capacity.
     */
    public function update(SaveSlotTemplateRequest $request, SlotTemplate $template): SlotTemplateResource
    {
        $this->reachBranch($request->actor(), $template->branch_id);

        return new SlotTemplateResource($this->save->update($template, $request->validated()));
    }

    /**
     * Remove a weekly window
     *
     * Its future slots go too, except those already booked.
     */
    public function destroy(SlotTemplate $template, #[CurrentUser] User $actor): Response
    {
        Gate::authorize('logistics.manage');
        $this->reachBranch($actor, $template->branch_id);

        $this->save->delete($template);

        return response()->noContent();
    }

    private function reachBranch(User $actor, int $branchId): void
    {
        $branch = Branch::query()->find($branchId);
        abort_unless($branch !== null && $actor->canAccessBranch($branch), 404);
    }
}
