<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Models\Station;

/**
 * A plant next to the shop's counter branch, with a washing station and
 * quality control at the plant and a pressing station at the counter.
 */
function workshop(object $shop): object
{
    $plant = Branch::factory()->for($shop->organization)->create(['code' => 'P1', 'type' => BranchType::Plant, 'name' => 'کارگاه']);

    return (object) [
        'plant' => $plant,
        'wash' => Station::factory()->create(['branch_id' => $plant->id, 'name' => 'شست‌وشو', 'code' => 'WASH']),
        'qc' => Station::factory()->create(['branch_id' => $plant->id, 'name' => 'کنترل کیفیت', 'code' => 'QC', 'kind' => 'qc']),
        'press' => Station::factory()->create(['branch_id' => $shop->branch->id, 'name' => 'اتو', 'code' => 'PRESS']),
    ];
}

/**
 * @param  list<string>  $tags
 * @param  array<string, mixed>  $extra
 * @return list<array<string, mixed>>
 */
function scan(Station $station, array $tags, array $extra = []): array
{
    return test()->postJson('/api/v1/scans', ['station_id' => $station->id, 'tags' => $tags, ...$extra])->assertOk()->json('data');
}

/**
 * A new order of one shirt at the shop's counter; returns it with its pieces.
 *
 * @return array<string, mixed>
 */
function shirtOrder(object $shop, int $quantity = 1): array
{
    return test()->postJson('/api/v1/orders', orderPayload($shop, ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => $quantity]]]))
        ->assertCreated()
        ->json('data');
}
