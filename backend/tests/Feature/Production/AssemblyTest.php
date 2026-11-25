<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Support\Settings;

beforeEach(function () {
    $this->shop = shop();
    $this->work = workshop($this->shop);
    $this->manager = staff($this->shop, 'branch_manager');
    $this->actingAs($this->manager);
    $this->rack = $this->postJson('/api/v1/racks', ['branch_id' => $this->shop->branch->id, 'name' => 'قفسه جلو', 'code' => 'a', 'slot_count' => 3])
        ->assertCreated()
        ->json('data');
});

function gather(string $tag): array
{
    return test()->postJson('/api/v1/assembly/scan', ['tag' => $tag])->assertOk()->json('data');
}

it('numbers rack slots and resizes racks while keeping occupied slots', function () {
    expect(array_column($this->rack['slots'], 'code'))->toBe(['A-01', 'A-02', 'A-03']);

    $this->putJson("/api/v1/racks/{$this->rack['id']}", ['slot_count' => 5, 'name' => 'قفسه A'])
        ->assertOk()
        ->assertJsonPath('data.slots.4.code', 'A-05');
    $this->postJson('/api/v1/racks', ['branch_id' => $this->shop->branch->id, 'name' => 'x', 'code' => 'A', 'slot_count' => 1])->assertJsonValidationErrors('code');

    $order = shirtOrder($this->shop);
    assembleAll($order, ['rack_slot_id' => $this->rack['slots'][2]['id']]);

    $this->getJson('/api/v1/racks')->assertJsonPath('data.0.slots.2.orders', [$order['ticket_number']]);
    $this->putJson("/api/v1/racks/{$this->rack['id']}", ['slot_count' => 2])->assertConflict()->assertJsonPath('code', 'rack_slots_in_use');
    $this->deleteJson("/api/v1/racks/{$this->rack['id']}")->assertConflict();
    $this->putJson("/api/v1/racks/{$this->rack['id']}", ['slot_count' => 3])->assertOk()->assertJsonCount(3, 'data.slots');
});

it('gathers pieces by scanning, racks what is ready and finishes the order later', function () {
    $order = shirtOrder($this->shop, 3);
    [$a, $b, $c] = array_column($order['lines'][0]['pieces'], 'tag_code');

    $state = gather(strtolower($a));
    expect($state['counts'])->toBe(['total' => 3, 'assembled' => 1, 'ready' => 0, 'waiting' => 2, 'lost' => 0])
        ->and($state['complete'])->toBeFalse()
        ->and($state['order']['status'])->toBe('in_process')
        ->and($state['suggestion']['code'])->toBe('A-01');
    // Scanning again changes nothing.
    expect(gather($a)['counts']['assembled'])->toBe(1);

    $slot = $this->rack['slots'][0]['id'];
    $this->postJson("/api/v1/assembly/{$order['id']}/complete", ['rack_slot_id' => $slot])
        ->assertConflict()
        ->assertJsonPath('message', "همه قطعه‌ها مونتاژ نشده‌اند. جامانده: {$b}، {$c}");

    $partial = $this->postJson("/api/v1/assembly/{$order['id']}/complete", ['rack_slot_id' => $slot, 'partial' => true])->assertOk()->json('data');
    expect($partial['order'])->toMatchArray(['status' => 'partially_ready', 'rack_location' => 'A-01'])
        ->and($partial['pieces'][0])->toMatchArray(['status' => 'ready', 'slot' => 'A-01']);

    gather($b);
    $state = gather($c);
    expect($state['complete'])->toBeTrue()->and($state['suggestion']['code'])->toBe('A-01');

    $done = $this->postJson("/api/v1/assembly/{$order['id']}/complete", ['rack_slot_id' => $slot])->assertOk()->json('data');
    expect($done['order'])->toMatchArray(['status' => 'ready', 'rack_location' => 'A-01'])
        ->and($done['counts']['ready'])->toBe(3);

    $this->getJson("/api/v1/orders/{$order['id']}")->assertJsonPath('data.history.*.to_status', ['received', 'in_process', 'partially_ready', 'ready']);
    $this->getJson("/api/v1/pieces/{$a}")->assertJsonPath('data.rack_location', 'A-01')->assertJsonPath('data.events.*.event', ['assembled', 'racked']);

    // The next order gets the next empty slot; handing this one over frees A-01.
    expect($this->getJson('/api/v1/assembly/'.shirtOrder($this->shop)['id'])->json('data.suggestion.code'))->toBe('A-02');
    $this->postJson("/api/v1/orders/{$order['id']}/payments", ['method' => 'card', 'amount' => $order['total']])->assertCreated();
    $this->postJson("/api/v1/orders/{$order['id']}/handover", ['piece_ids' => array_column($order['lines'][0]['pieces'], 'id')])->assertOk();
    expect($this->getJson('/api/v1/assembly/'.shirtOrder($this->shop)['id'])->json('data.suggestion.code'))->toBe('A-01');
});

it('will not gather pieces that are held, in rework, lost or at another branch', function () {
    $this->actingAs($this->owner($this->shop->organization));
    $order = shirtOrder($this->shop, 4);
    [$held, $away, $redo, $lost] = $order['lines'][0]['pieces'];

    $this->postJson("/api/v1/pieces/{$held['id']}/hold", ['reason' => 'تأیید مشتری'])->assertOk();
    scan($this->work->wash, [$away['tag_code']]);
    scan($this->work->qc, [$redo['tag_code']], ['passed' => false, 'reason' => 'لکه']);
    Piece::query()->whereKey($redo['id'])->update(['branch_id' => $this->shop->branch->id]);
    Piece::query()->whereKey($lost['id'])->update(['flag' => 'lost']);

    $this->postJson('/api/v1/assembly/scan', ['tag' => $held['tag_code']])->assertConflict()->assertJsonPath('message', "{$held['tag_code']}: نگه داشته شده است: تأیید مشتری");
    $this->postJson('/api/v1/assembly/scan', ['tag' => $away['tag_code']])->assertConflict()->assertJsonPath('message', "{$away['tag_code']}: در «کارگاه» است؛ اول به این شعبه فرستاده شود.");
    $this->postJson('/api/v1/assembly/scan', ['tag' => $redo['tag_code']])->assertConflict()->assertJsonPath('message', "{$redo['tag_code']}: باید دوباره کار شود: لکه");
    $this->postJson('/api/v1/assembly/scan', ['tag' => 'X-1'])->assertNotFound();

    $state = $this->getJson("/api/v1/assembly/{$order['id']}")->json('data');
    expect(array_column($state['pieces'], 'blocker'))->toBe(['نگه داشته شده است: تأیید مشتری', 'در «کارگاه» است؛ اول به این شعبه فرستاده شود.', 'باید دوباره کار شود: لکه', 'مفقود اعلام شده است.'])
        ->and($state['counts']['lost'])->toBe(1);

    // Lost pieces do not hold the order back.
    $this->postJson("/api/v1/pieces/{$held['id']}/release")->assertOk();
    Piece::query()->whereKey([$away['id'], $redo['id']])->update(['branch_id' => $this->shop->branch->id, 'flag' => null]);
    $this->postJson("/api/v1/assembly/{$order['id']}/complete", ['piece_ids' => [$held['id'], $away['id'], $redo['id']], 'rack_location' => 'زیر پیشخوان'])
        ->assertOk()
        ->assertJsonPath('data.order.status', 'ready')
        ->assertJsonPath('data.order.rack_location', 'زیر پیشخوان');
});

it('sends a ready order back to the workshop and off the rack', function () {
    $order = assembleAll(shirtOrder($this->shop), ['rack_slot_id' => $this->rack['slots'][0]['id']]);

    $this->postJson("/api/v1/orders/{$order['id']}/transitions", ['to' => 'in_process', 'reason' => 'یقه تمیز نیست'])->assertOk();

    $this->getJson("/api/v1/pieces/{$order['lines'][0]['pieces'][0]['tag_code']}")
        ->assertJsonPath('data.status', 'in_process')
        ->assertJsonPath('data.events.2', fn (array $event) => $event['event'] === 'sent_back' && $event['note'] === 'یقه تمیز نیست');
    $this->getJson('/api/v1/racks')->assertJsonPath('data.0.slots.0.orders', []);
});

it('requires scanning when the organization says so, and racks only at the pickup branch', function () {
    $order = shirtOrder($this->shop);
    $pieceId = $order['lines'][0]['pieces'][0]['id'];
    $plantRack = $this->actingAs($this->owner($this->shop->organization))
        ->postJson('/api/v1/racks', ['branch_id' => $this->work->plant->id, 'name' => 'کارگاه', 'code' => 'P', 'slot_count' => 1])->json('data');

    gather($order['lines'][0]['pieces'][0]['tag_code']);
    $this->postJson("/api/v1/assembly/{$order['id']}/complete", ['rack_slot_id' => $plantRack['slots'][0]['id']])->assertJsonValidationErrors('rack_slot_id');

    app(Settings::class)->put(['production.assembly_scan_required' => true]);
    $second = shirtOrder($this->shop);
    $this->postJson("/api/v1/assembly/{$second['id']}/complete", ['piece_ids' => [$second['lines'][0]['pieces'][0]['id']]])
        ->assertConflict()
        ->assertJsonPath('code', 'assembly_scan_required');
    expect($pieceId)->toBeInt();
});

it('keeps assembly to those who work with the order', function () {
    $order = shirtOrder($this->shop);

    $this->actingAs($this->userWithRole('plant_operator', $this->shop->organization, [$this->work->plant]));
    $this->getJson("/api/v1/assembly/{$order['id']}")->assertNotFound();

    $this->actingAs($this->userWithRole('driver', $this->shop->organization, [$this->shop->branch]));
    $this->postJson('/api/v1/assembly/scan', ['tag' => $order['lines'][0]['pieces'][0]['tag_code']])->assertForbidden();
    $this->postJson('/api/v1/racks', ['branch_id' => $this->shop->branch->id, 'name' => 'x', 'code' => 'Z', 'slot_count' => 1])->assertForbidden();
});
