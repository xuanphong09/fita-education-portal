<?php

namespace Database\Seeders;

use App\Models\Major;
use Illuminate\Database\Seeder;

class MajorSeeder extends Seeder
{
    public function run(): void
    {
        $majors = [
            ['code' => '7480201', 'slug' => 'cong-nghe-phan-mem', 'name' => ['vi' => 'Công nghệ phần mềm', 'en' => 'Software Engineering']],
            ['code' => '7480201', 'slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Công nghệ thông tin', 'en' => 'Information Technology']],
            ['code' => '7480201', 'slug' => 'he-thong-thong-tin', 'name' => ['vi' => 'Hệ thống thông tin', 'en' => 'Information Systems']],
            ['code' => '7480201', 'slug' => 'an-toan-thong-tin', 'name' => ['vi' => 'An toàn thông tin', 'en' => 'Cybersecurity']],
            ['code' => '7480102', 'slug' => 'mang-may-tinh', 'name' => ['vi' => 'Mạng máy tính', 'en' => 'Computer Networks']],
            ['code' => '7480102', 'slug' => 'truyen-thong', 'name' => ['vi' => 'Truyền thông', 'en' => 'Communication']],
            ['code' => '7480112', 'slug' => 'khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'name' => ['vi' => 'Khoa học dữ liệu và Trí tuệ nhân tạo', 'en' => 'Data Science and Artificial Intelligence']],
        ];

        foreach ($majors as $major) {
            Major::query()->updateOrCreate(
                ['slug' => $major['slug']],
                ['name' => $major['name'], 'code' => $major['code']]
            );
        }
    }
}

