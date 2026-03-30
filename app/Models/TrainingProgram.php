<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class TrainingProgram extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'level',
        'language',
        'duration_time',
        'major_id',
        'intake_id',
        'school_year_start',
        'school_year_end',
        'version',
        'total_credits',
        'status',
        'published_at',
        'notes',
    ];

    public array $translatable = [
        'name',
        'type',
        'level',
        'language',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'school_year_start' => 'integer',
        'school_year_end' => 'integer',
        'duration_time' => 'integer',
        'total_credits' => 'integer',
    ];


    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function intake(): BelongsTo
    {
        return $this->belongsTo(Intake::class);
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(ProgramSemester::class)->orderBy('semester_no');
    }

    public function subjectPrerequisites(): HasMany
    {
        return $this->hasMany(SubjectPrerequisite::class, 'training_program_id');
    }

    public function syncSubjectPrerequisites(int $subjectId, array $prerequisiteSubjectIds): void
    {
        SubjectPrerequisite::syncForProgramSubject($this->id, $subjectId, $prerequisiteSubjectIds);
    }


    // Lay danh sach mon bat buoc cua ca CTDT, sap xep theo hoc ky va thu tu mon.
    public function requiredSubjectsQuery(): Builder
    {
        return Subject::query()
            ->select([
                'subjects.*',
                'program_semesters.semester_no',
                'program_semester_subjects.order as semester_subject_order',
                'program_semester_subjects.type',
                'program_semester_subjects.notes',
            ])
            ->join('program_semester_subjects', 'program_semester_subjects.subject_id', '=', 'subjects.id')
            ->join('program_semesters', 'program_semesters.id', '=', 'program_semester_subjects.program_semester_id')
            ->where('program_semesters.training_program_id', $this->id)
            ->whereIn('program_semester_subjects.type', ['required', 'pcbb'])
            ->orderBy('program_semesters.semester_no')
            ->orderBy('program_semester_subjects.order');
    }
}


