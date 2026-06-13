<?php

namespace App\Repositories;

use App\Enums\InvoiceTypeEnum;
use App\Models\Customer;
use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function attachStore(Customer $customer, int $storeId): void
    {
        $customer->stores()->attach($storeId);
    }

    public function findById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    public function findByPhone(int $businessId, string $phone, ?int $excludeId = null): ?Customer
    {
        $query = Customer::query()
            ->where('business_id', $businessId)
            ->where('phone', $phone);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    public function deleteMany(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        return Customer::whereIn('id', array_values(array_unique($ids)))->delete();
    }

    public function all(int $businessId, ?int $storeId = null): Collection
    {
        return Customer::query()
            ->with('party')
            ->where('business_id', $businessId)
            ->select('customers.*')
            ->addSelect(['outstanding' => $this->outstandingSubquery($storeId)])
            ->latest()
            ->get();
    }

    /** Open sale-invoice balance owed by each customer's party (scoped to a store when given). */
    private function outstandingSubquery(?int $storeId): Builder
    {
        return Invoice::query()
            ->selectRaw('COALESCE(SUM(grand_total - paid_amount), 0)')
            ->whereColumn('party_id', 'customers.party_id')
            ->where('type', InvoiceTypeEnum::SALE->value)
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId));
    }

    public function listQuery(int $businessId, ?int $storeId = null, ?string $search = null, ?array $ids = null): Builder
    {
        $query = Customer::query()->where('business_id', $businessId);

        if ($storeId !== null) {
            $query->whereHas('stores', fn ($q) => $q->where('stores.id', $storeId));
        }

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if ($search !== null && $search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('tax_code', 'like', $needle)
                    ->orWhere('address', 'like', $needle)
                    ->orWhere('phone', 'like', $needle);
            });
        }

        return $query->orderBy('id');
    }
}
