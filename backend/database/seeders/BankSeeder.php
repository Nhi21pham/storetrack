<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Support\TextNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    /** Common Vietnamese banks seeded into every business. */
    public static function defaultBanks(): array
    {
        return [
            ['Vietcombank',  'Ngân hàng TMCP Ngoại thương Việt Nam',          'Joint Stock Commercial Bank for Foreign Trade of Vietnam'],
            ['VietinBank',   'Ngân hàng TMCP Công thương Việt Nam',           'Vietnam Joint Stock Commercial Bank for Industry and Trade'],
            ['BIDV',         'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam',  'Joint Stock Commercial Bank for Investment and Development of Vietnam'],
            ['Agribank',     'Ngân hàng Nông nghiệp và Phát triển Nông thôn Việt Nam', 'Vietnam Bank for Agriculture and Rural Development'],
            ['Techcombank',  'Ngân hàng TMCP Kỹ thương Việt Nam',             'Vietnam Technological and Commercial Joint Stock Bank'],
            ['MB',           'Ngân hàng TMCP Quân đội',                       'Military Commercial Joint Stock Bank'],
            ['ACB',          'Ngân hàng TMCP Á Châu',                         'Asia Commercial Joint Stock Bank'],
            ['VPBank',       'Ngân hàng TMCP Việt Nam Thịnh Vượng',           'Vietnam Prosperity Joint Stock Commercial Bank'],
            ['TPBank',       'Ngân hàng TMCP Tiên Phong',                     'Tien Phong Commercial Joint Stock Bank'],
            ['Sacombank',    'Ngân hàng TMCP Sài Gòn Thương Tín',             'Saigon Thuong Tin Commercial Joint Stock Bank'],
            ['HDBank',       'Ngân hàng TMCP Phát triển Thành phố Hồ Chí Minh', 'Ho Chi Minh City Development Joint Stock Commercial Bank'],
            ['SHB',          'Ngân hàng TMCP Sài Gòn - Hà Nội',               'Saigon - Hanoi Commercial Joint Stock Bank'],
            ['VIB',          'Ngân hàng TMCP Quốc tế Việt Nam',               'Vietnam International Commercial Joint Stock Bank'],
            ['SeABank',      'Ngân hàng TMCP Đông Nam Á',                     'Southeast Asia Commercial Joint Stock Bank'],
            ['Eximbank',     'Ngân hàng TMCP Xuất Nhập khẩu Việt Nam',        'Vietnam Export Import Commercial Joint Stock Bank'],
            ['OCB',          'Ngân hàng TMCP Phương Đông',                    'Orient Commercial Joint Stock Bank'],
            ['MSB',          'Ngân hàng TMCP Hàng Hải Việt Nam',              'Vietnam Maritime Commercial Joint Stock Bank'],
            ['LPBank',       'Ngân hàng TMCP Lộc Phát Việt Nam',              'Loc Phat Vietnam Commercial Joint Stock Bank'],
            ['Nam A Bank',   'Ngân hàng TMCP Nam Á',                          'Nam A Commercial Joint Stock Bank'],
            ['ABBank',       'Ngân hàng TMCP An Bình',                        'An Binh Commercial Joint Stock Bank'],
        ];
    }


    public static function seedDefaultsForBusiness(int $businessId): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach (self::defaultBanks() as [$short, $vi, $en]) {
            $rows[] = [
                'business_id'             => $businessId,
                'short_name'              => $short,
                'full_name_vi'            => $vi,
                'full_name_en'            => $en,
                'short_name_normalized'   => TextNormalizer::normalize($short),
                'full_name_vi_normalized' => TextNormalizer::normalize($vi),
                'full_name_en_normalized' => TextNormalizer::normalize($en),
                'is_active'               => true,
                'created_at'              => $now,
                'updated_at'              => $now,
            ];
        }

        DB::table('banks')->upsert(
            $rows,
            ['business_id', 'short_name_normalized'],
            ['full_name_vi', 'full_name_en', 'full_name_vi_normalized', 'full_name_en_normalized', 'updated_at']
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
