<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Price;
use App\Modules\Organization\Support\Settings;
use App\Support\Export\XlsxReader;
use App\Support\Export\XlsxWriter;
use Illuminate\Http\UploadedFile;
use Spatie\Activitylog\Models\Activity;

/**
 * @param  list<list<string|int|float|null>>  $rows
 */
function priceUpload(array $headers, array $rows): UploadedFile
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, XlsxWriter::build('لیست قیمت', $headers, $rows));

    return new UploadedFile($path, 'prices.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/** The rows of the price list the API hands out. */
function downloadedPriceList(): array
{
    $response = test()->get('/api/v1/prices/export')->assertOk();
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, (string) $response->getContent());

    return XlsxReader::firstSheet($path);
}

it('downloads the price list with one column per service, in toman', function () {
    $shop = shop();
    $this->actingAs(staff($shop));

    $this->get('/api/v1/prices/export')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="price-list.xlsx"');

    $rows = downloadedPriceList();
    expect($rows[0])->toBe(['شناسه', 'دسته', 'قلم', 'واحد', 'خشکشویی [DC]', 'شست‌وشو [WS]'])
        ->and($rows[1])->toBe([$shop->shirt->id, $shop->shirt->category->name, 'پیراهن', 'عدد', 90_000, null])
        ->and($rows[3])->toBe([$shop->laundry->id, $shop->laundry->category->name, 'کیلویی', 'کیلو', null, 80_000]);
});

it('previews the changes in an edited price list, then applies them', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));
    $rows = downloadedPriceList();
    $headers = array_shift($rows);

    $rows[0][4] = '۹۵٬۰۰۰';     // shirt dry cleaning: 90,000 → 95,000 toman, typed in Persian
    $rows[0][5] = 40000;        // shirt washing: newly offered
    $rows[1][4] = null;         // suit: no longer dry-cleaned
    $file = fn () => priceUpload($headers, $rows);

    $preview = $this->post('/api/v1/prices/import', ['file' => $file()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.applied', false)
        ->assertJsonPath('data.errors', [])
        ->assertJsonCount(3, 'data.changes')
        ->assertJsonPath('data.changes.0', ['item_id' => $shop->shirt->id, 'item' => 'پیراهن', 'service_type_id' => $shop->dc->id, 'service' => 'خشکشویی', 'old' => 900_000, 'new' => 950_000])
        ->assertJsonPath('data.changes.1.new', 400_000)
        ->assertJsonPath('data.changes.2', ['item_id' => $shop->suit->id, 'item' => 'کت‌وشلوار', 'service_type_id' => $shop->dc->id, 'service' => 'خشکشویی', 'old' => 3_200_000, 'new' => null]);
    expect(Price::query()->where('item_id', $shop->shirt->id)->value('price'))->toBe(900_000);

    $this->post('/api/v1/prices/import', ['file' => $file(), 'apply' => '1'], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.applied', true);

    $this->getJson('/api/v1/prices')->assertJsonPath('data', [
        ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'price' => 950_000],
        ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->ws->id, 'price' => 400_000],
        ['item_id' => $shop->laundry->id, 'service_type_id' => $shop->ws->id, 'price' => 800_000],
    ]);
    expect(Activity::query()->where('event', 'prices_updated')->latest('id')->first()?->properties['prices'])->toHaveCount(3);

    // The same file again changes nothing.
    $this->post('/api/v1/prices/import', ['file' => $file()], ['Accept' => 'application/json'])->assertJsonCount(0, 'data.changes');
});

it('reads amounts in rial when the organization shows rials', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));
    app(Settings::class)->put(['general.currency_unit' => 'rial']);

    $rows = downloadedPriceList();
    expect($rows[1][4])->toBe(900_000);

    $this->post('/api/v1/prices/import', ['file' => priceUpload($rows[0], [[$shop->shirt->id, null, null, null, 1_000_000]]), 'apply' => '1'], ['Accept' => 'application/json'])
        ->assertJsonPath('data.applied', true);
    expect(Price::query()->where('item_id', $shop->shirt->id)->value('price'))->toBe(1_000_000);
});

it('reports rows it cannot use and saves nothing while there are errors', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));
    $headers = ['شناسه', 'قلم', 'خشکشویی [DC]', 'اتو', 'شست‌وشو'];

    $this->post('/api/v1/prices/import', ['apply' => '1', 'file' => priceUpload($headers, [
        [$shop->shirt->id, 'پیراهن', 100_000, 5, 1],
        [999_999, 'ناشناس', 1, null, null],
        [$shop->suit->id, 'کت', 'گران', null, null],
        [$shop->shirt->id, 'پیراهن', 1, null, null],
        [null, null, null, null, null],
        [$shop->laundry->id, null, -5, null, 90_000],
    ])], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.applied', false)
        ->assertJsonPath('data.warnings', [['row' => null, 'message' => 'ستون «اتو» با هیچ خدمتی مطابقت نداشت و نادیده گرفته شد.']])
        ->assertJsonPath('data.errors.*.row', [3, 4, 5, 7])
        ->assertJsonPath('data.errors.0.message', 'قلمی با شناسه «999999» وجود ندارد.')
        ->assertJsonPath('data.errors.1.message', 'مبلغ «گران» برای «کت‌وشلوار» در خدمت «خشکشویی» عدد معتبری نیست.')
        ->assertJsonPath('data.errors.2.message', '«پیراهن» پیش‌تر در ردیف 2 آمده است.');

    expect(Price::query()->where('item_id', $shop->shirt->id)->value('price'))->toBe(900_000);
});

it('refuses files that are not price lists and keeps imports to catalog managers', function () {
    $shop = shop();
    $this->actingAs($this->owner($shop->organization));

    $this->post('/api/v1/prices/import', ['file' => UploadedFile::fake()->createWithContent('prices.xlsx', 'not a zip')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'فایل خوانده نشد.']);
    $this->post('/api/v1/prices/import', ['file' => UploadedFile::fake()->create('prices.csv', 1)], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('file');
    $this->post('/api/v1/prices/import', ['file' => priceUpload(['کالا', 'قیمت'], [['پیراهن', 1]])], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.errors.0.message', 'ستون «شناسه» در ردیف اول پیدا نشد. از فایل دانلودشده از همین بخش استفاده کنید.');

    $this->actingAs(staff($shop));
    $this->post('/api/v1/prices/import', ['file' => priceUpload(['شناسه'], [])], ['Accept' => 'application/json'])->assertForbidden();
});
