<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Enums\DefectKind;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $piece_id
 * @property int|null $defect_type_id
 * @property string $name
 * @property DefectKind $kind
 */
class PieceDefect extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => DefectKind::class];
    }
}
