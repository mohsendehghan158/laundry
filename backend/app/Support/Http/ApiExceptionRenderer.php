<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Gives every API error the same shape:
 * { "message": string, "code": string, "errors"?: { field: string[] } }.
 */
final class ApiExceptionRenderer
{
    private const array STATUS_CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'csrf_mismatch',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof ValidationException) {
            // A throttled login is reported as a field error with status 429.
            $code = $e->status === 429 ? 'too_many_requests' : 'validation_failed';

            return $this->respond($e->status, $code, __("api.errors.{$code}"), ['errors' => $e->errors()]);
        }

        if ($e instanceof ApiException) {
            return $this->respond($e->status, $e->errorCode, $e->getMessage());
        }

        if ($e instanceof AuthenticationException) {
            return $this->respond(401, 'unauthenticated', __('api.errors.unauthenticated'));
        }

        if ($e instanceof InvalidQuery) {
            return $this->respond(400, 'invalid_query', $e->getMessage());
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = self::STATUS_CODES[$status] ?? 'http_error';

            return $this->respond(
                $status,
                $code,
                __("api.errors.{$code}"),
                headers: $e->getHeaders(),
            );
        }

        report($e);

        return $this->respond(
            500,
            'server_error',
            config('app.debug') ? $e->getMessage() : __('api.errors.server_error'),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $headers
     */
    private function respond(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'code' => $code, ...$extra], $status, $headers);
    }
}
