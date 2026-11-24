<?php

declare(strict_types=1);

return [
    'item_unavailable' => 'This item is not active in the catalog.',
    'no_price' => 'There is no price for “:item” with “:service”.',
    'whole_quantity' => 'This item is counted in whole pieces.',
    'tier_unavailable' => 'This turnaround tier is not active.',
    'discount_not_allowed' => 'You are not allowed to give discounts.',
    'discount_over_limit' => 'The discount exceeds the limit of :max percent.',
    'statuses' => [
        'draft' => 'Draft',
        'pending_pickup' => 'Awaiting pickup',
        'picked_up' => 'Picked up',
        'received' => 'Received',
        'in_process' => 'In process',
        'partially_ready' => 'Partly ready',
        'ready' => 'Ready',
        'out_for_delivery' => 'Out for delivery',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
];
