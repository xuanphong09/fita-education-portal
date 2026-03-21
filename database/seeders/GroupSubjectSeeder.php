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
                'description' => ['vi' => 'Nhóm môn học nền tảng chung cho sinh viên.', 'en' => 'Foundation subjects for all students.'],
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => ['vi' => 'Khối kiến thức cơ sở ngành', 'en' => 'Major Foundation'],
                'description' => ['vi' => 'Nhóm môn tạo nền tảng chuyên môn cho ngành học.', 'en' => 'Foundation subjects for the major.'],
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => ['vi' => 'Khối kiến thức chuyên ngành', 'en' => 'Specialized Subjects'],
                'description' => ['vi' => 'Nhóm môn chuyên sâu theo định hướng đào tạo.', 'en' => 'Advanced subjects for the training orientation.'],
                'sort_order' => 3,
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
