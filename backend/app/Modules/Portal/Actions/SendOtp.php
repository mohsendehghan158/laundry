<?php

declare(strict_types=1);

namespace App\Modules\Portal\Actions;

use App\Modules\Notifications\Contracts\SmsGateway;
use App\Modules\Organization\Models\Organization;
use App\Modules\Portal\Models\OtpCode;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Texts a five-digit sign-in code, valid for five minutes. At most one
 * code a minute and five an hour per number; earlier codes stop working.
 */
final class SendOtp
{
    public const int LIFETIME_MINUTES = 5;

    public const int RESEND_SECONDS = 60;

    public function __construct(private readonly SmsGateway $sms) {}

    public function handle(int $organizationId, string $mobile, ?string $ip): OtpCode
    {
        $recent = OtpCode::query()->where('mobile', $mobile)->where('created_at', '>', now()->subHour());

        if ((clone $recent)->where('created_at', '>', now()->subSeconds(self::RESEND_SECONDS))->exists()) {
            throw new ApiException('otp_too_soon', __('api.errors.otp_too_soon', ['seconds' => self::RESEND_SECONDS]), 429);
        }
        if ($recent->count() >= 5) {
            throw new ApiException('otp_too_many', __('api.errors.otp_too_many'), 429);
        }

        $code = $this->code();
        OtpCode::query()->where('mobile', $mobile)->whereNull('used_at')->update(['expires_at' => now()]);
        $otp = OtpCode::query()->create([
            'organization_id' => $organizationId,
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
            'ip' => $ip,
        ]);

        $organization = Organization::query()->findOrFail($organizationId)->name;
        try {
            $this->sms->send($mobile, __('portal.otp_message', ['code' => $code, 'organization' => $organization]));
        } catch (Throwable $exception) {
            report($exception);
            throw new ApiException('otp_not_sent', __('api.errors.otp_not_sent'), 503);
        }

        return $otp;
    }

    private function code(): string
    {
        // A fixed code for automated tests and local development only.
        $fixed = config('services.portal.otp_fixed_code');
        if (is_string($fixed) && $fixed !== '' && ! app()->isProduction()) {
            return $fixed;
        }

        return str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
}
