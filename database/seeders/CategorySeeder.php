<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => ['vi' => 'Tin tức', 'en' => 'News'],
                'slug' => 'tin-tuc',
            ],
            [
                'name' => ['vi' => 'Thông báo', 'en' => 'Notifications'],
                'slug' => 'thong-bao',
            ],
            [
                'name' => ['vi' => 'Sự kiện', 'en' => 'Events'],
                'slug' => 'su-kien',
            ],
            [
                'name' => ['vi'=>'Sinh viên', 'en'=>'Students'],
                'slug' => 'sinh-vien',
            ],
            [
                'name' =>['vi'=>'Tuyển sinh','en'=>'Admissions'],
                'slug' => 'tuyen-sinh',
            ]
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']]
            );
        }
    }
}

