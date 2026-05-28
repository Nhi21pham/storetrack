<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Support\TextNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /** Common Vietnamese units seeded into every store. */
    public static function defaultUnits(): array
    {
        return [
            'Cái',
            'Chiếc',
            'Cây',
            'Bộ',
            'Đôi',
            'Hộp',
            'Thùng',
            'Gói',
            'Túi',
            'Chai',
            'Lon',
            'Lốc',
            'Vỉ',
            'Cuộn',
            'Tờ',
            'Quyển',
            'Kg',
            'Gam',
            'Tấn',
            'Lít',
            'Ml',
            'Mét',
            'Cm',
            'M2',
            'M3',
        ];
    }

    public static function seedDefaultsForStore(int $storeId): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach (self::defaultUnits() as $name) {
            $rows[] = [
                'store_id'        => $storeId,
                'name'            => $name,
                'name_normalized' => TextNormalizer::normalize($name),
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('units')->upsert(
            $rows,
            ['store_id', 'name_normalized'],
            ['name', 'updated_at']
        );
    }

    /** Backfill: seed defaults for every existing store. Idempotent. */
    public function run(): void
    {
        Store::query()->orderBy('id')->each(function (Store $store) {
            self::seedDefaultsForStore((int) $store->id);
        });
    }
}
