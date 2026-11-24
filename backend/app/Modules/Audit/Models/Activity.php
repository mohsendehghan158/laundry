<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * An audit log entry, kept inside the organization it happened in.
 *
 * @property int|null $organization_id
 */
class Activity extends BaseActivity
{
    use BelongsToOrganization;
}
