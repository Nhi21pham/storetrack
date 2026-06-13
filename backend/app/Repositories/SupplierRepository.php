<?php

namespace App\Repositories;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\Invoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SupplierRepository
{
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function attachStore(Supplier $supplier, int $storeId): void
    {
        $supplier->stores()->attach($storeId);
    }

    /** Link the supplier (by party) to a store it transacted at, idempotently. */
    public function attachStoreByParty(int $partyId, int $storeId): void
    {
        $supplier = Supplier::where('party_id', $partyId)->first();
        $supplier?->stores()->syncWithoutDetaching([$storeId]);
    }

    public function findById(int $id): ?Supplier
    {
        return Supplier::find($id);
    }

    public function findByName(int $businessId, string $name, ?int $excludeId = null): ?Supplier
    {
        $query = Supplier::query()
            ->where('business_id', $businessId)
            ->where('name', $name);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    public function deleteMany(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        return Supplier::whereIn('id', array_values(array_unique($ids)))->delete();
    }

    public function all(int $businessId, ?int $storeId = null): Collection
    {
        return Supplier::query()
            ->with('party')
            ->where('business_id', $businessId)
            ->select('suppliers.*')
            ->addSelect([
                'outstanding'          => $this->outstandingSubquery($storeId),
                'business_outstanding' => $this->outstandingSubquery(null),
            ])
            ->latest()
            ->get();
    }

    /** Open purchase-invoice balance owed to each supplier's party (scoped to a store when given). */
    private function outstandingSubquery(?int $storeId): Builder
    {
        return Invoice::query()
            ->selectRaw('COALESCE(SUM(grand_total - paid_amount), 0)')
            ->whereColumn('party_id', 'suppliers.party_id')
            ->where('type', InvoiceTypeEnum::PURCHASE->value)
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId));
    }

    public function listQuery(int $businessId, ?int $storeId = null, ?string $search = null, ?array $ids = null): Builder
    {
        $query = Supplier::query()->where('business_id', $businessId);

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
