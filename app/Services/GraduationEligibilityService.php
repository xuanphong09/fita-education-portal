<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TrainingProgram;
use Illuminate\Support\Collection;

class GraduationEligibilityService
{
    /**
     * Xét điều kiện tốt nghiệp của sinh viên theo chương trình đào tạo.
     *
     * Lưu ý:
     * - Chỉ xét học phần required và elective.
     * - Bỏ qua hoàn toàn học phần có type = pcbb.
     * - Môn bắt buộc có thể được hoàn thành bằng môn tương đương.
     * - Một môn đã dùng để thay thế môn bắt buộc sẽ không được tính lại
     *   vào tín chỉ tự chọn hoặc dùng cho một yêu cầu bắt buộc khác.
     */
    public function evaluate(
        Student $student,
        TrainingProgram $program
    ): array {
        $program->loadMissing([
            'semesters.subjects.equivalents',
            'semesters.subjects.groupSubject',
        ]);

        /*
         * Lấy kết quả tốt nhất của từng môn học.
         */
        $bestGrades = $student->grades()
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $grades) => $this->pickBestGrade($grades));

        $programSubjects = $program->semesters
            ->flatMap(function ($semester) {
                return collect($semester->subjects)
                    ->map(function ($subject) use ($semester) {
                        /*
                         * Gắn thông tin học kỳ của chương trình đào tạo
                         * để sử dụng khi hiển thị kết quả xét tốt nghiệp.
                         */
                        $subject->setAttribute(
                            'graduation_semester_no',
                            (int) $semester->semester_no
                        );

                        $subject->setAttribute(
                            'graduation_semester_name',
                            $semester->semester_name
                        );

                        return $subject;
                    });
            })
            ->unique(fn ($subject) => (int) $subject->id)
            ->values();

        $pcbbSubjectIds = $programSubjects
            ->filter(
                fn ($subject) => $this->subjectType($subject) === 'pcbb'
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        /*
         * Chỉ giữ các học phần thực sự được dùng để xét tốt nghiệp.
         */
        $graduationSubjects = $programSubjects
            ->reject(
                fn ($subject) => $pcbbSubjectIds->contains((int) $subject->id)
            )
            ->values();

        /*
         * Môn bắt buộc: chỉ xét type = required.
         */
        $requiredSubjects = $graduationSubjects
            ->filter(
                fn ($subject) => $this->subjectType($subject) === 'required'
            )
            ->values();

        /*
         * Lưu các môn đã được dùng để đáp ứng một yêu cầu bắt buộc.
         *
         * Việc này ngăn một môn:
         * - Thay thế cho nhiều môn bắt buộc khác nhau.
         * - Vừa thay thế môn bắt buộc, vừa được cộng tín chỉ tự chọn.
         */
        $usedPassedSubjectIds = collect();

        $requiredResults = $requiredSubjects
            ->map(function ($subject) use (
                $bestGrades,
                $pcbbSubjectIds,
                $usedPassedSubjectIds,
                $programSubjects
            ) {
                /*
                 * Loại PCBB khỏi danh sách môn thay thế.
                 */
                $validEquivalentSubjects = collect($subject->equivalents)
                    ->reject(
                        fn ($equivalent) => $pcbbSubjectIds->contains(
                            (int) $equivalent->id
                        )
                    )
                    ->unique(fn ($equivalent) => (int) $equivalent->id)
                    ->values();

                /*
                 * Danh sách môn có thể dùng để hoàn thành môn bắt buộc:
                 * môn chính hoặc môn thay thế.
                 */
                $candidateIds = collect([(int) $subject->id])
                    ->merge(
                        $validEquivalentSubjects
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                    )
                    ->unique()
                    ->values();

                $candidateGrades = $candidateIds
                    ->map(fn ($subjectId) => $bestGrades->get($subjectId))
                    ->filter()
                    ->values();

                /*
                 * Tìm môn đã đạt.
                 */
                $passedGrades = $candidateGrades
                    ->filter(function ($grade) use ($usedPassedSubjectIds) {
                        return (int) $grade->is_passed === 1
                            && !$usedPassedSubjectIds->contains(
                                (int) $grade->subject_id
                            );
                    })
                    ->values();

                /*
                 * Ưu tiên môn chính nếu đã đạt.
                 */
                $passedGrade = $passedGrades->first(
                    fn ($grade) =>
                        (int) $grade->subject_id === (int) $subject->id
                );

                /*
                 * Nếu môn chính chưa đạt thì lấy môn thay thế có điểm cao nhất.
                 */
                if (!$passedGrade) {
                    $passedGrade = $passedGrades
                        ->sortByDesc(
                            fn ($grade) => $this->gradeNumericScore($grade)
                        )
                        ->first();
                }

                /*
                 * Kiểm tra môn đang học.
                 */
                $studyingGrade = $candidateGrades
                    ->filter(fn ($grade) => (int) $grade->is_passed === -1)
                    ->sortByDesc('academic_semester')
                    ->first();

                /*
                 * Kiểm tra môn đã có điểm nhưng bị trượt.
                 */
                $failedGrade = $candidateGrades
                    ->filter(function ($grade) {
                        return (int) $grade->is_passed === 0
                            && $this->gradeNumericScore($grade) >= 0;
                    })
                    ->sortByDesc(
                        fn ($grade) => $this->gradeNumericScore($grade)
                    )
                    ->first();

                /*
                 * Có bản ghi môn học nhưng chưa có điểm.
                 */
                $noGrade = $candidateGrades
                    ->first(function ($grade) {
                        return (int) $grade->is_passed === 0
                            && $this->gradeNumericScore($grade) < 0;
                    });

                /*
                 * Xác định trạng thái hiển thị.
                 *
                 * Thứ tự ưu tiên:
                 * Đã đạt > Đang học > Trượt > Chưa có điểm > Chưa học.
                 */
                $learningStatus = match (true) {
                    $passedGrade !== null => 'passed',
                    $studyingGrade !== null => 'studying',
                    $failedGrade !== null => 'failed',
                    $noGrade !== null => 'no_grade',
                    default => 'not_studied',
                };

                $statusLabel = match ($learningStatus) {
                    'passed' => 'Đã đạt',
                    'studying' => 'Đang học',
                    'failed' => 'Trượt',
                    'no_grade' => 'Chưa có điểm',
                    default => 'Chưa học',
                };

                /*
                 * Bản ghi được dùng để hiển thị điểm.
                 */
                $displayGrade = $passedGrade
                    ?? $studyingGrade
                    ?? $failedGrade
                    ?? $noGrade;

                $passedSubjectId = $passedGrade
                    ? (int) $passedGrade->subject_id
                    : null;

                if ($passedSubjectId !== null) {
                    $usedPassedSubjectIds->push($passedSubjectId);
                }

                $passedByEquivalent = $passedSubjectId !== null
                    && $passedSubjectId !== (int) $subject->id;

                $equivalentSubject = $passedByEquivalent
                    ? $validEquivalentSubjects->firstWhere(
                        'id',
                        $passedSubjectId
                    )
                    : null;

                $substituteSubjects = $programSubjects->filter(function ($s) use ($subject) {
                    return (int) ($s->pivot->substitute_for_id ?? 0) === (int) $subject->id;
                })->values();

                $substitutesData = [];
                $earnedSubstituteCredits = 0;
                $isStudyingSubstitute = false;

                if ($substituteSubjects->isNotEmpty()) {
                    $substitutesData = $substituteSubjects->map(function ($sub) use ($bestGrades, &$earnedSubstituteCredits, &$isStudyingSubstitute) {
                        $subGrade = $bestGrades->get((int) $sub->id);
                        $subStatus = 'not_studied';

                        if ($subGrade) {
                            if ((int) $subGrade->is_passed === 1) {
                                $earnedSubstituteCredits += (float) ($sub->credits ?? 0);
                                $subStatus = 'passed';
                            } elseif ((int) $subGrade->is_passed === -1) {
                                $isStudyingSubstitute = true;
                                $subStatus = 'studying';
                            } else {
                                $subStatus = $this->gradeNumericScore($subGrade) >= 0 ? 'failed' : 'no_grade';
                            }
                        }

                        return [
                            'id' => (int) $sub->id,
                            'code' => (string) $sub->code,
                            'name' => $this->subjectName($sub),
                            'credits' => (float) ($sub->credits ?? 0),
                            'learning_status' => $subStatus,
                            'final_score' => $subGrade ? $subGrade->score_10 : null,
                        ];
                    })->all();
                }

                $passedBySubstitute = $earnedSubstituteCredits > 0 && $earnedSubstituteCredits >= (float) ($subject->credits ?? 0);

                if ($passedBySubstitute) {
                    // Đánh dấu Khóa luận là ĐẠT
                    $learningStatus = 'passed';
                    $statusLabel = 'Đạt (Học thay thế)';
                    $passedGrade = true; // Fake passed để nó không lọt vào danh sách thiếu

                    // Khóa 4 môn chuyên đề lại, không cho tính đúp vào "Tín chỉ tự chọn"
                    foreach ($substituteSubjects as $sub) {
                        $subGrade = $bestGrades->get((int) $sub->id);
                        if ($subGrade && (int) $subGrade->is_passed === 1) {
                            $usedPassedSubjectIds->push((int) $sub->id);
                        }
                    }
                } elseif ($learningStatus !== 'passed' && $isStudyingSubstitute) {
                    // Nếu chưa pass, nhưng đang học 1 trong 4 môn thay thế -> Báo Đang học KLTN
                    $learningStatus = 'studying';
                    $statusLabel = 'Đang học môn thay thế';
                }

                return [
                    'subject_id' => (int) $subject->id,
                    'code' => (string) $subject->code,
                    'name' => $this->subjectName($subject),
                    'credits' => (float) ($subject->credits ?? 0),

                    /*
                     * Học kỳ theo chương trình đào tạo.
                     */
                    'semester_no' => (int) (
                        $subject->graduation_semester_no ?? 0
                    ),

                    'semester_name' => trim((string) (
                        $subject->graduation_semester_name ?? ''
                    )),

                    /*
                     * Trạng thái học tập.
                     */
                    'passed' => $passedGrade !== null,
                    'learning_status' => $learningStatus,
                    'status_label' => $statusLabel,

                    /*
                     * Điểm hiện tại hoặc điểm lần trượt.
                     */
                    'score_10' => $displayGrade?->score_10,
                    'score_4' => $displayGrade?->score_4,
                    'letter_grade' => $displayGrade?->letter_grade,
                    'academic_semester' => $displayGrade?->academic_semester,

                    /*
                     * Thông tin môn thay thế.
                     */
                    'passed_subject_id' => $passedSubjectId,
                    'passed_by_equivalent' => $passedByEquivalent,
                    'passed_by_code' => $equivalentSubject?->code,
                    'passed_by_name' => $equivalentSubject
                        ? $this->subjectName($equivalentSubject)
                        : null,

                    'equivalents' => $validEquivalentSubjects
                        ->map(function ($equivalent) use ($bestGrades) {
                            $eqGrade = $bestGrades->get((int) $equivalent->id);
                            $eqStatus = 'not_studied';

                            if ($eqGrade) {
                                $eqStatus = match ((int) $eqGrade->is_passed) {
                                    1 => 'passed',
                                    -1 => 'studying',
                                    0 => $this->gradeNumericScore($eqGrade) >= 0 ? 'failed' : 'no_grade',
                                    default => 'not_studied',
                                };
                            }

                            return [
                                'id' => (int) $equivalent->id,
                                'code' => (string) $equivalent->code,
                                'name' => $this->subjectName($equivalent),
                                'credits' => (float) ($equivalent->credits ?? 0),
                                // Bổ sung 2 trường này để UI Modal nhận diện được
                                'learning_status' => $eqStatus,
                                'final_score' => $eqGrade ? $eqGrade->score_10 : null,
                            ];
                        })
                        ->values()
                        ->all(),
                    'substitutes' => $substitutesData,
                ];
            })
            ->values();

        $missingRequiredSubjects = $requiredResults
            ->where('passed', false)
            ->sortBy([
                ['semester_no', 'asc'],
                ['code', 'asc'],
            ])
            ->values();

        /*
         * Tín chỉ tự chọn:
         * - Chỉ xét type = elective.
         * - Không có PCBB vì đã được loại khỏi graduationSubjects.
         * - Không tính lại môn đã dùng thay thế môn bắt buộc.
         */
        $electiveSubjects = $graduationSubjects
            ->filter(
                fn ($subject) => $this->subjectType($subject) === 'elective'
            )
            ->values();

        $passedElectiveSubjects = $electiveSubjects
            ->filter(function ($subject) use (
                $bestGrades,
                $usedPassedSubjectIds
            ) {
                $subjectId = (int) $subject->id;
                $grade = $bestGrades->get($subjectId);

                return $grade
                    && (int) $grade->is_passed === 1
                    && !$usedPassedSubjectIds->contains($subjectId);
            })
            ->map(fn ($subject) => [
                'subject_id' => (int) $subject->id,
                'code' => (string) $subject->code,
                'name' => $this->subjectName($subject),
                'credits' => (float) ($subject->credits ?? 0),
                'group_id' => $subject->groupSubject?->id,
                'group_name' => $subject->groupSubject
                    ? $this->subjectName($subject->groupSubject)
                    : null,
                'score_10' => $bestGrades->get(
                    (int) $subject->id
                )?->score_10,
            ])
            ->unique('subject_id')
            ->values();

        /*
         * Tính tín chỉ xét tốt nghiệp, không sử dụng total_credits_earned
         * của sinh viên vì trường đó có thể đã bao gồm tín chỉ PCBB.
         */
        $requiredRequiredCredits = (float) $requiredSubjects
            ->sum(fn ($subject) => (float) ($subject->credits ?? 0));

        $earnedRequiredCredits = (float) $requiredResults
            ->where('passed', true)
            ->sum('credits');

        $requiredElectiveCredits = max(
            0,
            (float) ($program->elective_credits ?? 0)
        );

        $earnedElectiveCredits = (float) $passedElectiveSubjects
            ->sum('credits');

        /*
         * Tổng tín chỉ xét tốt nghiệp chỉ gồm:
         * tín chỉ bắt buộc + mức tín chỉ tự chọn tối thiểu.
         */
        $requiredTotalCredits = max(
            0,
            (float) ($program->total_credits ?? 0)
        );

        $earnedTotalCredits = $earnedRequiredCredits
            + $earnedElectiveCredits;

        $minimumGpa4 = (float) (
            $program->minimum_graduation_gpa_4 ?? 2.0
        );

        /*
         * GPA vẫn dùng GPA tích lũy chính thức đang lưu của sinh viên.
         * PCBB chỉ bị loại khỏi kiểm tra học phần và tín chỉ.
         */
        $currentGpa4 = $student->gpa_4 !== null
            ? (float) $student->gpa_4
            : null;

        $checks = [
            'required_subjects' => [
                'label' => 'Hoàn thành môn bắt buộc',
                'passed' => $missingRequiredSubjects->isEmpty(),
                'completed' => $requiredResults
                    ->where('passed', true)
                    ->count(),
                'required' => $requiredResults->count(),
            ],

            'elective_credits' => [
                'label' => 'Đủ tín chỉ tự chọn',
                'passed' => $earnedElectiveCredits
                    >= $requiredElectiveCredits,
                'earned' => $earnedElectiveCredits,
                'required' => $requiredElectiveCredits,
                'missing' => max(
                    0,
                    $requiredElectiveCredits - $earnedElectiveCredits
                ),
            ],

            'total_credits' => [
                'label' => 'Đủ tổng số tín chỉ xét tốt nghiệp',
                'passed' => $earnedTotalCredits >= $requiredTotalCredits,
                'earned' => $earnedTotalCredits,
                'required' => $requiredTotalCredits,
                'missing' => max(
                    0,
                    $requiredTotalCredits - $earnedTotalCredits
                ),
            ],

            'gpa' => [
                'label' => 'Đạt điểm trung bình tích lũy',
                'passed' => $currentGpa4 !== null
                    && $currentGpa4 >= $minimumGpa4,
                'current' => $currentGpa4,
                'required' => $minimumGpa4,
            ],
        ];

        $eligible = collect($checks)
            ->every(fn ($check) => $check['passed'] === true);

        return [
            'eligible' => $eligible,
            'status' => $eligible ? 'eligible' : 'not_eligible',

            'checks' => $checks,

            'required_subjects' => $requiredResults,
            'missing_required_subjects' => $missingRequiredSubjects,

            'passed_elective_subjects' => $passedElectiveSubjects,

            'summary' => [
                'pcbb_subjects_excluded' => $pcbbSubjectIds->count(),

                'required_subjects_completed' => $requiredResults
                    ->where('passed', true)
                    ->count(),

                'required_subjects_total' => $requiredResults->count(),

                'required_credits_earned' => $earnedRequiredCredits,
                'required_credits_required' => $requiredRequiredCredits,

                'elective_credits_earned' => $earnedElectiveCredits,
                'elective_credits_required' => $requiredElectiveCredits,

                'total_credits_earned' => $earnedTotalCredits,
                'total_credits_required' => $requiredTotalCredits,

                'gpa_4' => $currentGpa4,
                'minimum_gpa_4' => $minimumGpa4,
            ],
        ];
    }

    private function pickBestGrade(Collection $grades): mixed
    {
        $grades = $grades->filter();

        if ($grades->isEmpty()) {
            return null;
        }

        /*
         * Ưu tiên lần học đã đạt có điểm cao nhất.
         */
        $passed = $grades
            ->filter(fn ($grade) => (int) $grade->is_passed === 1)
            ->sortByDesc(fn ($grade) => $this->gradeNumericScore($grade))
            ->first();

        if ($passed) {
            return $passed;
        }

        /*
         * Nếu chưa đạt nhưng đang học lại thì ưu tiên trạng thái đang học.
         */
        $studying = $grades
            ->filter(fn ($grade) => (int) $grade->is_passed === -1)
            ->sortByDesc('academic_semester')
            ->first();

        if ($studying) {
            return $studying;
        }

        /*
         * Nếu toàn bộ lần học đều chưa đạt, lấy lần có điểm cao nhất.
         */
        return $grades
            ->sortByDesc(fn ($grade) => $this->gradeNumericScore($grade))
            ->first();
    }

    private function gradeNumericScore(mixed $grade): float
    {
        if (!$grade) {
            return -1;
        }

        if (is_numeric($grade->score_10)) {
            return (float) $grade->score_10;
        }

        if (is_numeric($grade->final_score)) {
            return (float) $grade->final_score;
        }

        return -1;
    }

    private function subjectName(mixed $subject): string
    {
        if (!$subject) {
            return '';
        }

        if (method_exists($subject, 'getTranslation')) {
            return trim((string) (
            $subject->getTranslation(
                'name',
                app()->getLocale(),
                false
            )
                ?: $subject->getTranslation('name', 'vi', false)
                ?: $subject->getTranslation('name', 'en', false)
            ));
        }

        return trim((string) ($subject->name ?? ''));
    }

    private function subjectType(mixed $subject): string
    {
        return strtolower(
            trim((string) ($subject->pivot->type ?? ''))
        );
    }
}
