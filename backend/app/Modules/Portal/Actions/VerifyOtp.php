<?php

declare(strict_types=1);

namespace App\Modules\Portal\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Portal\Models\OtpCode;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Checks a sign-in code and returns the customer with that number,
 * creating one on first sign-in. Five wrong tries spoil a code.
 */
final class VerifyOtp
{
    public const int MAX_ATTEMPTS = 5;

    public function handle(int $organizationId, string $mobile, string $code): Customer
    {
        return DB::transaction(function () use ($organizationId, $mobile, $code): Customer {
            $otp = OtpCode::query()
                ->where('mobile', $mobile)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->attempts >= self::MAX_ATTEMPTS) {
                throw new ApiException('otp_expired', __('api.errors.otp_expired'), 422);
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');
                throw new ApiException('otp_wrong', __('api.errors.otp_wrong', ['left' => self::MAX_ATTEMPTS - $otp->attempts]), 422);
            }

            $otp->update(['used_at' => now()]);

            $customer = Customer::query()->where('mobile', $mobile)->first()
                ?? Customer::query()->forceCreate(['organization_id' => $organizationId, 'name' => '', 'mobile' => $mobile]);
            $customer->forceFill(['last_login_at' => now()])->save();

            return $customer;
        });
    }
}
