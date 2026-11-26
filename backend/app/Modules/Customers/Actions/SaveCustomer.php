<?php

declare(strict_types=1);

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class SaveCustomer
{
    /**
     * Creates or updates a customer. When "addresses" is given it becomes
     * the full list: listed ids are updated, new entries added, the rest
     * removed. The first address is the default unless one is marked.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, ?Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($actor, $customer, $data): Customer {
            if ($customer === null) {
                $customer = new Customer;
                $customer->created_by = $actor->id;
            }

            $customer->fill(Arr::except($data, 'addresses'))->save();

            if (array_key_exists('addresses', $data)) {
                /** @var list<array<string, mixed>> $addresses */
                $addresses = $data['addresses'];
                $this->syncAddresses($customer, $addresses);
            }

            return $customer->load('addresses');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     */
    private function syncAddresses(Customer $customer, array $addresses): void
    {
        $defaultIndex = array_find_key($addresses, fn (array $address): bool => (bool) ($address['is_default'] ?? false)) ?? 0;
        $keep = [];

        foreach ($addresses as $index => $address) {
            $attributes = [...Arr::only($address, ['title', 'address', 'latitude', 'longitude']), 'is_default' => $index === $defaultIndex];
            $id = $address['id'] ?? null;

            if ($id === null) {
                $model = $customer->addresses()->create($attributes);
            } else {
                $model = $customer->addresses()->findOrFail((int) $id);
                $model->update($attributes);
            }

            $keep[] = $model->id;
        }

        $customer->addresses()->whereNotIn('id', $keep)->delete();
    }
}
