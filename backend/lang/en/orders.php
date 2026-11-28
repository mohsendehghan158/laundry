<?php

declare(strict_types=1);

return [
    'item_unavailable' => 'This item is not active in the catalog.',
    'department_invalid' => "This department does not belong to the customer's company or is inactive.",
    'no_price' => 'There is no price for “:item” with “:service”.',
    'whole_quantity' => 'This item is counted in whole pieces.',
    'tier_unavailable' => 'This turnaround tier is not active.',
    'discount_not_allowed' => 'You are not allowed to give discounts.',
    'discount_over_limit' => 'The discount exceeds the limit of :max percent.',
    'promo' => [
        'not_found' => 'This promo code is not valid.',
        'expired' => 'This promo code is not active now.',
        'min_order' => 'This code needs an order of at least :min rials.',
        'used_up' => 'This code has been used up.',
        'customer_limit' => 'This customer has already used this code.',
        'first_order_only' => "This code is only for a customer\'s first order.",
        'reason' => 'Promo code :code',
    ],
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
