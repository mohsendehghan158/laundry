<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Support;

use App\Modules\Organization\Support\Settings;
use Carbon\CarbonImmutable;

/** The organization's pickup and delivery settings, typed. */
final readonly class LogisticsSettings
{
    public function __construct(private Settings $settings) {}

    public function timezone(): string
    {
        return (string) $this->settings->get('general.timezone');
    }

    /** How many days ahead, today included, customers can book. */
    public function bookingDays(): int
    {
        return max(1, (int) $this->settings->get('logistics.booking_days'));
    }

    /** Windows starting sooner than this many minutes from now are not offered. */
    public function leadMinutes(): int
    {
        return max(0, (int) $this->settings->get('logistics.lead_minutes'));
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }
}
