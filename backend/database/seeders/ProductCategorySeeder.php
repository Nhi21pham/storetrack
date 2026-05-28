<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Support\TextNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    /** [code, vi-canonical name] pairs seeded as system rows. */
    public static function defaultCategories(): array
    {
        return [
            ['SV', 'Dịch vụ'],
            ['GD', 'Hàng hóa'],
            ['MF', 'Sản xuất'],
            ['TR', 'Vận tải'],
            ['OT', 'Khác'],
        ];
    }

    public static function seedDefaultsForStore(int $storeId): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach (self::defaultCategories() as [$code, $name]) {
            $rows[] = [
                'store_id'        => $storeId,
                'code'            => $code,
                'name'            => $name,
                'name_normalized' => TextNormalizer::normalize($name),
                'description'     => null,
                'is_active'       => true,
                'is_system'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('product_categories')->upsert(
            $rows,
            ['store_id', 'code'],
            ['name', 'name_normalized', 'is_system', 'updated_at']
        );
    }

    public function run(): void
    {
        Store::query()->orderBy('id')->each(function (Store $store) {
            self::seedDefaultsForStore((int) $store->id);
        });
    }
}
