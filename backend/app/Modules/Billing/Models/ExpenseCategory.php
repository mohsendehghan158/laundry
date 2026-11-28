<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExpenseCategory extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Gives an organization that has never had categories a starting set.
     */
    public static function ensureDefaults(): void
    {
        if (self::query()->withTrashed()->exists()) {
            return;
        }

        foreach ((array) __('billing.expense_categories') as $name) {
            self::query()->create(['name' => (string) $name]);
        }
    }
}
