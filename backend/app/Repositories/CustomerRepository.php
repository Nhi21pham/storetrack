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

    /** Link the customer (by party) to a store it transacted at, idempotently. */
    public function attachStoreByParty(int $partyId, int $storeId): void
    {
        $customer = Customer::where('party_id', $partyId)->first();
        $customer?->stores()->syncWithoutDetaching([$storeId]);
    }

    public function findById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    /**
     * Every customer in the business as {party_id, name, phone}, for resolving
     * the owner of an imported bank account. Names aren't unique (customers are
     * keyed by phone), so the importer resolves by phone when given and falls
     * back to a unique name match.
     *
     * @return list<array{party_id: int, name: string, phone: string}>
     */
    public function listForImport(int $businessId): array
    {
        return Customer::query()
            ->where('business_id', $businessId)
            ->get(['party_id', 'name', 'phone'])
            ->map(fn (Customer $customer) => [
                'party_id' => (int) $customer->party_id,
                'name'     => (string) $customer->name,
                'phone'    => (string) $customer->phone,
            ])
            ->all();
    }

    /**
     * Every customer in the business as {id, name, phone, email, tax_code}, for
     * resolving a row's identity during import. The importer matches a row's
     * phone (the unique identity) against these, and treats email / tax code as
     * additional unique fields — a collision with a *different* customer is a
     * fixable conflict, a match on the same customer is a skip.
     *
     * @return list<array{id: int, name: string, phone: string, email: string, tax_code: string}>
     */
    public function contactsForImport(int $businessId): array
    {
        return Customer::query()
            ->where('business_id', $businessId)
            ->get(['id', 'name', 'phone', 'email', 'tax_code'])
            ->map(fn (Customer $customer) => [
                'id'       => (int) $customer->id,
                'name'     => (string) $customer->name,
                'phone'    => (string) $customer->phone,
                'email'    => (string) $customer->email,
                'tax_code' => (string) $customer->tax_code,
            ])
            ->all();
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
            ->addSelect([
                'outstanding'          => $this->outstandingSubquery($storeId),
                'business_outstanding' => $this->outstandingSubquery(null),
            ])
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
