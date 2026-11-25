<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Support\Sequences;
use App\Modules\Production\Enums\ClaimKind;
use App\Modules\Production\Enums\ClaimStatus;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Models\Claim;
use App\Modules\Production\Support\PieceHistory;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Damage and loss cases: opened against a piece, then settled (resolved,
 * with any compensation) or rejected (not upheld, or the piece was found).
 */
final class HandleClaim
{
    public function __construct(private readonly Sequences $sequences) {}

    public function open(User $actor, Piece $piece, ClaimKind $kind, string $description): Claim
    {
        $piece->loadMissing('order');

        if ($piece->order->status === OrderStatus::Cancelled || ($kind === ClaimKind::Lost && $piece->status === PieceStatus::HandedOver)) {
            throw ApiException::conflict('claim_not_allowed');
        }
        if ($piece->claims()->where('kind', $kind)->where('status', ClaimStatus::Open)->exists()) {
            throw ApiException::conflict('claim_already_open');
        }

        return DB::transaction(function () use ($actor, $piece, $kind, $description): Claim {
            $claim = Claim::query()->create([
                'organization_id' => $piece->organization_id,
                'number' => 'CL-'.str_pad((string) $this->sequences->next($piece->organization_id, 'claim'), 5, '0', STR_PAD_LEFT),
                'order_id' => $piece->order_id,
                'piece_id' => $piece->id,
                'kind' => $kind,
                'status' => ClaimStatus::Open,
                'description' => $description,
                'created_by' => $actor->id,
            ]);

            if ($kind === ClaimKind::Lost) {
                $piece->update(['flag' => PieceFlag::Lost, 'flag_note' => $claim->number]);
            } elseif ($piece->flag === null) {
                $piece->update(['flag' => PieceFlag::Damaged, 'flag_note' => $claim->number]);
            }
            PieceHistory::record($piece, $kind === ClaimKind::Lost ? PieceEvent::Lost : PieceEvent::Damaged, $actor, note: $claim->number.': '.$description);
            activity('production')->causedBy($actor)->performedOn($claim)->event('claim_opened')
                ->withProperties(['number' => $claim->number, 'kind' => $kind->value, 'piece' => $piece->tag_code])
                ->log('claim_opened');

            return $claim;
        });
    }

    public function resolve(User $actor, Claim $claim, string $resolution, ?int $compensation): Claim
    {
        $this->expectOpen($claim);

        return DB::transaction(function () use ($actor, $claim, $resolution, $compensation): Claim {
            $claim->update(['status' => ClaimStatus::Resolved, 'resolution' => $resolution, 'compensation' => $compensation, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            activity('production')->causedBy($actor)->performedOn($claim)->event('claim_resolved')
                ->withProperties(['number' => $claim->number, 'compensation' => $compensation])
                ->log('claim_resolved');

            return $claim;
        });
    }

    /** Not upheld; a lost piece rejected this way has been found and goes back to work. */
    public function reject(User $actor, Claim $claim, string $resolution): Claim
    {
        $this->expectOpen($claim);

        return DB::transaction(function () use ($actor, $claim, $resolution): Claim {
            $claim->update(['status' => ClaimStatus::Rejected, 'resolution' => $resolution, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $piece = $claim->piece;

            if ($claim->kind === ClaimKind::Lost && $piece->flag === PieceFlag::Lost) {
                $piece->update(['flag' => null, 'flag_note' => null]);
                PieceHistory::record($piece, PieceEvent::Found, $actor, note: $claim->number.': '.$resolution);
            } elseif ($claim->kind === ClaimKind::Damaged && $piece->flag === PieceFlag::Damaged) {
                $piece->update(['flag' => null, 'flag_note' => null]);
            }
            activity('production')->causedBy($actor)->performedOn($claim)->event('claim_rejected')
                ->withProperties(['number' => $claim->number])
                ->log('claim_rejected');

            return $claim;
        });
    }

    private function expectOpen(Claim $claim): void
    {
        if ($claim->status !== ClaimStatus::Open) {
            throw ApiException::conflict('claim_closed');
        }
    }
}
