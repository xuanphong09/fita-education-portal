<?php

namespace Database\Seeders;

use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramSemester;
use App\Models\Subject;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;

class TrainingProgramSeeder extends Seeder
{
    public function run(): void
    {
        $major = Major::query()->first();
        $intake = Intake::query()->where('name', 'K68')->first() ?? Intake::query()->first();

        if (!$intake) {
            return;
        }

        $subjectModels = Subject::query()
            ->whereIn('code', ['IT101', 'IT102', 'IT201', 'IT202', 'IT301', 'IT302', 'IT303', 'IT304'])
            ->get()
            ->keyBy('code');

        if ($subjectModels->count() < 6) {
            return;
        }

        $program = TrainingProgram::query()->updateOrCreate(
            [
                'major_id' => $major?->id,
                'intake_id' => $intake->id,
                'version' => $intake->name . ' - ' . '2026',
            ],
            [
                'name' => [
                    'vi' => 'Chương trình đào tạo ' . ($major?->getTranslation('name', 'vi', false) ?: 'Ngành chung') . ' - ' . $intake->name,
                    'en' => 'Training Program ' . ($major?->getTranslation('name', 'en', false) ?: 'General Program') . ' - ' . $intake->name,
                ],
                'type' => ['vi' => 'Chính quy', 'en' => 'Full-time'],
                'level' => ['vi' => 'Đại học', 'en' => 'Undergraduate'],
                'language' => ['vi' => 'Tiếng Việt', 'en' => 'Vietnamese'],
                'duration_time' => 4,
                'status' => 'published',
                'published_at' => now(),
                'school_year_start' => 2026,
                'school_year_end' => 2030,
                'notes' => 'Du lieu mau cho module quan ly chuong trinh dao tao.',
                'total_credits' => 25,
            ]
        );

        $semester1 = ProgramSemester::query()->updateOrCreate(
            ['training_program_id' => $program->id, 'semester_no' => 1],
            [
                'total_credits' => 6,
            ]
        );

        $semester2 = ProgramSemester::query()->updateOrCreate(
            ['training_program_id' => $program->id, 'semester_no' => 2],
            [
                'total_credits' => 7,
            ]
        );

        $semester3 = ProgramSemester::query()->updateOrCreate(
            ['training_program_id' => $program->id, 'semester_no' => 3],
            [
                'total_credits' => 9,
            ]
        );

        $semester4 = ProgramSemester::query()->updateOrCreate(
            ['training_program_id' => $program->id, 'semester_no' => 4],
            [
                'total_credits' => 3,
            ]
        );

        $semester1->subjects()->sync([
            $subjectModels['IT101']->id => ['type' => 'required', 'notes' => 'Mon nen tang nhap mon', 'order' => 1],
            $subjectModels['IT102']->id => ['type' => 'required', 'notes' => 'Mon lap trinh co ban', 'order' => 2],
        ]);

        $semester2->subjects()->sync([
            $subjectModels['IT201']->id => ['type' => 'required', 'notes' => 'Hoc sau IT102', 'order' => 1],
            $subjectModels['IT202']->id => ['type' => 'required', 'notes' => 'Mon co so du lieu', 'order' => 2],
        ]);

        $semester3->subjects()->sync([
            $subjectModels['IT301']->id => ['type' => 'required', 'notes' => 'Mon phat trien web', 'order' => 1],
            $subjectModels['IT302']->id => ['type' => 'elective', 'notes' => 'Mon tu chon chuyen nganh', 'order' => 2],
            $subjectModels['IT303']->id => ['type' => 'required', 'notes' => 'Mon phan tich he thong', 'order' => 3],
        ]);

        $semester4->subjects()->sync([
            $subjectModels['IT304']->id => ['type' => 'required', 'notes' => 'Hoc sau IT301', 'order' => 1],
        ]);

        $prerequisites = [
            'IT201' => ['IT102'],
            'IT202' => ['IT102'],
            'IT301' => ['IT102', 'IT202'],
            'IT302' => ['IT102'],
            'IT303' => ['IT101', 'IT102'],
            'IT304' => ['IT301'],
        ];

        foreach ($prerequisites as $subjectCode => $requiredCodes) {
            $subjectId = $subjectModels->get($subjectCode)?->id;

            if (!$subjectId) {
                continue;
            }

            $requiredIds = collect($requiredCodes)
                ->map(fn (string $requiredCode) => $subjectModels->get($requiredCode)?->id)
                ->filter()
                ->values()
                ->all();

            $program->syncSubjectPrerequisites($subjectId, $requiredIds);
        }

        $semester1->update(['total_credits' => (int) $semester1->subjects()->sum('subjects.credits')]);
        $semester2->update(['total_credits' => (int) $semester2->subjects()->sum('subjects.credits')]);
        $semester3->update(['total_credits' => (int) $semester3->subjects()->sum('subjects.credits')]);
        $semester4->update(['total_credits' => (int) $semester4->subjects()->sum('subjects.credits')]);

        $program->update([
            'total_credits' => (int) ProgramSemester::query()
                ->where('training_program_id', $program->id)
                ->sum('total_credits'),
        ]);
    }
}


