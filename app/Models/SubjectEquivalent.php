<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubjectEquivalent extends Pivot
{
    protected $table = 'subject_equivalents';

    public $incrementing = true;

    protected $fillable = [
        'training_program_id',
        'subject_id',
        'equivalent_subject_id',
    ];

    protected $casts = [
        'training_program_id' => 'integer',
        'subject_id' => 'integer',
        'equivalent_subject_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->subject_id === $model->equivalent_subject_id) {
                throw new InvalidArgumentException('Mon hoc khong the tuong duong voi chinh no.');
            }

            if (!self::subjectBelongsToProgram((int) $model->training_program_id, (int) $model->subject_id)) {
                throw new InvalidArgumentException('Mon hoc hien tai khong thuoc chuong trinh dao tao da chon.');
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

    public function equivalentSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'equivalent_subject_id');
    }

    public function scopeForProgram(Builder $query, int $trainingProgramId): Builder
    {
        return $query->where('training_program_id', $trainingProgramId);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public static function syncForProgramSubject(int $trainingProgramId, int $subjectId, array $equivalentSubjectIds): void
    {
        $equivalentSubjectIds = collect($equivalentSubjectIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $subjectId)
            ->unique()
            ->values()
            ->all();

        if (!self::subjectBelongsToProgram($trainingProgramId, $subjectId)) {
            throw new InvalidArgumentException('Mon hoc hien tai khong thuoc chuong trinh dao tao da chon.');
        }

        DB::transaction(function () use ($trainingProgramId, $subjectId, $equivalentSubjectIds): void {
            $existing = self::query()
                ->forProgram($trainingProgramId)
                ->forSubject($subjectId)
                ->pluck('equivalent_subject_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $removedIds = array_values(array_diff($existing, $equivalentSubjectIds));

            if (!empty($removedIds)) {
                self::query()
                    ->forProgram($trainingProgramId)
                    ->where(function (Builder $q) use ($subjectId, $removedIds): void {
                        $q->where('subject_id', $subjectId)
                            ->whereIn('equivalent_subject_id', $removedIds);
                    })
                    ->orWhere(function (Builder $q) use ($trainingProgramId, $subjectId, $removedIds): void {
                        $q->where('training_program_id', $trainingProgramId)
                            ->whereIn('subject_id', $removedIds)
                            ->where('equivalent_subject_id', $subjectId);
                    })
                    ->delete();
            }

            if (empty($equivalentSubjectIds)) {
                return;
            }

            $now = now();
            $rows = [];

            foreach ($equivalentSubjectIds as $equivalentId) {
                $rows[] = [
                    'training_program_id' => $trainingProgramId,
                    'subject_id' => $subjectId,
                    'equivalent_subject_id' => $equivalentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Keep equivalence symmetric: A <-> B.
                $rows[] = [
                    'training_program_id' => $trainingProgramId,
                    'subject_id' => $equivalentId,
                    'equivalent_subject_id' => $subjectId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            self::query()->upsert(
                $rows,
                ['training_program_id', 'subject_id', 'equivalent_subject_id'],
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


