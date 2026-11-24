<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * A business-rule failure that should reach the client as a stable,
 * machine-readable error code plus a translated message.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }

    /**
     * A conflict with the current state. The message is looked up under
     * "api.errors.{code}".
     *
     * @param  array<string, string|int>  $replace
     */
    public static function conflict(string $code, array $replace = []): self
    {
        return new self($code, __("api.errors.{$code}", $replace), 409);
    }
}
