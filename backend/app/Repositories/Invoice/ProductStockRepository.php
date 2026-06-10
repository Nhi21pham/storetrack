<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\ProductStock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductStockRepository
{
    /** On-hand quantity per product in a store. */
    public function allForStore(int $storeId): Collection
    {
        return ProductStock::where('store_id', $storeId)->get(['product_id', 'quantity']);
    }

    /** Increase on-hand quantity, creating the row if absent. Atomic at the DB level. */
    public function increment(int $storeId, int $productId, float $quantity): void
    {
        DB::table('product_stocks')->insertOrIgnore([
            'store_id'   => $storeId,
            'product_id' => $productId,
            'quantity'   => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_stocks')
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->increment('quantity', $quantity, ['updated_at' => now()]);
    }

    /** Decrease on-hand quantity. Atomic at the DB level. */
    public function decrement(int $storeId, int $productId, float $quantity): void
    {
        DB::table('product_stocks')
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->decrement('quantity', $quantity, ['updated_at' => now()]);
    }
}
