<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Support\TextNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /** Common Vietnamese units seeded into every business. */
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

    public static function seedDefaultsForBusiness(int $businessId): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach (self::defaultUnits() as $name) {
            $rows[] = [
                'business_id'     => $businessId,
                'name'            => $name,
                'name_normalized' => TextNormalizer::normalize($name),
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('units')->upsert(
            $rows,
            ['business_id', 'name_normalized'],
            ['name', 'updated_at']
        );
    }

    /** Backfill: seed defaults for every existing business. Idempotent. */
    public function run(): void
    {
        Business::query()->orderBy('id')->each(function (Business $business) {
            self::seedDefaultsForBusiness((int) $business->id);
        });
    }
}
