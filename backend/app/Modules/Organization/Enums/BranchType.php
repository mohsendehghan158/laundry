<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

enum BranchType: string
{
    /** Counter only: takes orders in and hands them back. */
    case Store = 'store';

    /** Production only: cleans items received from stores. */
    case Plant = 'plant';

    /** Counter and production under one roof. */
    case Hybrid = 'hybrid';
}
