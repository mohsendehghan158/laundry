<?php

declare(strict_types=1);

namespace App\Modules\Portal\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $mobile
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $ip
 * @property Carbon $created_at
 */
class OtpCode extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
