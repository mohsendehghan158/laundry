<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum DefectKind: string
{
    /** Damage noted at intake: torn, missing button, faded … */
    case Defect = 'defect';

    /** A stain to treat: oil, ink, blood … */
    case Stain = 'stain';
}
