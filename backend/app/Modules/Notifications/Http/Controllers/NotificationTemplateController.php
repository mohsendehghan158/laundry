<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Enums\NotificationEvent;
use App\Modules\Notifications\Http\Requests\UpdateTemplateRequest;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Support\Templates;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class NotificationTemplateController
{
    /**
     * List message templates
     *
     * One per event, with the organization's wording or the built-in one,
     * and the placeholders that can be used.
     *
     * @response array{data: list<array{event: 'order_placed'|'order_received'|'order_ready'|'order_completed'|'order_cancelled', body: string, default_body: string, is_active: bool, customized: bool}>, placeholders: list<string>}
     */
    public function index(Templates $templates): JsonResponse
    {
        Gate::authorize('notifications.manage');

        return response()->json([
            'data' => array_map(fn (NotificationEvent $event): array => $this->present($event, $templates), NotificationEvent::cases()),
            'placeholders' => NotificationEvent::PLACEHOLDERS,
        ]);
    }

    /**
     * Change a message template
     *
     * @response array{data: array{event: 'order_placed'|'order_received'|'order_ready'|'order_completed'|'order_cancelled', body: string, default_body: string, is_active: bool, customized: bool}}
     */
    public function update(UpdateTemplateRequest $request, NotificationEvent $event, Templates $templates): JsonResponse
    {
        $template = NotificationTemplate::query()->firstOrNew(['event' => $event]);
        $template->fill($request->validated())->save();

        return response()->json(['data' => $this->present($event, $templates)]);
    }

    /**
     * @return array{event: string, body: string, default_body: string, is_active: bool, customized: bool}
     */
    private function present(NotificationEvent $event, Templates $templates): array
    {
        return ['event' => $event->value, ...$templates->for($event), 'default_body' => $event->defaultBody()];
    }
}
