<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

require_once __DIR__.'/Support/shop.php';
require_once __DIR__.'/Support/production.php';
require_once __DIR__.'/Support/portal.php';
