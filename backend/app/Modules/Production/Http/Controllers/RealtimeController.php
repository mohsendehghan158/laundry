<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use Illuminate\Http\JsonResponse;

class RealtimeController
{
    /**
     * Live updates settings
     *
     * Where the browser connects for live board updates (Reverb, Pusher
     * protocol). `enabled` is false when no socket server is configured;
     * screens then refresh on a timer.
     *
     * @response array{data: array{enabled: bool, key: string|null, host: string|null, port: int, scheme: 'http'|'https'}}
     */
    public function __invoke(): JsonResponse
    {
        $key = config('broadcasting.connections.reverb.key');
        $enabled = config('broadcasting.default') === 'reverb' && is_string($key) && $key !== '';

        return response()->json(['data' => [
            'enabled' => $enabled,
            'key' => $enabled ? $key : null,
            'host' => $enabled ? (config('services.realtime.host') ?: null) : null,
            'port' => (int) config('services.realtime.port', 8080),
            'scheme' => config('services.realtime.scheme') === 'https' ? 'https' : 'http',
        ]]);
    }
}
