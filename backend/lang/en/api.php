<?php

declare(strict_types=1);

return [
    'errors' => [
        'validation_failed' => 'The given data was invalid.',
        'unauthenticated' => 'You need to sign in to continue.',
        'forbidden' => 'You are not allowed to do this.',
        'not_found' => 'The requested item was not found.',
        'method_not_allowed' => 'This operation is not allowed on this address.',
        'conflict' => 'This operation conflicts with the current state.',
        'csrf_mismatch' => 'Your session has expired. Refresh the page and try again.',
        'too_many_requests' => 'Too many requests. Please try again shortly.',
        'bad_request' => 'The request is invalid.',
        'service_unavailable' => 'The service is temporarily unavailable.',
        'http_error' => 'The request could not be completed.',
        'server_error' => 'Something went wrong. Please try again.',

        'branch_last' => 'The last branch cannot be deleted.',
        'user_cannot_delete_self' => 'You cannot delete your own account.',
        'user_cannot_deactivate_self' => 'You cannot deactivate your own account.',
        'user_last_owner' => 'At least one active owner must remain.',
        'role_owner_immutable' => 'The permissions of the owner role cannot be changed.',
        'role_permission_beyond_own' => 'You cannot grant a permission you do not hold yourself.',

        'category_has_items' => 'This category still has items; move or delete them first.',

        'order_branch_unavailable' => 'You cannot work in this branch, or it is inactive.',
        'order_branch_not_counter' => 'A plant does not take orders directly; choose a counter branch.',
        'order_not_editable' => 'Order lines can only change before work starts.',
        'order_version_conflict' => 'Someone else changed this order in the meantime. Reload and try again.',
        'order_total_below_paid' => 'The new total is below what has been paid; refund first.',
        'order_transition_not_allowed' => 'The order cannot go from “:from” to “:to”.',
        'order_has_payments' => 'This order has payments; refund them first.',

        'shift_already_open' => 'This branch\'s register is already open.',
        'shift_not_open' => 'The branch\'s register is closed; open it to take cash.',
        'shift_cash_insufficient' => 'That is more cash than the drawer holds.',
        'payment_order_cancelled' => 'A cancelled order cannot be paid.',
        'payment_exceeds_balance' => 'The amount is more than the order\'s balance.',
        'refund_exceeds_paid' => 'The refund is more than what was paid.',

        'handover_not_ready' => 'The order is not ready yet.',
        'handover_unpaid' => 'The order has an unpaid balance; take the payment first.',
        'handover_piece_unavailable' => 'Some chosen pieces are not ready or not part of this order.',

        'piece_cannot_hold' => 'This piece cannot be put on hold (it is ready, handed over or in another state).',
        'piece_not_held' => 'This piece is not on hold.',

        'assembly_order_not_open' => 'This order cannot be assembled in its current state.',
        'assembly_piece_done' => 'Piece :tag is already ready or handed over.',
        'assembly_scan_required' => 'Every piece must be scanned at the assembly screen here.',
        'assembly_nothing_gathered' => 'No piece of this order has been gathered yet.',
        'assembly_incomplete' => 'Not every piece has been gathered. Missing: :tags',
        'rack_slots_in_use' => 'Orders still wait in these rack slots.',

        'notification_not_failed' => 'Only failed messages can be sent again.',
    ],
];
