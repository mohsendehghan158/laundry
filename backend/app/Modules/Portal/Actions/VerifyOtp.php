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
        // A wrong try is counted and committed before it is reported; an
        // exception thrown inside the transaction would roll the count back.
        $outcome = DB::transaction(function () use ($organizationId, $mobile, $code): Customer|int {
            $otp = OtpCode::query()
                ->where('mobile', $mobile)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->attempts >= self::MAX_ATTEMPTS) {
                return 0;
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                return self::MAX_ATTEMPTS - $otp->attempts;
            }

            $otp->update(['used_at' => now()]);

            $customer = Customer::query()->where('mobile', $mobile)->first()
                ?? Customer::query()->forceCreate(['organization_id' => $organizationId, 'name' => '', 'mobile' => $mobile]);
            $customer->forceFill(['last_login_at' => now()])->save();

            return $customer;
        });

        if ($outcome instanceof Customer) {
            return $outcome;
        }

        // No code to try, or this was its last try.
        if ($outcome === 0) {
            throw new ApiException('otp_expired', __('api.errors.otp_expired'), 422);
        }

        throw new ApiException('otp_wrong', __('api.errors.otp_wrong', ['left' => $outcome]), 422);
    }
}
