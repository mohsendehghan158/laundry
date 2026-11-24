<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Support\Settings;
use Illuminate\Support\Facades\DB;

final class UpdateSettings
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $values  dotted key => value
     */
    public function handle(array $values): void
    {
        $before = $this->settings->flat();

        $changed = array_filter(
            $values,
            fn (mixed $value, string $key): bool => $before[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed === []) {
            return;
        }

        DB::transaction(function () use ($changed, $before): void {
            $this->settings->put($changed);

            activity('settings')
                ->event('updated')
                ->withChanges([
                    'attributes' => $changed,
                    'old' => array_intersect_key($before, $changed),
                ])
                ->log('updated');
        });
    }
}
