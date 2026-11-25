<?php

declare(strict_types=1);

return [
    'errors' => [
        'piece_not_found' => 'No piece has this code.',
        'scan_order_not_received' => 'This piece\'s order has not been checked in at a branch yet.',
        'scan_order_closed' => 'This piece\'s order is closed or cancelled.',
        'scan_piece_handed_over' => 'This piece has been handed back to the customer.',
        'scan_piece_ready' => 'This piece is ready and on the rack; send the order back to the workshop to work on it again.',
        'scan_piece_on_hold' => 'This piece is on hold: :note',
        'scan_piece_outsourced' => 'This piece is at an outside vendor; record its return first.',
        'scan_piece_in_transit' => 'This piece is on a transfer; receive the transfer first.',
        'scan_piece_lost' => 'This piece has been reported lost.',
    ],
    'warnings' => [
        'moved' => 'The piece came here from ":branch" without a transfer.',
        'unassembled' => 'The piece left assembly and is back in work.',
        'rework' => 'Rework: :reason',
        'damaged' => 'This piece is recorded as damaged.',
        'overdue' => 'This order is past its due time.',
    ],
    'events' => [
        'scanned' => 'Scanned at station',
        'qc_passed' => 'Passed quality control',
        'qc_failed' => 'Failed quality control',
        'held' => 'Put on hold',
        'released' => 'Released',
        'assembled' => 'Assembled',
        'racked' => 'Put on the rack',
        'sent_back' => 'Sent back to the workshop',
        'transferred_out' => 'Sent on a transfer',
        'transferred_in' => 'Received from a transfer',
        'outsourced' => 'Sent to a vendor',
        'returned' => 'Back from the vendor',
        'damaged' => 'Recorded as damaged',
        'lost' => 'Reported lost',
        'found' => 'Found',
        'handed_over' => 'Handed to the customer',
    ],
];
