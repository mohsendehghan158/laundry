<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\DefectKind;
use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Catalog\Models\Color;
use App\Modules\Catalog\Models\DefectType;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Catalog\Models\Upcharge;
use App\Modules\Organization\Tenancy\OrganizationContext;

/**
 * The starting catalog of a new organization: common services, delivery
 * tiers, surcharges, defects and colors. Items and prices differ too much
 * between businesses and are entered by the owner.
 */
final class CreateDefaultCatalog
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(int $organizationId): void
    {
        $this->context->runFor($organizationId, function (): void {
            PriceList::default();

            foreach ([['خشکشویی', 'DC'], ['شست‌وشو', 'WS'], ['فقط اتو', 'PR'], ['لکه‌بری', 'SR']] as $order => [$name, $code]) {
                ServiceType::query()->create(['name' => $name, 'code' => $code, 'sort_order' => $order]);
            }

            foreach ([['عادی', 72, 0, true], ['فوری', 24, 50, false], ['همان روز', 8, 100, false]] as $order => [$name, $hours, $percent, $default]) {
                TurnaroundTier::query()->create([
                    'name' => $name,
                    'duration_hours' => $hours,
                    'surcharge_percent' => $percent,
                    'is_default' => $default,
                    'sort_order' => $order,
                ]);
            }

            foreach ([['ابریشم', UpchargeType::Percent, 30], ['چرم و جیر', UpchargeType::Percent, 50], ['پلیسه', UpchargeType::Fixed, 300_000], ['لکه سنگین', UpchargeType::Fixed, 200_000]] as $order => [$name, $type, $value]) {
                Upcharge::query()->create(['name' => $name, 'type' => $type, 'value' => $value, 'sort_order' => $order]);
            }

            $defects = [
                ['پارگی', DefectKind::Defect], ['دکمه افتاده', DefectKind::Defect], ['رنگ‌رفتگی', DefectKind::Defect],
                ['سوختگی', DefectKind::Defect], ['زیپ خراب', DefectKind::Defect], ['پرزدار', DefectKind::Defect],
                ['چربی', DefectKind::Stain], ['جوهر', DefectKind::Stain], ['خون', DefectKind::Stain],
                ['رنگ', DefectKind::Stain], ['چای و قهوه', DefectKind::Stain], ['عرق', DefectKind::Stain],
            ];
            foreach ($defects as $order => [$name, $kind]) {
                DefectType::query()->create(['name' => $name, 'kind' => $kind, 'sort_order' => $order]);
            }

            $colors = [
                ['سفید', '#ffffff'], ['مشکی', '#000000'], ['طوسی', '#8c8c8c'], ['سرمه‌ای', '#1f2a5a'],
                ['آبی', '#1677ff'], ['قرمز', '#f5222d'], ['سبز', '#52c41a'], ['قهوه‌ای', '#7a4a21'],
                ['کرم', '#f3e5c0'], ['زرد', '#fadb14'], ['صورتی', '#eb2f96'], ['چندرنگ', null],
            ];
            foreach ($colors as $order => [$name, $hex]) {
                Color::query()->create(['name' => $name, 'hex' => $hex, 'sort_order' => $order]);
            }
        });
    }
}
