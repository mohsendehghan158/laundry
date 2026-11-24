<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use Illuminate\Support\Facades\DB;

/**
 * Hands out the next number of a named counter (e.g. "ticket:T1") for an
 * organization. A single upsert with RETURNING increments and reads the
 * value atomically, so two counters taking an order at the same moment
 * never get the same number. Used inside the caller's transaction, a
 * rolled-back order also gives its number back.
 */
final class Sequences
{
    public function next(int $organizationId, string $key): int
    {
        $row = DB::selectOne(
            'INSERT INTO sequences (organization_id, key, value, created_at, updated_at)
             VALUES (?, ?, 1, now(), now())
             ON CONFLICT (organization_id, key)
             DO UPDATE SET value = sequences.value + 1, updated_at = now()
             RETURNING value',
            [$organizationId, $key],
        );

        return (int) $row->value;
    }
}
