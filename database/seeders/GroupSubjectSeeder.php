<?php

namespace Database\Seeders;

use App\Models\GroupSubject;
use Illuminate\Database\Seeder;

class GroupSubjectSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => ['vi' => 'Khối kiến thức đại cương', 'en' => 'General Education'],
                'description' => ['vi' => 'Lý luận chính trị, Toán, Khoa học cơ bản và Kỹ năng bổ trợ.', 'en' => 'Political theory, Math, Basic Sciences and Soft skills.'],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => ['vi' => 'Khối kiến thức cơ sở ngành', 'en' => 'Major Foundation'],
                'description' => ['vi' => 'Các môn học nền tảng bắt buộc của ngành Công nghệ thông tin.', 'en' => 'Core foundational subjects for IT.'],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => ['vi' => 'Khối kiến thức chuyên ngành', 'en' => 'Specialized Subjects'],
                'description' => ['vi' => 'Các môn học chuyên sâu và các hướng tự chọn.', 'en' => 'Advanced and elective subjects.'],
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => ['vi' => 'Thực tập và Tốt nghiệp', 'en' => 'Internship and Graduation'],
                'description' => ['vi' => 'Học phần thực hành cuối khóa và khóa luận.', 'en' => 'Final internship and graduation thesis.'],
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($groups as $group) {
            GroupSubject::query()->updateOrCreate([
                'sort_order' => $group['sort_order'],
            ], $group);
        }
    }
}
