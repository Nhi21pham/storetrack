<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['01', 'Hà Nội',            'Hanoi'],
            ['02', 'Cao Bằng',          'Cao Bang'],
            ['04', 'Lai Châu',          'Lai Chau'],
            ['06', 'Tuyên Quang',       'Tuyen Quang'],
            ['08', 'Lào Cai',           'Lao Cai'],
            ['10', 'Điện Biên',         'Dien Bien'],
            ['12', 'Lạng Sơn',          'Lang Son'],
            ['14', 'Sơn La',            'Son La'],
            ['15', 'Quảng Ninh',        'Quang Ninh'],
            ['17', 'Thái Nguyên',       'Thai Nguyen'],
            ['19', 'Phú Thọ',           'Phu Tho'],
            ['20', 'Bắc Ninh',          'Bac Ninh'],
            ['22', 'Hưng Yên',          'Hung Yen'],
            ['24', 'Hải Phòng',         'Hai Phong'],
            ['26', 'Ninh Bình',         'Ninh Binh'],
            ['27', 'Thanh Hóa',         'Thanh Hoa'],
            ['29', 'Nghệ An',           'Nghe An'],
            ['31', 'Hà Tĩnh',           'Ha Tinh'],
            ['33', 'Quảng Trị',         'Quang Tri'],
            ['35', 'Huế',               'Hue'],
            ['37', 'Đà Nẵng',           'Da Nang'],
            ['39', 'Quảng Ngãi',        'Quang Ngai'],
            ['41', 'Gia Lai',           'Gia Lai'],
            ['43', 'Khánh Hòa',         'Khanh Hoa'],
            ['45', 'Lâm Đồng',          'Lam Dong'],
            ['47', 'Đắk Lắk',           'Dak Lak'],
            ['49', 'Hồ Chí Minh',       'Ho Chi Minh'],
            ['51', 'Đồng Nai',          'Dong Nai'],
            ['52', 'Tây Ninh',          'Tay Ninh'],
            ['54', 'Cần Thơ',           'Can Tho'],
            ['56', 'Vĩnh Long',         'Vinh Long'],
            ['58', 'Đồng Tháp',         'Dong Thap'],
            ['60', 'Cà Mau',            'Ca Mau'],
            ['62', 'An Giang',          'An Giang'],
        ];

        $now = Carbon::now();
        $rows = [];

        foreach ($provinces as [$code, $vi, $en]) {
            $rows[] = [
                'code'       => $code,
                'name_vi'    => $vi,
                'name_en'    => $en,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('provinces')->upsert(
            $rows,
            ['code'],
            ['name_vi', 'name_en', 'updated_at']
        );
    }
}
