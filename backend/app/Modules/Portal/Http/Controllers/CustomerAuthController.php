<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Support\Settings;
use App\Modules\Portal\Actions\SendOtp;
use App\Modules\Portal\Actions\VerifyOtp;
use App\Modules\Portal\Http\Requests\OtpRequest;
use App\Modules\Portal\Support\Portal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CustomerAuthController
{
    /**
     * Portal settings
     *
     * What the portal shows before sign-in: the business's name and how
     * amounts are displayed.
     *
     * @response array{data: array{organization_name: string, currency_unit: 'toman'|'rial'}}
     */
    public function settings(Portal $portal, Settings $settings): JsonResponse
    {
        return response()->json(['data' => [
            'organization_name' => Organization::query()->findOrFail($portal->organizationId())->name,
            'currency_unit' => $settings->get('general.currency_unit') === 'rial' ? 'rial' : 'toman',
        ]]);
    }

    /**
     * Text a sign-in code
     *
     * Five digits, valid for five minutes; one a minute and five an hour
     * per number (429 `otp_too_soon` / `otp_too_many`).
     *
     * @response array{data: array{mobile: string, expires_in: int, resend_in: int}}
     */
    public function otp(OtpRequest $request, SendOtp $send, Portal $portal): JsonResponse
    {
        $mobile = (string) $request->validated('mobile');
        $send->handle($portal->organizationId(), $mobile, $request->ip());

        return response()->json(['data' => ['mobile' => $mobile, 'expires_in' => SendOtp::LIFETIME_MINUTES * 60, 'resend_in' => SendOtp::RESEND_SECONDS]]);
    }

    /**
     * Sign in with the code
     *
     * Starts the customer's portal session (a separate sign-in from staff);
     * a customer is created on first sign-in. Fails with 422 `otp_wrong`
     * or `otp_expired`.
     *
     * @response array{data: array{id: int, name: string, mobile: string, needs_name: bool}}
     */
    public function verify(OtpRequest $request, VerifyOtp $verify, Portal $portal): JsonResponse
    {
        $customer = $verify->handle($portal->organizationId(), (string) $request->validated('mobile'), (string) $request->validated('code'));

        Auth::guard('customer')->login($customer, remember: true);
        $request->session()->regenerate();

        return response()->json(['data' => $this->describe($customer)]);
    }

    /**
     * The signed-in customer
     *
     * @response array{data: array{id: int, name: string, mobile: string, needs_name: bool}}
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return response()->json(['data' => $this->describe($customer)]);
    }

    /**
     * Update the customer's name
     *
     * @response array{data: array{id: int, name: string, mobile: string, needs_name: bool}}
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');
        /** @var array{name: string} $input */
        $input = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $customer->update(['name' => trim($input['name'])]);

        return response()->json(['data' => $this->describe($customer)]);
    }

    /**
     * Sign out of the portal
     */
    public function logout(Request $request): Response
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * @return array{id: int, name: string, mobile: string, needs_name: bool}
     */
    private function describe(Customer $customer): array
    {
        return ['id' => $customer->id, 'name' => $customer->name, 'mobile' => $customer->mobile, 'needs_name' => trim($customer->name) === ''];
    }
}
