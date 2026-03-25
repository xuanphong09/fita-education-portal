<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $partners = [
            [
                'name' => 'Khoa Công nghệ Thông tin',
                'logo' => '/assets/images/LogoKhoaCNTT.png',
                'url' => null,
                'order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'FITA',
                'logo' => '/assets/images/FITA.png',
                'url' => null,
                'order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Hệ thống',
                'logo' => '/assets/images/logoST.jpg',
                'url' => null,
                'order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Học viện Nông nghiệp Việt Nam',
                'logo' => '/assets/images/Logo Học viện.png',
                'url' => 'https://vnua.edu.vn',
                'order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($partners as $partner) {
            Partner::firstOrCreate(
                ['name' => $partner['name']],
                $partner
            );
        }
    }
}
