<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Support\TextNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaxSeeder extends Seeder
{
    /**
     * Common Vietnamese taxes seeded into every store as system taxes.
     * These are tax TYPES only — no rate (the rate is entered per invoice line).
     * [name, description] pairs.
     */
    public static function defaultTaxes(): array
    {
        return [
            ['Thuế GTGT', 'Thuế giá trị gia tăng'],
            ['Thuế TTĐB', 'Thuế tiêu thụ đặc biệt'],
            ['Thuế BVMT', 'Thuế bảo vệ môi trường'],
        ];
    }

    public static function seedDefaultsForStore(int $storeId): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach (self::defaultTaxes() as [$name, $description]) {
            $rows[] = [
                'store_id'        => $storeId,
                'name'            => $name,
                'name_normalized' => TextNormalizer::normalize($name),
                'description'     => $description,
                'is_active'       => true,
                'is_system'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        // is_active intentionally NOT in the update list — don't reactivate a
        // system tax a store has deactivated when re-seeding.
        DB::table('taxes')->upsert(
            $rows,
            ['store_id', 'name_normalized'],
            ['description', 'is_system', 'updated_at']
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
