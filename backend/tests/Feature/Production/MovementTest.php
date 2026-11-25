<?php

declare(strict_types=1);

use App\Modules\Orders\Models\Piece;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->shop = shop();
    $this->work = workshop($this->shop);
    $this->cashier = staff($this->shop);
    $this->operator = $this->userWithRole('plant_operator', $this->shop->organization, [$this->work->plant]);
    $this->manager = staff($this->shop, 'branch_manager');
    $this->manager->branches()->attach($this->work->plant->id);
});

/** @return list<array<string, mixed>> */
function piecesOf(array $order): array
{
    return $order['lines'][0]['pieces'];
}

it('sends pieces to the plant with a manifest and checks them in there', function () {
    $this->actingAs($this->cashier);
    [$a, $b, $c] = piecesOf(shirtOrder($this->shop, 3));
    $other = piecesOf(shirtOrder($this->shop))[0];

    expect($this->getJson('/api/v1/transfer-destinations')->json('data.*.name'))->toEqualCanonicalizing(['کارگاه', $this->shop->branch->name]);
    $transfer = $this->postJson('/api/v1/transfers', ['from_branch_id' => $this->shop->branch->id, 'to_branch_id' => $this->work->plant->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->json('data');
    expect($transfer['number'])->toBe('TR-00001');

    $busy = $this->postJson('/api/v1/transfers', ['from_branch_id' => $this->shop->branch->id, 'to_branch_id' => $this->work->plant->id])->json('data');
    $this->postJson("/api/v1/transfers/{$busy['id']}/pieces", ['tags' => [$other['tag_code']]])->assertOk();

    $added = $this->postJson("/api/v1/transfers/{$transfer['id']}/pieces", ['tags' => [$a['tag_code'], strtolower($b['tag_code']), $c['tag_code'], $other['tag_code'], 'NOPE']])
        ->assertOk()
        ->json();
    expect(array_column($added['results'], 'ok'))->toBe([true, true, true, false, false])
        ->and($added['results'][3]['message'])->toBe('این قطعه در بارنامه باز TR-00002 است.')
        ->and($added['data']['piece_count'])->toBe(3);
    $this->deleteJson("/api/v1/transfers/{$transfer['id']}/pieces/{$c['id']}")->assertOk()->assertJsonPath('data.piece_count', 2);

    $this->postJson("/api/v1/transfers/{$transfer['id']}/send", ['bags' => 1])
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.bags_sent', 1);
    $this->getJson("/api/v1/pieces/{$a['tag_code']}")
        ->assertJsonPath('data.flag', 'in_transit')
        ->assertJsonPath('data.movement', ['kind' => 'transfer', 'id' => $transfer['id'], 'number' => 'TR-00001', 'to' => 'کارگاه']);

    // The counter cannot check it in at the plant; the plant cannot work on it before it does.
    $this->postJson("/api/v1/transfers/{$transfer['id']}/receive", ['tags' => [$a['tag_code']]])->assertNotFound();
    $this->actingAs($this->operator);
    expect(scan($this->work->wash, [$a['tag_code']])[0]['code'])->toBe('scan_piece_in_transit');
    // The plant can look up what is on its way to it.
    $this->getJson("/api/v1/pieces/{$c['tag_code']}")->assertNotFound();
    $this->getJson("/api/v1/pieces/{$b['tag_code']}")->assertOk()->assertJsonPath('data.movement.number', 'TR-00001');

    $received = $this->postJson("/api/v1/transfers/{$transfer['id']}/receive", ['tags' => [$a['tag_code'], $c['tag_code']]])->assertOk()->json();
    expect(array_column($received['results'], 'ok'))->toBe([true, false])
        ->and($received['results'][1]['message'])->toBe('این قطعه در TR-00001 نیست.')
        ->and($received['data']['received_count'])->toBe(1);

    $this->postJson("/api/v1/transfers/{$transfer['id']}/close", ['bags' => 1])->assertOk()->assertJsonPath('data.status', 'received');
    expect(Piece::query()->find($a['id']))->toMatchArray(['branch_id' => $this->work->plant->id, 'flag' => null])
        ->and(Piece::query()->find($b['id'])->flag->value)->toBe('in_transit');
    expect(scan($this->work->wash, [$a['tag_code']])[0]['warnings'])->toBe([]);

    // The missing piece turns up later and is checked in on the closed transfer.
    $this->postJson("/api/v1/transfers/{$transfer['id']}/receive", ['tags' => [$b['tag_code']]])->assertJsonPath('results.0.ok', true);
    $this->getJson("/api/v1/pieces/{$b['tag_code']}")->assertJsonPath('data.events.*.event', ['transferred_out', 'transferred_in'])->assertJsonPath('data.movement', null);
});

it('trusts a sealed bag when closing with all, and lists transfers by branch', function () {
    $this->actingAs($this->manager);
    [$a, $b] = piecesOf(shirtOrder($this->shop, 2));
    $transfer = $this->postJson('/api/v1/transfers', ['from_branch_id' => $this->shop->branch->id, 'to_branch_id' => $this->work->plant->id, 'note' => 'کیسه آبی'])->json('data');
    $this->postJson("/api/v1/transfers/{$transfer['id']}/send", ['bags' => 1])->assertConflict()->assertJsonPath('code', 'transfer_empty');
    $this->postJson("/api/v1/transfers/{$transfer['id']}/pieces", ['tags' => [$a['tag_code'], $b['tag_code']]]);
    $this->postJson("/api/v1/transfers/{$transfer['id']}/send", ['bags' => 2]);

    $this->getJson('/api/v1/transfers')->assertJsonPath('data.0.received_count', 0)->assertJsonPath('data.0.piece_count', 2);
    $this->postJson("/api/v1/transfers/{$transfer['id']}/close", ['bags' => 2, 'all' => true])->assertJsonPath('data.received_count', 2);
    $this->getJson('/api/v1/transfers')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/transfers?status=received')->assertJsonPath('data.0.bags_received', 2);
    $this->postJson("/api/v1/transfers/{$transfer['id']}/send", ['bags' => 1])->assertConflict()->assertJsonPath('code', 'transfer_wrong_status');
});

it('sends work to a vendor and tracks it until everything is back', function () {
    $this->actingAs($this->manager);
    $vendor = $this->postJson('/api/v1/vendors', ['name' => 'قالیشویی نگین', 'phone' => '۰۲۱۱۲۳۴', 'services' => 'فرش و پرده'])->assertCreated()->json('data');
    expect($vendor['phone'])->toBe('0211234');
    $order = shirtOrder($this->shop, 3);
    [$a, $b, $held] = piecesOf($order);
    $this->postJson("/api/v1/pieces/{$held['id']}/hold", ['reason' => 'تأیید'])->assertOk();

    $this->postJson('/api/v1/shipments', ['vendor_id' => $vendor['id'], 'branch_id' => $this->shop->branch->id, 'tags' => [$a['tag_code'], $held['tag_code']]])
        ->assertConflict()
        ->assertJsonPath('code', 'outsourcing_pieces_refused')
        ->assertJsonPath('message', "{$held['tag_code']}: این قطعه نگه‌داشته است.");
    expect(Piece::query()->find($a['id'])->flag)->toBeNull();

    $shipment = $this->postJson('/api/v1/shipments', ['vendor_id' => $vendor['id'], 'branch_id' => $this->shop->branch->id, 'tags' => [$a['tag_code'], $b['tag_code']], 'expected_at' => now()->addDays(3)->toIso8601String(), 'cost' => 400_000])
        ->assertCreated()
        ->assertJsonPath('data.number', 'OS-00001')
        ->assertJsonPath('data.piece_count', 2)
        ->json('data');
    $this->postJson('/api/v1/assembly/scan', ['tag' => $a['tag_code']])->assertConflict()->assertJsonPath('message', "{$a['tag_code']}: نزد پیمانکار بیرونی است.");
    $this->getJson("/api/v1/pieces/{$a['tag_code']}")->assertJsonPath('data.flag_note', 'قالیشویی نگین · OS-00001')->assertJsonPath('data.movement.kind', 'shipment');

    $this->postJson("/api/v1/shipments/{$shipment['id']}/receive", ['tags' => [$a['tag_code']]])->assertOk()->assertJsonPath('data.status', 'sent')->assertJsonPath('data.returned_count', 1);
    $this->getJson('/api/v1/shipments')->assertJsonCount(1, 'data');
    $this->postJson("/api/v1/shipments/{$shipment['id']}/receive", ['tags' => [$b['tag_code'], $held['tag_code']]])
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('results.1.ok', false);
    expect(Piece::query()->find($b['id']))->toMatchArray(['flag' => null, 'status' => 'in_process']);
    $this->getJson('/api/v1/shipments?status=closed')->assertJsonPath('data.0.cost', 400_000);

    $this->actingAs($this->cashier);
    $this->postJson('/api/v1/vendors', ['name' => 'x'])->assertForbidden();
    $this->getJson('/api/v1/shipments')->assertForbidden();
});

it('opens damage and loss claims and settles them', function () {
    $this->actingAs($this->manager);
    $order = shirtOrder($this->shop, 2);
    [$torn, $missing] = piecesOf($order);

    $damage = $this->postJson('/api/v1/claims', ['piece_id' => $torn['id'], 'kind' => 'damaged', 'description' => 'پارگی آستین هنگام اتو'])
        ->assertCreated()
        ->assertJsonPath('data.number', 'CL-00001')
        ->assertJsonPath('data.piece.tag_code', $torn['tag_code'])
        ->json('data');
    $this->postJson('/api/v1/claims', ['piece_id' => $torn['id'], 'kind' => 'damaged', 'description' => 'x'])->assertConflict()->assertJsonPath('code', 'claim_already_open');

    // A damaged piece still travels, and is still damaged when it arrives.
    $transfer = $this->postJson('/api/v1/transfers', ['from_branch_id' => $this->shop->branch->id, 'to_branch_id' => $this->work->plant->id])->json('data');
    $this->postJson("/api/v1/transfers/{$transfer['id']}/pieces", ['tags' => [$torn['tag_code']]])->assertJsonPath('results.0.ok', true);
    $this->postJson("/api/v1/transfers/{$transfer['id']}/send", ['bags' => 1]);
    $this->postJson("/api/v1/transfers/{$transfer['id']}/close", ['all' => true]);
    expect(Piece::query()->find($torn['id'])->flag->value)->toBe('damaged');

    $this->postJson('/api/v1/claims', ['piece_id' => $missing['id'], 'kind' => 'lost', 'description' => 'در قفسه پیدا نشد'])->assertCreated();
    $this->getJson('/api/v1/claims')->assertJsonCount(2, 'data');

    $this->postJson("/api/v1/claims/{$damage['id']}/resolve", ['resolution' => 'تعمیر رایگان و تخفیف', 'compensation' => 500_000])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.compensation', 500_000);
    $this->postJson("/api/v1/claims/{$damage['id']}/reject", ['resolution' => 'x'])->assertConflict()->assertJsonPath('code', 'claim_closed');

    $lost = $this->getJson('/api/v1/claims')->json('data.0');
    $this->postJson("/api/v1/claims/{$lost['id']}/reject", ['resolution' => 'زیر میز پیدا شد'])->assertOk()->assertJsonPath('data.status', 'rejected');
    $this->getJson("/api/v1/pieces/{$missing['tag_code']}")
        ->assertJsonPath('data.flag', null)
        ->assertJsonPath('data.events.*.event', ['lost', 'found'])
        ->assertJsonPath('data.claims.0.status', 'rejected');

    $this->actingAs($this->cashier);
    $this->getJson('/api/v1/claims?status=resolved')->assertJsonCount(1, 'data');
    $this->postJson('/api/v1/claims', ['piece_id' => $torn['id'], 'kind' => 'lost', 'description' => 'x'])->assertForbidden();
});

it('keeps photos of a piece for those who can see it', function () {
    Storage::fake('local');
    $this->actingAs($this->cashier);
    $piece = piecesOf(shirtOrder($this->shop))[0];

    // A real (1×1) JPEG, so the type check sees an image without needing GD.
    $jpeg = UploadedFile::fake()->createWithContent('stain.jpg', (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));
    $photo = $this->post("/api/v1/pieces/{$piece['id']}/photos", ['photo' => $jpeg], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.uploader_name', $this->cashier->name)
        ->json('data');
    $this->post("/api/v1/pieces/{$piece['id']}/photos", ['photo' => UploadedFile::fake()->create('notes.pdf', 10)], ['Accept' => 'application/json'])->assertJsonValidationErrors('photo');

    $this->getJson("/api/v1/pieces/{$piece['tag_code']}")->assertJsonPath('data.photos.0.url', "/api/v1/media/{$photo['id']}");
    $this->get($photo['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $this->actingAs($this->operator);
    $this->get($photo['url'])->assertNotFound();
    $this->actingAs($this->manager);
    $this->deleteJson($photo['url'])->assertNoContent();
    Storage::disk('local')->assertDirectoryEmpty("pieces/{$this->shop->organization->id}/{$piece['id']}");
});
