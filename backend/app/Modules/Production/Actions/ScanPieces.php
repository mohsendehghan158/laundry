<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Enums\StationKind;
use App\Modules\Production\Models\QcInspection;
use App\Modules\Production\Models\Station;
use App\Modules\Production\Support\PieceHistory;
use App\Modules\Production\Support\PieceSummary;
use App\Modules\Production\Support\Tags;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type ScannedPiece from PieceSummary
 *
 * @phpstan-type ScanResult array{tag: string, ok: bool, duplicate: bool, code: string|null, message: string|null, warnings: list<string>, piece: ScannedPiece|null}
 */
final class ScanPieces
{
    /** A second scan at the same station within this many seconds is the scanner repeating itself. */
    private const int REPEAT_SECONDS = 30;

    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * Records pieces reaching a station: one tag or a batch. Each tag is
     * handled on its own, so one bad tag does not stop the rest. At a
     * quality-control station every piece passes, or fails and is flagged
     * for rework. The first scan of an order puts it "in process".
     *
     * @param  list<string>  $tags
     * @param  list<string>  $defects  names of the defects found when failing
     * @return list<ScanResult>
     */
    public function handle(User $actor, Station $station, array $tags, ?bool $passed = null, ?string $reason = null, array $defects = []): array
    {
        $results = [];
        $seen = [];

        foreach ($tags as $raw) {
            $tag = Tags::normalize($raw);
            if ($tag === '' || isset($seen[$tag])) {
                continue;
            }
            $seen[$tag] = true;
            $results[] = DB::transaction(fn (): array => $this->scan($actor, $station, $tag, $passed, $reason, $defects));
        }

        return $results;
    }

    /**
     * @param  list<string>  $defects
     * @return ScanResult
     */
    private function scan(User $actor, Station $station, string $tag, ?bool $passed, ?string $reason, array $defects): array
    {
        $piece = Piece::query()->with(['order', 'line', 'defects', 'branch'])->where('tag_code', $tag)->lockForUpdate()->first();

        if ($piece === null) {
            return $this->failure($tag, 'piece_not_found');
        }

        $order = $piece->order;
        $error = match (true) {
            in_array($order->status, [OrderStatus::Draft, OrderStatus::PendingPickup, OrderStatus::PickedUp], true) => 'scan_order_not_received',
            ! $order->status->isOpen() => 'scan_order_closed',
            $piece->status === PieceStatus::HandedOver => 'scan_piece_handed_over',
            $piece->status === PieceStatus::Ready => 'scan_piece_ready',
            $piece->flag !== null && $piece->flag->blocksWork() => 'scan_piece_'.$piece->flag->value,
            default => null,
        };

        if ($error !== null) {
            return $this->failure($tag, $error, $piece);
        }

        $now = now();
        $repeat = $station->kind === StationKind::Process
            && $piece->station_id === $station->id
            && $piece->last_scanned_at !== null
            && $piece->last_scanned_at->gt($now->copy()->subSeconds(self::REPEAT_SECONDS));

        if ($repeat) {
            return $this->success($piece, [], duplicate: true);
        }

        $warnings = [];
        if ($piece->branch_id !== $station->branch_id) {
            $warnings[] = __('production.warnings.moved', ['branch' => $piece->branch->name ?? '—']);
        }
        if ($piece->status === PieceStatus::Assembled) {
            $warnings[] = __('production.warnings.unassembled');
        }
        if ($piece->flag === PieceFlag::Rework && $station->kind === StationKind::Process) {
            $warnings[] = __('production.warnings.rework', ['reason' => $piece->flag_note ?? '—']);
        }
        if ($piece->flag === PieceFlag::Damaged) {
            $warnings[] = __('production.warnings.damaged');
        }

        $changes = ['station_id' => $station->id, 'branch_id' => $station->branch_id, 'last_scanned_at' => $now];
        if ($piece->status !== PieceStatus::InProcess) {
            $changes += ['status' => PieceStatus::InProcess, 'assembled_at' => null];
        }

        if ($station->kind === StationKind::Qc) {
            QcInspection::query()->create([
                'organization_id' => $piece->organization_id,
                'piece_id' => $piece->id,
                'station_id' => $station->id,
                'user_id' => $actor->id,
                'passed' => (bool) $passed,
                'reason' => $passed ? null : $reason,
                'defects' => $passed || $defects === [] ? null : $defects,
            ]);

            $note = $passed ? null : trim(implode('، ', array_filter([$reason, ...$defects])));
            if ($passed && $piece->flag === PieceFlag::Rework) {
                $changes += ['flag' => null, 'flag_note' => null];
            } elseif (! $passed) {
                $changes += ['flag' => PieceFlag::Rework, 'flag_note' => $note === '' ? null : $note];
            }
            $piece->update($changes);
            PieceHistory::record($piece, $passed ? PieceEvent::QcPassed : PieceEvent::QcFailed, $actor, $station->id, $station->branch_id, $note === '' ? null : $note);
        } else {
            $piece->update($changes);
            PieceHistory::record($piece, PieceEvent::Scanned, $actor, $station->id, $station->branch_id);
        }

        if ($order->status === OrderStatus::Received) {
            $this->transition->handle($actor, $order, OrderStatus::InProcess);
        }

        if ($order->due_at->isPast()) {
            $warnings[] = __('production.warnings.overdue');
        }

        return $this->success($piece, $warnings);
    }

    /**
     * @param  list<string>  $warnings
     * @return ScanResult
     */
    private function success(Piece $piece, array $warnings, bool $duplicate = false): array
    {
        return ['tag' => $piece->tag_code, 'ok' => true, 'duplicate' => $duplicate, 'code' => null, 'message' => null, 'warnings' => $warnings, 'piece' => PieceSummary::of($piece)];
    }

    /**
     * @return ScanResult
     */
    private function failure(string $tag, string $code, ?Piece $piece = null): array
    {
        $replace = $piece === null ? [] : ['note' => $piece->flag_note ?? '—'];

        return ['tag' => $tag, 'ok' => false, 'duplicate' => false, 'code' => $code, 'message' => __("production.errors.{$code}", $replace), 'warnings' => [], 'piece' => $piece === null ? null : PieceSummary::of($piece)];
    }
}
