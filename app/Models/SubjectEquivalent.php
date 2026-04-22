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
        'subject_id',
        'equivalent_subject_id',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'equivalent_subject_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->subject_id === $model->equivalent_subject_id) {
                throw new InvalidArgumentException('Mon hoc khong the tuong duong voi chinh no.');
            }
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function equivalentSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'equivalent_subject_id');
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public static function syncForSubject(int $subjectId, array $equivalentSubjectIds): void
    {
        $equivalentSubjectIds = collect($equivalentSubjectIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $subjectId)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($subjectId, $equivalentSubjectIds): void {
            $existing = self::query()
                ->forSubject($subjectId)
                ->pluck('equivalent_subject_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $removedIds = array_values(array_diff($existing, $equivalentSubjectIds));

            if (!empty($removedIds)) {
                self::query()
                    ->where(function (Builder $q) use ($subjectId, $removedIds): void {
                        $q->where('subject_id', $subjectId)
                            ->whereIn('equivalent_subject_id', $removedIds);
                    })
                    ->orWhere(function (Builder $q) use ($subjectId, $removedIds): void {
                        $q->whereIn('subject_id', $removedIds)
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
                    'subject_id' => $subjectId,
                    'equivalent_subject_id' => $equivalentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Keep equivalence symmetric: A <-> B.
                $rows[] = [
                    'subject_id' => $equivalentId,
                    'equivalent_subject_id' => $subjectId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            self::query()->upsert(
                $rows,
                ['subject_id', 'equivalent_subject_id'],
                ['updated_at']
            );
        });
    }

    // Backward-compatible wrapper for existing callers.
    public static function syncForProgramSubject(int $trainingProgramId, int $subjectId, array $equivalentSubjectIds): void
    {
        self::syncForSubject($subjectId, $equivalentSubjectIds);
    }
}


