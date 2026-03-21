<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubjectPrerequisite extends Pivot
{
    protected $table = 'subject_prerequisites';

    public $incrementing = true;

    protected $fillable = [
        'training_program_id',
        'subject_id',
        'prerequisite_subject_id',
    ];

    protected $casts = [
        'training_program_id' => 'integer',
        'subject_id' => 'integer',
        'prerequisite_subject_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->subject_id === $model->prerequisite_subject_id) {
                throw new InvalidArgumentException('Mon hoc khong the la mon tien quyet cua chinh no.');
            }

            if (!self::subjectBelongsToProgram((int) $model->training_program_id, (int) $model->subject_id)) {
                throw new InvalidArgumentException('Mon hoc hien tai khong thuoc chuong trinh dao tao da chon.');
            }

            if (!self::subjectBelongsToProgram((int) $model->training_program_id, (int) $model->prerequisite_subject_id)) {
                throw new InvalidArgumentException('Mon tien quyet khong thuoc chuong trinh dao tao da chon.');
            }
        });
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function prerequisiteSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'prerequisite_subject_id');
    }

    public function scopeForProgram(Builder $query, int $trainingProgramId): Builder
    {
        return $query->where('training_program_id', $trainingProgramId);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForPrerequisite(Builder $query, int $prerequisiteSubjectId): Builder
    {
        return $query->where('prerequisite_subject_id', $prerequisiteSubjectId);
    }

    public static function syncForProgramSubject(int $trainingProgramId, int $subjectId, array $prerequisiteSubjectIds): void
    {
        $prerequisiteSubjectIds = collect($prerequisiteSubjectIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $subjectId)
            ->unique()
            ->values()
            ->all();

        if (!self::subjectBelongsToProgram($trainingProgramId, $subjectId)) {
            throw new InvalidArgumentException('Mon hoc hien tai khong thuoc chuong trinh dao tao da chon.');
        }

        foreach ($prerequisiteSubjectIds as $prerequisiteSubjectId) {
            if (!self::subjectBelongsToProgram($trainingProgramId, $prerequisiteSubjectId)) {
                throw new InvalidArgumentException('Mon tien quyet khong thuoc chuong trinh dao tao da chon.');
            }
        }

        DB::transaction(function () use ($trainingProgramId, $subjectId, $prerequisiteSubjectIds): void {
            self::query()
                ->forProgram($trainingProgramId)
                ->forSubject($subjectId)
                ->whereNotIn('prerequisite_subject_id', $prerequisiteSubjectIds ?: [0])
                ->delete();

            if (empty($prerequisiteSubjectIds)) {
                return;
            }

            $now = now();

            $rows = collect($prerequisiteSubjectIds)->map(fn (int $prerequisiteId) => [
                'training_program_id' => $trainingProgramId,
                'subject_id' => $subjectId,
                'prerequisite_subject_id' => $prerequisiteId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            self::query()->upsert(
                $rows,
                ['training_program_id', 'subject_id', 'prerequisite_subject_id'],
                ['updated_at']
            );
        });
    }

    private static function subjectBelongsToProgram(int $trainingProgramId, int $subjectId): bool
    {
        return DB::table('program_semester_subjects')
            ->join('program_semesters', 'program_semesters.id', '=', 'program_semester_subjects.program_semester_id')
            ->where('program_semesters.training_program_id', $trainingProgramId)
            ->where('program_semester_subjects.subject_id', $subjectId)
            ->exists();
    }
}

