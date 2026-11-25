<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use RuntimeException;

/**
 * Sends a text message. Each provider (Kavenegar, SMS.ir, a log for
 * development …) implements this; the one in use is chosen by
 * SMS_DRIVER, so switching providers touches no other code.
 */
interface SmsGateway
{
    /** Short name stored with each message, e.g. "kavenegar". */
    public function name(): string;

    /**
     * @param  string  $to  mobile number, e.g. 09121234567
     * @return string the provider's id for the message
     *
     * @throws RuntimeException when the provider refuses or cannot be reached
     */
    public function send(string $to, string $text): string;
}
