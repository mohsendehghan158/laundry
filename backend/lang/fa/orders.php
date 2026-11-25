<?php

declare(strict_types=1);

return [
    'item_unavailable' => 'این قلم در کاتالوگ فعال نیست.',
    'no_price' => 'برای «:item» با خدمت «:service» قیمتی تعریف نشده است.',
    'whole_quantity' => 'تعداد این قلم باید عدد صحیح باشد.',
    'tier_unavailable' => 'این سطح تحویل فعال نیست.',
    'discount_not_allowed' => 'شما اجازه دادن تخفیف ندارید.',
    'discount_over_limit' => 'تخفیف بیشتر از سقف مجاز (:max درصد) است.',
    'promo' => [
        'not_found' => 'این کد تخفیف معتبر نیست.',
        'expired' => 'این کد تخفیف در این زمان فعال نیست.',
        'min_order' => 'این کد برای سفارش‌های دست‌کم :min ریال است.',
        'used_up' => 'ظرفیت استفاده از این کد تمام شده است.',
        'customer_limit' => 'این مشتری قبلاً از این کد استفاده کرده است.',
        'first_order_only' => 'این کد فقط برای اولین سفارش مشتری است.',
        'reason' => 'کد تخفیف :code',
    ],
    'statuses' => [
        'draft' => 'پیش‌نویس',
        'pending_pickup' => 'منتظر جمع‌آوری',
        'picked_up' => 'جمع‌آوری‌شده',
        'received' => 'پذیرش‌شده',
        'in_process' => 'در حال انجام',
        'partially_ready' => 'بخشی آماده',
        'ready' => 'آماده تحویل',
        'out_for_delivery' => 'در مسیر تحویل',
        'completed' => 'تحویل‌شده',
        'cancelled' => 'لغوشده',
    ],
];
