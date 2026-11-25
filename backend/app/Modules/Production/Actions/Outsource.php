<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Sequences;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Enums\ShipmentStatus;
use App\Modules\Production\Models\OutsourcingShipment;
use App\Modules\Production\Models\Vendor;
use App\Modules\Production\Support\Movable;
use App\Modules\Production\Support\PieceHistory;
use App\Modules\Production\Support\Tags;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Work sent to an outside vendor and tracked until every piece is back.
 *
 * @phpstan-import-type TagResult from MoveTransfer
 */
final class Outsource
{
    public function __construct(private readonly Sequences $sequences) {}

    /**
     * Sends pieces to a vendor: all of them, or none if one cannot go.
     *
     * @param  list<string>  $tags
     */
    public function send(User $actor, Vendor $vendor, Branch $branch, array $tags, ?Carbon $expectedAt, ?int $cost, ?string $note): OutsourcingShipment
    {
        return DB::transaction(function () use ($actor, $vendor, $branch, $tags, $expectedAt, $cost, $note): OutsourcingShipment {
            $pieces = [];
            $problems = [];
            foreach (array_unique(array_filter(array_map(Tags::normalize(...), $tags))) as $tag) {
                $piece = Piece::query()->where('tag_code', $tag)->lockForUpdate()->first();
                $blocker = $piece === null ? __('production.errors.piece_not_found') : Movable::blocker($piece, $branch->id);
                if ($blocker !== null) {
                    $problems[] = "{$tag}: {$blocker}";
                } else {
                    $pieces[] = $piece;
                }
            }

            if ($problems !== []) {
                throw new ApiException('outsourcing_pieces_refused', implode(' — ', $problems));
            }
            if ($pieces === []) {
                throw ApiException::conflict('transfer_empty');
            }

            $shipment = OutsourcingShipment::query()->create([
                'organization_id' => $actor->organization_id,
                'number' => 'OS-'.str_pad((string) $this->sequences->next($actor->organization_id, 'outsourcing'), 5, '0', STR_PAD_LEFT),
                'vendor_id' => $vendor->id,
                'branch_id' => $branch->id,
                'status' => ShipmentStatus::Sent,
                'expected_at' => $expectedAt,
                'cost' => $cost,
                'note' => $note,
                'sent_by' => $actor->id,
                'sent_at' => now(),
            ]);

            foreach ($pieces as $piece) {
                $shipment->pieces()->attach($piece->id);
                $piece->update(['flag' => PieceFlag::Outsourced, 'flag_note' => $vendor->name.' · '.$shipment->number]);
                PieceHistory::record($piece, PieceEvent::Outsourced, $actor, note: $vendor->name.' · '.$shipment->number);
            }

            return $shipment;
        });
    }

    /**
     * Checks pieces back in from the vendor; the shipment closes once all are back.
     *
     * @param  list<string>  $tags
     * @return list<TagResult>
     */
    public function receive(User $actor, OutsourcingShipment $shipment, array $tags): array
    {
        $results = [];
        foreach (array_unique(array_filter(array_map(Tags::normalize(...), $tags))) as $tag) {
            $results[] = DB::transaction(function () use ($actor, $shipment, $tag): array {
                $piece = Piece::query()->where('tag_code', $tag)->lockForUpdate()->first();
                $row = $piece === null ? null : DB::table('outsourcing_shipment_pieces')->where('outsourcing_shipment_id', $shipment->id)->where('piece_id', $piece->id)->first();

                if ($piece === null || $row === null) {
                    return ['tag' => $tag, 'ok' => false, 'message' => $piece === null ? __('production.errors.piece_not_found') : __('production.move.not_listed', ['number' => $shipment->number])];
                }

                if ($row->returned_at === null) {
                    DB::table('outsourcing_shipment_pieces')->where('id', $row->id)->update(['returned_at' => now(), 'returned_by' => $actor->id]);
                    $piece->update([
                        'flag' => Movable::restingFlag($piece),
                        'flag_note' => null,
                        'status' => $piece->status === PieceStatus::Received ? PieceStatus::InProcess : $piece->status,
                        'branch_id' => $shipment->branch_id,
                    ]);
                    PieceHistory::record($piece, PieceEvent::Returned, $actor, branchId: $shipment->branch_id, note: $shipment->vendor->name.' · '.$shipment->number);
                }

                return ['tag' => $tag, 'ok' => true, 'message' => null];
            });
        }

        if (! DB::table('outsourcing_shipment_pieces')->where('outsourcing_shipment_id', $shipment->id)->whereNull('returned_at')->exists()) {
            $shipment->update(['status' => ShipmentStatus::Closed, 'closed_at' => $shipment->closed_at ?? now()]);
        }

        return $results;
    }
}
