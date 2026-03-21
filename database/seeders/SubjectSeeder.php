<?php

namespace Database\Seeders;

use App\Models\GroupSubject;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $groups = GroupSubject::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('sort_order');

        if ($groups->isEmpty()) {
            return;
        }

        $subjects = [
            [
                'code' => 'IT101',
                'name' => ['vi' => 'Nhập môn Công nghệ thông tin', 'en' => 'Introduction to Information Technology'],
                'credits' => 3,
                'credits_theory' => 2,
                'credits_practice' => 1,
                'group_sort_order' => 1,
            ],
            [
                'code' => 'IT102',
                'name' => ['vi' => 'Lập trình cơ bản', 'en' => 'Programming Fundamentals'],
                'credits' => 3,
                'credits_theory' => 1,
                'credits_practice' => 2,
                'group_sort_order' => 2,
            ],
            [
                'code' => 'IT201',
                'name' => ['vi' => 'Cấu trúc dữ liệu và giải thuật', 'en' => 'Data Structures and Algorithms'],
                'credits' => 4,
                'credits_theory' => 3,
                'credits_practice' => 1,
                'group_sort_order' => 2,
            ],
            [
                'code' => 'IT202',
                'name' => ['vi' => 'Cơ sở dữ liệu', 'en' => 'Database Systems'],
                'credits' => 3,
                'credits_theory' => 2,
                'credits_practice' => 1,
                'group_sort_order' => 2,
            ],
            [
                'code' => 'IT301',
                'name' => ['vi' => 'Công nghệ Web', 'en' => 'Web Technology'],
                'credits' => 3,
                'credits_theory' => 1,
                'credits_practice' => 2,
                'group_sort_order' => 3,
            ],
            [
                'code' => 'IT302',
                'name' => ['vi' => 'Kiểm thử phần mềm', 'en' => 'Software Testing'],
                'credits' => 3,
                'credits_theory' => 2,
                'credits_practice' => 1,
                'group_sort_order' => 3,
            ],
            [
                'code' => 'IT303',
                'name' => ['vi' => 'Phân tích và thiết kế hệ thống', 'en' => 'Systems Analysis and Design'],
                'credits' => 3,
                'credits_theory' => 2,
                'credits_practice' => 1,
                'group_sort_order' => 3,
            ],
            [
                'code' => 'IT304',
                'name' => ['vi' => 'Phát triển ứng dụng Laravel', 'en' => 'Laravel Application Development'],
                'credits' => 3,
                'credits_theory' => 1,
                'credits_practice' => 2,
                'group_sort_order' => 3,
            ],
        ];

        foreach ($subjects as $subject) {
            $group = $groups->get($subject['group_sort_order']);

            Subject::query()->updateOrCreate(
                ['code' => $subject['code']],
                [
                    'name' => $subject['name'],
                    'credits' => $subject['credits'],
                    'credits_theory' => $subject['credits_theory'],
                    'credits_practice' => $subject['credits_practice'],
                    'group_subject_id' => $group?->id,
                    'is_active' => true,
                ]
            );
        }

        // Prerequisites are seeded per training program in TrainingProgramSeeder
        // because subject_prerequisites now requires training_program_id.
    }
}
