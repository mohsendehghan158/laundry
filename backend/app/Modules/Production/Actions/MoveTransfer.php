<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Sequences;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Enums\TransferStatus;
use App\Modules\Production\Models\Transfer;
use App\Modules\Production\Support\Movable;
use App\Modules\Production\Support\PieceHistory;
use App\Modules\Production\Support\Tags;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * A transfer between branches: filled by scanning at the sending branch,
 * sent with a bag count, then checked in piece by piece at the other end.
 * Pieces are "in transit" from sending until they are scanned in.
 *
 * @phpstan-type TagResult array{tag: string, ok: bool, message: string|null}
 */
final class MoveTransfer
{
    public function __construct(private readonly Sequences $sequences) {}

    public function create(User $actor, Branch $from, Branch $to, ?string $note): Transfer
    {
        return DB::transaction(fn (): Transfer => Transfer::query()->create([
            'organization_id' => $actor->organization_id,
            'number' => 'TR-'.str_pad((string) $this->sequences->next($actor->organization_id, 'transfer'), 5, '0', STR_PAD_LEFT),
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'status' => TransferStatus::Open,
            'note' => $note,
            'created_by' => $actor->id,
        ]));
    }

    /**
     * @param  list<string>  $tags
     * @return list<TagResult>
     */
    public function add(Transfer $transfer, array $tags): array
    {
        $this->expect($transfer, TransferStatus::Open);

        return $this->each($tags, function (Piece $piece) use ($transfer): ?string {
            if ($transfer->pieces()->whereKey($piece->id)->exists()) {
                return null;
            }
            $blocker = Movable::blocker($piece, $transfer->from_branch_id);
            if ($blocker === null) {
                $transfer->pieces()->attach($piece->id);
            }

            return $blocker;
        });
    }

    public function remove(Transfer $transfer, Piece $piece): void
    {
        $this->expect($transfer, TransferStatus::Open);
        $transfer->pieces()->detach($piece->id);
    }

    public function send(User $actor, Transfer $transfer, int $bags): Transfer
    {
        return DB::transaction(function () use ($actor, $transfer, $bags): Transfer {
            $transfer = Transfer::query()->with('toBranch')->lockForUpdate()->findOrFail($transfer->id);
            $this->expect($transfer, TransferStatus::Open);

            $pieces = $transfer->pieces()->get();
            if ($pieces->isEmpty()) {
                throw ApiException::conflict('transfer_empty');
            }

            foreach ($pieces as $piece) {
                $piece->update(['flag' => PieceFlag::InTransit, 'flag_note' => $transfer->number]);
                PieceHistory::record($piece, PieceEvent::TransferredOut, $actor, note: $transfer->number.' → '.$transfer->toBranch->name);
            }
            $transfer->update(['status' => TransferStatus::Sent, 'bags_sent' => $bags, 'sent_by' => $actor->id, 'sent_at' => now()]);

            return $transfer;
        });
    }

    /**
     * Checks pieces in at the receiving branch, as they come out of the bags.
     *
     * @param  list<string>  $tags
     * @return list<TagResult>
     */
    public function receive(User $actor, Transfer $transfer, array $tags): array
    {
        if (! in_array($transfer->status, [TransferStatus::Sent, TransferStatus::Received], true)) {
            throw ApiException::conflict('transfer_not_sent');
        }

        return $this->each($tags, function (Piece $piece) use ($actor, $transfer): ?string {
            $row = DB::table('transfer_pieces')->where('transfer_id', $transfer->id)->where('piece_id', $piece->id)->first();
            if ($row === null) {
                return __('production.move.not_listed', ['number' => $transfer->number]);
            }
            if ($row->received_at === null) {
                $this->checkIn($actor, $transfer, $piece);
            }

            return null;
        });
    }

    /**
     * Closes the transfer at the receiving branch. With `all`, every piece
     * not scanned in yet is checked in too (a sealed bag trusted as is);
     * otherwise they stay in transit and show up as missing.
     */
    public function close(User $actor, Transfer $transfer, ?int $bags, bool $all): Transfer
    {
        return DB::transaction(function () use ($actor, $transfer, $bags, $all): Transfer {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->expect($transfer, TransferStatus::Sent);

            if ($all) {
                foreach ($transfer->pieces()->wherePivotNull('received_at')->get() as $piece) {
                    $this->checkIn($actor, $transfer, $piece);
                }
            }
            $transfer->update(['status' => TransferStatus::Received, 'bags_received' => $bags, 'received_by' => $actor->id, 'received_at' => now()]);

            return $transfer;
        });
    }

    public function cancel(Transfer $transfer): void
    {
        $this->expect($transfer, TransferStatus::Open);

        DB::transaction(function () use ($transfer): void {
            $transfer->pieces()->detach();
            $transfer->update(['status' => TransferStatus::Cancelled]);
        });
    }

    private function checkIn(User $actor, Transfer $transfer, Piece $piece): void
    {
        DB::table('transfer_pieces')->where('transfer_id', $transfer->id)->where('piece_id', $piece->id)->update(['received_at' => now()]);
        $piece->update(['branch_id' => $transfer->to_branch_id, 'station_id' => null, 'flag' => Movable::restingFlag($piece), 'flag_note' => null]);
        PieceHistory::record($piece, PieceEvent::TransferredIn, $actor, branchId: $transfer->to_branch_id, note: $transfer->number);
    }

    private function expect(Transfer $transfer, TransferStatus $status): void
    {
        if ($transfer->status !== $status) {
            throw ApiException::conflict('transfer_wrong_status', ['status' => __("production.transfer_statuses.{$transfer->status->value}")]);
        }
    }

    /**
     * Runs a step for each scanned tag; the step returns why it refused, or null.
     *
     * @param  list<string>  $tags
     * @param  callable(Piece): ?string  $step
     * @return list<TagResult>
     */
    private function each(array $tags, callable $step): array
    {
        $results = [];
        foreach (array_unique(array_filter(array_map(Tags::normalize(...), $tags))) as $tag) {
            $piece = Piece::query()->where('tag_code', $tag)->first();
            $message = $piece === null ? __('production.errors.piece_not_found') : DB::transaction(fn (): ?string => $step($piece));
            $results[] = ['tag' => $tag, 'ok' => $message === null, 'message' => $message];
        }

        return $results;
    }
}
