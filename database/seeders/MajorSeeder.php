<?php

namespace Database\Seeders;

use App\Models\Major;
use App\Models\ProgramMajor;
use Illuminate\Database\Seeder;

class MajorSeeder extends Seeder
{
    public function run(): void
    {
        $programMajors = [
            ['code' => '74802', 'slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Công nghệ thông tin', 'en' => 'Information Technology']],
            ['code' => '74801', 'slug' => 'ky-thuat-may-tinh-va-truyen-thong', 'name' => ['vi' => 'Kỹ thuật máy tính và truyền thông', 'en' => 'Computer Engineering and Communication']],
        ];

        foreach ($programMajors as $index => $programMajor) {
            ProgramMajor::query()->updateOrCreate(
                ['slug' => $programMajor['slug']],
                [
                    'name' => $programMajor['name'],
                    'code' => $programMajor['code'],
                    'order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $programMajorIds = ProgramMajor::query()->pluck('id', 'slug');

        $majors = [
            ['code' => '7480201', 'slug' => 'cong-nghe-phan-mem', 'program_major_slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Công nghệ phần mềm', 'en' => 'Software Engineering']],
            ['code' => '7480201', 'slug' => 'cong-nghe-thong-tin', 'program_major_slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Công nghệ thông tin', 'en' => 'Information Technology']],
            ['code' => '7480201', 'slug' => 'he-thong-thong-tin', 'program_major_slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Hệ thống thông tin', 'en' => 'Information Systems']],
            ['code' => '7480201', 'slug' => 'an-toan-thong-tin', 'program_major_slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'An toàn thông tin', 'en' => 'Cybersecurity']],
            ['code' => '7480102', 'slug' => 'mang-may-tinh', 'program_major_slug' => 'ky-thuat-may-tinh-va-truyen-thong', 'name' => ['vi' => 'Mạng máy tính', 'en' => 'Computer Networks']],
            ['code' => '7480102', 'slug' => 'truyen-thong', 'program_major_slug' => 'ky-thuat-may-tinh-va-truyen-thong', 'name' => ['vi' => 'Truyền thông', 'en' => 'Communication']],
            ['code' => '7480112', 'slug' => 'khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'program_major_slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Khoa học dữ liệu và Trí tuệ nhân tạo', 'en' => 'Data Science and Artificial Intelligence']],
        ];

        foreach ($majors as $major) {
            Major::query()->updateOrCreate(
                ['slug' => $major['slug']],
                [
                    'name' => $major['name'],
                    'code' => $major['code'],
                    'program_major_id' => $programMajorIds[$major['program_major_slug']] ?? null,
                ]
            );
        }
    }
}

