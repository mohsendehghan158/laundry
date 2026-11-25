<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Catalog\Models\DefectType;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Actions\ScanPieces;
use App\Modules\Production\Enums\StationKind;
use App\Modules\Production\Http\Requests\ScanRequest;
use App\Modules\Production\Models\Station;
use App\Modules\Production\Support\Board;
use App\Modules\Production\Support\Tags;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ScanController
{
    /**
     * Scan pieces at a station
     *
     * One tag or a batch. Each tag gets its own result: `ok` with the
     * piece and any warnings, or an error code and message; one bad tag
     * does not stop the rest. At a quality-control station send `passed`
     * (and, when failing, a `reason` or defects): failed pieces are
     * flagged for rework. A repeated scan within 30 seconds is reported
     * as `duplicate` and not counted twice.
     *
     * @response array{data: list<array{tag: string, ok: bool, duplicate: bool, code: string|null, message: string|null, warnings: list<string>, piece: array{id: int, tag_code: string, item_name: string, service_name: string, color_name: string|null, notes: string|null, defects: list<string>, status: string, flag: string|null, flag_note: string|null, order: array{id: int, ticket_number: string, due_at: string, is_express: bool, is_overdue: bool, piece_count: int, notes: string|null}}|null}>}
     */
    public function __invoke(ScanRequest $request, ScanPieces $scanPieces): JsonResponse
    {
        $station = Station::query()->with('branch')->findOrFail($request->integer('station_id'));
        abort_unless($station->branch !== null && $request->actor()->canAccessBranch($station->branch), 404);

        $passed = $request->validated('passed');
        if ($station->kind === StationKind::Qc && $passed === null) {
            throw ValidationException::withMessages(['passed' => __('validation.required', ['attribute' => __('validation.attributes.passed')])]);
        }

        /** @var list<int> $defectIds */
        $defectIds = $request->validated('defect_type_ids') ?? [];
        $defects = DefectType::query()->whereKey($defectIds)->pluck('name')->values()->all();

        if ($station->kind === StationKind::Qc && $passed === false && $defects === [] && blank($request->validated('reason'))) {
            throw ValidationException::withMessages(['reason' => __('validation.required', ['attribute' => __('validation.attributes.reason')])]);
        }

        /** @var list<string> $tags */
        $tags = $request->validated('tags');
        // Boards of the branches the pieces come from change too.
        $from = Piece::query()->whereIn('tag_code', array_map(Tags::normalize(...), $tags))->distinct()->pluck('branch_id')->all();
        $results = $scanPieces->handle(
            $request->actor(),
            $station,
            $tags,
            $station->kind === StationKind::Qc ? (bool) $passed : null,
            $request->validated('reason'),
            array_map(strval(...), $defects),
        );

        Board::changed([$station->branch_id, ...$from], 'scan');

        return response()->json(['data' => $results]);
    }
}
