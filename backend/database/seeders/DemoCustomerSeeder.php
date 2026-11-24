<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Tenancy\OrganizationContext;

final class DemoCustomerSeeder
{
    private const array CUSTOMERS = [
        ['محمد حسینی', '09121112233', 'تهران، ونک، خیابان ملاصدرا، پلاک ۱۲', null],
        ['فاطمه رحیمی', '09122223344', 'تهران، سعادت‌آباد، خیابان سرو، پلاک ۸', 'پیراهن‌ها را تاشده تحویل دهید.'],
        ['امیر جعفری', '09353334455', null, null],
        ['زینب کاظمی', '09194445566', 'تهران، پونک، بلوار عدل، پلاک ۴۰', null],
        ['شرکت آرمان', '09125556677', 'تهران، میرداماد، برج آرمان، طبقه ۵', 'مشتری سازمانی؛ فاکتور رسمی می‌خواهد.'],
    ];

    public function __construct(private readonly OrganizationContext $context) {}

    public function run(int $organizationId): void
    {
        $this->context->runFor($organizationId, function (): void {
            foreach (self::CUSTOMERS as [$name, $mobile, $address, $alert]) {
                $customer = Customer::query()->create(['name' => $name, 'mobile' => $mobile, 'alert' => $alert]);

                if ($address !== null) {
                    $customer->addresses()->create(['title' => 'خانه', 'address' => $address, 'is_default' => true]);
                }
            }
        });
    }
}
