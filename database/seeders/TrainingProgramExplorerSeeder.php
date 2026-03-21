<?php

namespace Database\Seeders;

use App\Models\GroupSubject;
use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramSemester;
use App\Models\Subject;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;

class TrainingProgramExplorerSeeder extends Seeder
{
    public function run(): void
    {
        $groups = GroupSubject::query()->get()->keyBy('sort_order');

        $subjectSeed = [
            ['code' => 'IT305', 'name' => ['vi' => 'Lập trình hướng đối tượng', 'en' => 'Object-Oriented Programming'], 'credits' => 3, 'theory' => 2, 'practice' => 1, 'group' => 2],
            ['code' => 'IT306', 'name' => ['vi' => 'Hệ quản trị cơ sở dữ liệu', 'en' => 'Database Management Systems'], 'credits' => 3, 'theory' => 2, 'practice' => 1, 'group' => 2],
            ['code' => 'IT401', 'name' => ['vi' => 'Phân tích dữ liệu', 'en' => 'Data Analytics'], 'credits' => 3, 'theory' => 2, 'practice' => 1, 'group' => 3],
            ['code' => 'IT402', 'name' => ['vi' => 'Đồ án chuyên ngành', 'en' => 'Capstone Project'], 'credits' => 4, 'theory' => 1, 'practice' => 3, 'group' => 3],
            ['code' => 'DS101', 'name' => ['vi' => 'Toán cho khoa học dữ liệu', 'en' => 'Mathematics for Data Science'], 'credits' => 3, 'theory' => 3, 'practice' => 0, 'group' => 1],
            ['code' => 'DS201', 'name' => ['vi' => 'Học máy cơ bản', 'en' => 'Introduction to Machine Learning'], 'credits' => 3, 'theory' => 2, 'practice' => 1, 'group' => 3],
        ];

        foreach ($subjectSeed as $item) {
            Subject::query()->updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'credits' => $item['credits'],
                    'credits_theory' => $item['theory'],
                    'credits_practice' => $item['practice'],
                    'group_subject_id' => $groups->get($item['group'])?->id,
                    'is_active' => true,
                ]
            );
        }

        $subjects = Subject::query()->get()->keyBy('code');

        $programConfigs = [
            [
                'major_slug' => 'cong-nghe-phan-mem',
                'intake' => 'K67',
                'version' => 'K67 - 2022',
                'start_year' => 2022,
                'end_year' => 2026,
                'semesters' => [
                    1 => [['IT101', 'required'], ['IT102', 'required']],
                    2 => [['IT201', 'required'], ['IT202', 'required'], ['IT305', 'required']],
                    3 => [['IT301', 'required'], ['IT302', 'elective'], ['IT306', 'required']],
                    4 => [['IT303', 'required'], ['IT304', 'required'], ['IT402', 'elective']],
                ],
                'prerequisites' => [
                    'IT201' => ['IT102'],
                    'IT202' => ['IT102'],
                    'IT301' => ['IT201', 'IT202'],
                    'IT303' => ['IT201'],
                    'IT304' => ['IT301'],
                    'IT402' => ['IT303', 'IT304'],
                ],
            ],
            [
                'major_slug' => 'he-thong-thong-tin',
                'intake' => 'K68',
                'version' => 'K68 - 2023',
                'start_year' => 2023,
                'end_year' => 2027,
                'semesters' => [
                    1 => [['IT101', 'required'], ['IT102', 'required']],
                    2 => [['IT202', 'required'], ['IT305', 'required'], ['DS101', 'required']],
                    3 => [['IT306', 'required'], ['IT401', 'required'], ['IT302', 'elective']],
                    4 => [['IT303', 'required'], ['IT402', 'required']],
                ],
                'prerequisites' => [
                    'IT202' => ['IT102'],
                    'IT306' => ['IT202'],
                    'IT401' => ['IT202'],
                    'IT402' => ['IT401', 'IT303'],
                ],
            ],
            [
                'major_slug' => 'khoa-hoc-du-lieu-va-tri-tue-nhan-tao',
                'intake' => 'K69',
                'version' => 'K69 - 2024',
                'start_year' => 2024,
                'end_year' => 2028,
                'semesters' => [
                    1 => [['IT101', 'required'], ['IT102', 'required'], ['DS101', 'required']],
                    2 => [['IT201', 'required'], ['IT202', 'required']],
                    3 => [['DS201', 'required'], ['IT401', 'required'], ['IT306', 'elective']],
                    4 => [['IT402', 'required']],
                ],
                'prerequisites' => [
                    'IT201' => ['IT102'],
                    'IT202' => ['IT102'],
                    'DS201' => ['IT201', 'DS101'],
                    'IT401' => ['IT202'],
                    'IT402' => ['DS201'],
                ],
            ],
        ];

        foreach ($programConfigs as $config) {
            $major = Major::query()->where('slug', $config['major_slug'])->first();
            $intake = Intake::query()->where('name', $config['intake'])->first();

            if (!$major || !$intake) {
                continue;
            }

            $majorNameVi = $major->getTranslation('name', 'vi', false)
                ?: $major->getTranslation('name', 'en', false)
                ?: $major->slug;
            $majorNameEn = $major->getTranslation('name', 'en', false)
                ?: $major->getTranslation('name', 'vi', false)
                ?: $major->slug;

            $program = TrainingProgram::query()->updateOrCreate(
                [
                    'major_id' => $major->id,
                    'intake_id' => $intake->id,
                    'version' => $config['version'],
                ],
                [
                    'name' => [
                        'vi' => 'Chương trình đào tạo ' . $majorNameVi . ' - ' . $intake->name,
                        'en' => 'Training Program ' . $majorNameEn . ' - ' . $intake->name,
                    ],
                    'school_year_start' => $config['start_year'],
                    'school_year_end' => $config['end_year'],
                    'type' => ['vi' => 'Chính quy', 'en' => 'Full-time'],
                    'level' => ['vi' => 'Đại học', 'en' => 'Undergraduate'],
                    'language' => ['vi' => 'Tiếng Việt', 'en' => 'Vietnamese'],
                    'duration_time' => 4,
                    'status' => 'published',
                    'published_at' => now(),
                    'notes' => 'Du lieu demo cho trang client dao tao.',
                ]
            );

            $programSubjectCodes = collect();

            foreach ($config['semesters'] as $semesterNo => $semesterSubjects) {
                $semester = ProgramSemester::query()->updateOrCreate(
                    [
                        'training_program_id' => $program->id,
                        'semester_no' => $semesterNo,
                    ],
                    ['total_credits' => 0]
                );

                $syncData = [];
                $order = 1;

                foreach ($semesterSubjects as $definition) {
                    [$code, $type] = $definition;
                    $subject = $subjects->get($code);

                    if (!$subject) {
                        continue;
                    }

                    $programSubjectCodes->push($code);

                    $syncData[$subject->id] = [
                        'type' => $type,
                        'notes' => null,
                        'order' => $order++,
                    ];
                }

                $semester->subjects()->sync($syncData);

                $semester->update([
                    'total_credits' => (int) $semester->subjects()->sum('subjects.credits'),
                ]);
            }

            $programSubjectCodes = $programSubjectCodes->unique()->values();

            foreach ($programSubjectCodes as $subjectCode) {
                $subjectId = $subjects->get($subjectCode)?->id;

                if (!$subjectId) {
                    continue;
                }

                $requiredIds = collect($config['prerequisites'][$subjectCode] ?? [])
                    ->map(fn (string $requiredCode) => $subjects->get($requiredCode)?->id)
                    ->filter()
                    ->values()
                    ->all();

                $program->syncSubjectPrerequisites($subjectId, $requiredIds);
            }

            $program->update([
                'total_credits' => (int) ProgramSemester::query()
                    ->where('training_program_id', $program->id)
                    ->sum('total_credits'),
            ]);
        }
    }
}

