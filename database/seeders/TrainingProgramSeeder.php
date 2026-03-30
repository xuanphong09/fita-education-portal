<?php

namespace Database\Seeders;

use App\Models\Intake;
use App\Models\Major;
use App\Models\ProgramSemester;
use App\Models\Subject;
use App\Models\SubjectEquivalent;
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

        // Get all subject codes that are used in semester maps below.
        // NOTE: custom textual options like "Chon 2/9 hoc phan..." are not subject codes, so they are not included here.
        $requiredCodes = [
            // Semester 1
            'ML01020', 'TH01009', 'TH01002', 'TH01006', 'TH01024', 'ML01009', 'TH02036', 'SN00010', 'GT01016', 'QS01011', 'QS01012','QS01011,QS01012',
            // Semester 2
            'SN00011', 'ML01021', 'TH01007', 'TH01023', 'TH02044', 'TH02001', 'TH02045', 'QS01013', 'QS01014','QS01013,QS01014',
            // Semester 3
            'SN01032', 'TH02037', 'TH02046', 'TH03005', 'TH02038', 'TH01025', 'TH02032', 'KT01020', 'SN01016', 'MT01001', 'Chọn 2/9 học phần GDTC: GT01017, GT01018,GT01019, GT01020,GT01021, GT01022,GT01023, GT01014,GT01015', 'KN01001/ KN01002/ KN01003/ KN01004/ KN01005/ KN01006/ KN01007/ KN01008/ KN01009/ KN01010/',
            // Semester 4
            'SN01033', 'ML01022', 'TH02015', 'TH03134', 'TH03106', 'TH03206',
            // Semester 5
            'SN03039', 'ML01005', 'MT01008', 'KQ01211', 'TH03133', 'TH03115',
            // Semester 6
            'ML01023', 'TH03324', 'TH03327', 'TH03101', 'TH03102', 'TH03103', 'TH03137', 'TH03112', 'TH03116', 'TH03136',
            // Semester 6 (Emerging Issues track)
            'TH03325', 'TH03328', 'TH03326',
            // Semester 7
            'TH03996', 'PTH03222', 'KQ03331', 'CD02148', 'CD03913', 'KQ02209', 'KT02003',
            // Semester 8
            'TH04996', 'TH03201', 'TH03316', 'TH03507', 'TH03110',
        ];

        $subjectModels = Subject::query()
            ->whereIn('code', $requiredCodes)
            ->get()
            ->keyBy('code');

        if ($subjectModels->isEmpty()) {
            return;
        }

        // Create or update training program
        $program = TrainingProgram::query()->updateOrCreate(
            [
                'major_id' => $major?->id,
                'intake_id' => $intake->id,
                'version' => $intake->name . ' - 2023',
            ],
            [
                'name' => [
                    'vi' => 'Chương trình đào tạo ' . ($major?->getTranslation('name', 'vi', false) ?: 'Công nghệ thông tin') . ' - ' . $intake->name,
                    'en' => 'Training Program ' . ($major?->getTranslation('name', 'en', false) ?: 'Information Technology') . ' - ' . $intake->name,
                ],
                'type' => ['vi' => 'Chính quy', 'en' => 'Full-time'],
                'level' => ['vi' => 'Đại học', 'en' => 'Undergraduate'],
                'language' => ['vi' => 'Tiếng Việt', 'en' => 'Vietnamese'],
                'duration_time' => 4,
                'status' => 'published',
                'published_at' => now(),
                'school_year_start' => 2023,
                'school_year_end' => 2027,
                'notes' => 'Chương trình đào tạo công nghệ thông tin theo tiêu chuẩn quốc tế.',
                'total_credits' => 126,
            ]
        );

        // Create semesters with their subjects - based on the curriculum images
        // SEMESTER 1 (2 năm học)
        $sem1 = $this->createSemester($program, 1, 18);
        $this->addSubjectsToSemester($sem1, [
            'ML01020' => ['type' => 'required', 'order' => 1],  // Triết học
            'TH01009' => ['type' => 'required', 'order' => 2],  // Tin học đại cương
            'TH01002' => ['type' => 'required', 'order' => 3],  // Vật lý đại cương
            'TH01006' => ['type' => 'required', 'order' => 4],  // Đại số tuyến tính
            'TH01024' => ['type' => 'required', 'order' => 5],  // Toán giải tích
            'ML01009' => ['type' => 'required', 'order' => 6],  // Pháp luật đại cương
            'TH02036' => ['type' => 'required', 'order' => 7],  // Nhập môn CNPM
            'SN00010' => ['type' => 'pcbb', 'order' => 8],  // Tiếng Anh bổ trợ
            'GT01016' => ['type' => 'pcbb', 'order' => 9],  // Giáo dục thể chất
            'QS01011,QS01012' => ['type' => 'pcbb', 'order' => 10],  // Giáo dục quốc phòng
        ], $subjectModels);

        // SEMESTER 2 (2 năm học)
        $sem2 = $this->createSemester($program, 2, 18);
        $this->addSubjectsToSemester($sem2, [
            'SN00011' => ['type' => 'pcbb', 'order' => 1],  // Tiếng Anh 0
            'ML01021' => ['type' => 'required', 'order' => 2],  // Kinh tế chính trị
            'TH01007' => ['type' => 'required', 'order' => 3],  // Xác suất thống kê
            'TH01023' => ['type' => 'required', 'order' => 4],  // Toán rời rạc
            'TH02044' => ['type' => 'required', 'order' => 5],  // Kiến trúc máy tính
            'TH02001' => ['type' => 'required', 'order' => 6],  // Cơ sở dữ liệu
            'TH02045' => ['type' => 'required', 'order' => 7],  // Kỹ thuật lập trình
            'QS01013,QS01014' => ['type' => 'pcbb', 'order' => 8],  // Giáo dục quốc phòng 3, 4
        ], $subjectModels);

        // SEMESTER 3 (2 năm học)
        $sem3 = $this->createSemester($program, 3, 17);
        $this->addSubjectsToSemester($sem3, [
            'SN01032' => ['type' => 'required', 'order' => 1],  // Tiếng Anh 1
            'TH02037' => ['type' => 'required', 'order' => 2],  // Phân tích và thiết kế hệ thống
            'TH02046' => ['type' => 'required', 'order' => 3],  // Cấu trúc dữ liệu và giải thuật
            'TH03005' => ['type' => 'required', 'order' => 4],  // Hệ quản trị cơ sở dữ liệu
            'TH02038' => ['type' => 'required', 'order' => 5],  // Mạng máy tính
            'TH01025' => ['type' => 'elective', 'order' => 6],  // Phương pháp tính
            'TH02032' => ['type' => 'elective', 'order' => 7],  // Phân tích số liệu
            'KT01020' => ['type' => 'elective', 'order' => 8],
            'SN01016' => ['type' => 'elective', 'order' => 9],  // Tâm lý học đại cương
            'MT01001' => ['type' => 'elective', 'order' => 10],  // Hóa học đại cương
            'Chọn 2/9 học phần GDTC: GT01017, GT01018,GT01019, GT01020,GT01021, GT01022,GT01023, GT01014,GT01015' => ['type' => 'pcbb', 'order' => 11],  // Hóa học đại cương
            'KN01001/ KN01002/ KN01003/ KN01004/ KN01005/ KN01006/ KN01007/ KN01008/ KN01009/ KN01010/' => ['type' => 'pcbb', 'order' => 12],  // Hóa học đại cương
        ], $subjectModels);

        // SEMESTER 4 (2 năm học)
        $sem4 = $this->createSemester($program, 4, 14);
        $this->addSubjectsToSemester($sem4, [
            'SN01033' => ['type' => 'required', 'order' => 1],  // Tiếng Anh 2
            'ML01022' => ['type' => 'required', 'order' => 2],  // Chủ nghĩa xã hội
            'TH02015' => ['type' => 'required', 'order' => 3],  // Nguyên lý hệ điều hành
            'TH03134' => ['type' => 'required', 'order' => 4],  // Phát triển phần mềm ứng dụng
            'TH03106' => ['type' => 'required', 'order' => 5],  // OOP
            'TH03206' => ['type' => 'required', 'order' => 6],

        ], $subjectModels);

        // SEMESTER 5 (3 năm học)
        $sem5 = $this->createSemester($program, 5, 17);
        $this->addSubjectsToSemester($sem5, [
            'SN03039' => ['type' => 'required', 'order' => 1],// Tiếng Anh chuyên ngành
            'ML01005' => ['type' => 'required', 'order' => 2],  // Tư tưởng Hồ Chí Minh
            'MT01008' => ['type' => 'required', 'order' => 3],  // Sinh thái môi trường
            'KQ01211' => ['type' => 'required', 'order' => 4],  // Quản trị học
            'TH03133' => ['type' => 'required', 'order' => 5],  // Phát triển web
            'TH03115' => ['type' => 'required', 'order' => 6],  // Phát triển GIS
        ], $subjectModels);

        // SEMESTER 6 (3 năm học) - Main track
        $sem6 = $this->createSemester($program, 6, 16);
        $this->addSubjectsToSemester($sem6, [
            'ML01023' => ['type' => 'required', 'order' => 1],  // Lịch sử Đảng
            'TH03324' => ['type' => 'required', 'order' => 2],  // An toàn hệ thống
            'TH03327' => ['type' => 'elective', 'order' => 3],
            'TH03101' => ['type' => 'elective', 'order' => 4],  // Quản lý dự án phần mềm
            'TH03102' => ['type' => 'elective', 'order' => 5],  // Phân tích yêu cầu phần mềm
            'TH03103' => ['type' => 'elective', 'order' => 6],  // Kiến trúc và thiết kế phần mềm
            'TH03137' => ['type' => 'elective', 'order' => 7],  // Kiểm thử và QA phần mềm
            'TH03112' => ['type' => 'elective', 'order' => 8],  // Phát triển di động
            'TH03116' => ['type' => 'elective', 'order' => 9],  // Phát triển di động
            'TH03136' => ['type' => 'elective', 'order' => 10],  // Thiết kế giao diện người dùng
        ], $subjectModels);

        // SEMESTER 6 (3 năm học) - Emerging Issues track (parallel option)
        $sem6_alt = $this->createSemester($program, 6, 10);
        $this->addSubjectsToSemester($sem6_alt, [
            'ML01023' => ['type' => 'required', 'order' => 1],  // Lịch sử Đảng
            'TH03324' => ['type' => 'required', 'order' => 2],  // An toàn hệ thống
            'TH03327' => ['type' => 'elective', 'order' => 3],
            'TH03101' => ['type' => 'elective', 'order' => 4],  // Quản lý dự án phần mềm
            'TH03102' => ['type' => 'elective', 'order' => 5],  // Phân tích yêu cầu phần mềm
            'TH03103' => ['type' => 'elective', 'order' => 6],  // Kiến trúc và thiết kế phần mềm
            'TH03137' => ['type' => 'elective', 'order' => 7],  // Kiểm thử và QA phần mềm
            'TH03112' => ['type' => 'elective', 'order' => 8],  // Phát triển di động
            'TH03116' => ['type' => 'elective', 'order' => 9],  // Phát triển di động
            'TH03136' => ['type' => 'elective', 'order' => 10],  // Thiết kế giao diện người dùng
        ], $subjectModels);

        // SEMESTER 7 (4 năm học)
        $sem7 = $this->createSemester($program, 7, 15);
        $this->addSubjectsToSemester($sem7, [
            'TH03996' => ['type' => 'required', 'order' => 1, 'notes' =>'Đã tích lũy được tối thiểu 80 tín chỉ'],  // Thực tập
            'PTH03222' => ['type' => 'elective', 'order' => 2],
            'KQ03331' => ['type' => 'elective', 'order' => 3],
            'CD02148' => ['type' => 'elective', 'order' => 4],
            'CD03913' => ['type' => 'elective', 'order' => 5],
            'KQ02209' => ['type' => 'elective', 'order' => 6],
            'KT02003' => ['type' => 'elective', 'order' => 7],
        ], $subjectModels);

        // SEMESTER 8 (4 năm học)
        $sem8 = $this->createSemester($program, 8, 15);
        $this->addSubjectsToSemester($sem8, [
            'TH04996' => ['type' => 'required', 'order' => 1, 'notes' => 'TTCN và đã tích lũy được tối thiểu 95 tín chỉ'],  // Khóa luận tốt nghiệp
            'TH03201' => ['type' => 'elective', 'order' => 2],  // OOP analysis and design
            'TH03316' => ['type' => 'elective', 'order' => 2],  // OOP analysis and design
            'TH03507' => ['type' => 'elective', 'order' => 2],  // OOP analysis and design
            'TH03110' => ['type' => 'elective', 'order' => 2],  // OOP analysis and design
        ], $subjectModels);

        // Set prerequisites
        $this->setSemesterPrerequisites($program, $subjectModels);

        // Set equivalent subjects (A <-> B) inside this training program
        $this->setSubjectEquivalents($program, $subjectModels);

        // Update total credits for all semesters
        $semesters = ProgramSemester::query()
            ->where('training_program_id', $program->id)
            ->get();

        foreach ($semesters as $semester) {
            $semester->update([
                'total_credits' => (int) $semester->subjects()->sum('subjects.credits'),
            ]);
        }

//        $program->update([
//            'total_credits' => (int) ProgramSemester::query()tôi
//                ->where('training_program_id', $program->id)
//                ->sum('total_credits'),
//        ]);
    }

    private function createSemester($program, $semesterNo, $totalCredits = 0, $description = null)
    {
        return ProgramSemester::query()->updateOrCreate(
            [
                'training_program_id' => $program->id,
                'semester_no' => $semesterNo,
            ],
            [
                'total_credits' => $totalCredits,
            ]
        );
    }

    private function addSubjectsToSemester($semester, $subjectsMap, $subjectModels)
    {
        $syncData = [];
        foreach ($subjectsMap as $code => $meta) {
            $subject = $subjectModels->get($code);
            if ($subject) {
                $syncData[$subject->id] = [
                    'type' => $meta['type'] ?? 'required',
                    'notes' => $meta['notes'] ?? null,
                    'order' => $meta['order'] ?? 0,
                ];
            }
        }
        $semester->subjects()->sync($syncData);
    }

    private function setSemesterPrerequisites($program, $subjectModels)
    {
        // Define prerequisites based on curriculum - only for subjects that exist
        $prerequisites = [
            'SN01032' => ['SN00010'],  // Tiếng Anh 1 requires Tiếng Anh bổ trợ
            'SN01033' => ['SN01032'],  // Tiếng Anh 2 requires Tiếng Anh 1
            'SN03039' => ['SN01033'],  // Tiếng Anh chuyên ngành requires Tiếng Anh 2
            'TH02045' => ['TH01009', 'TH01024'],  // Kỹ thuật lập trình
            'TH03106' => ['TH02045'],  // OOP requires Kỹ thuật lập trình
            'TH03134' => ['TH02045'],  // Phát triển phần mềm
            'TH03133' => ['TH02045'],  // Phát triển web
            'TH03115' => ['TH02045'],  // Phát triển GIS
            'TH02036' => ['TH01009'],  // Nhập môn CNPM
            'TH03101' => ['TH02036'],  // Quản lý dự án phần mềm
            'TH03102' => ['TH02036'],  // Phân tích yêu cầu
            'TH03103' => ['TH02036'],  // Kiến trúc và thiết kế
            'TH03137' => ['TH03106'],  // Kiểm thử
            'TH03112' => ['TH03134'],  // Phát triển di động
            'TH03324' => ['TH02015'],  // An toàn hệ thống
            'TH04996' => ['TH03996'],  // Khóa luận requires thực tập
            'TH03217' => ['TH02038'],
            'TH03201' => ['TH02037'],
            'TH03507' => ['TH02038'],
            'TH03110' => ['TH03133'],
        ];

        foreach ($prerequisites as $subjectCode => $requiredCodes) {
            $subject = $subjectModels->get($subjectCode);
            if (!$subject) {
                continue;
            }

            $requiredIds = collect($requiredCodes)
                ->map(fn($code) => $subjectModels->get($code)?->id)
                ->filter()
                ->values()
                ->all();

            if (!empty($requiredIds)) {
                try {
                    $program->syncSubjectPrerequisites($subject->id, $requiredIds);
                } catch (\Exception $e) {
                    // Skip if prerequisite validation fails (subject not in program)
                    continue;
                }
            }
        }
    }

    private function setSubjectEquivalents($program, $subjectModels): void
    {
        // Keep only practical equivalence pairs that already exist in this seeded program.
        // You can extend this map later when the official equivalence matrix is finalized.
        $equivalentMap = [
            'TH01002' => ['TH01029'],
            'TH02045' => ['TH02034'],
            'TH02046' => ['TH02016', 'TH02035'],
            'TH03005' => ['TH03107'],
            'TH03134' => ['TH03111'],
            'TH03133' => ['TH03109'],
            'TH03324' => ['TH03224'],
            'TH03137' => ['TH03105'],
            'TH03325' => ['TH03303'],
            'TH03326' => ['TH03117'],
        ];

        foreach ($equivalentMap as $subjectCode => $equivalentCodes) {
            $subject = $subjectModels->get($subjectCode)
                ?? Subject::query()->where('code', $subjectCode)->first();

            if (!$subject) {
                continue;
            }

            // Equivalent subjects can be outside the current CTDT subject list,
            // so resolve them from the full subjects table.
            $equivalentIds = Subject::query()
                ->whereIn('code', $equivalentCodes)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($equivalentIds)) {
                continue;
            }

            try {
                SubjectEquivalent::syncForProgramSubject($program->id, $subject->id, $equivalentIds);
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}


