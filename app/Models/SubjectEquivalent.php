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

    public static function syncForSubject(
        int $subjectId,
        array $equivalentSubjectIds,
        bool $deleteAllRelated = false
    ): void
    {
        $equivalentSubjectIds = collect($equivalentSubjectIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $subjectId)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($subjectId, $equivalentSubjectIds, $deleteAllRelated): void {
            $subject = Subject::query()->findOrFail($subjectId);
            $subjectCredits = (float) ($subject->credits ?? 0);

            if ($deleteAllRelated) {
                // Khi đổi tổng tín chỉ: xóa sạch mọi liên kết liên quan tới môn này.
                self::query()
                    ->where(function (Builder $q) use ($subjectId): void {
                        $q->where('subject_id', $subjectId)
                            ->orWhere('equivalent_subject_id', $subjectId);
                    })
                    ->delete();
            } else {
                // Khi không đổi tổng tín chỉ: chỉ đồng bộ quan hệ do môn hiện tại quản lý.
                self::query()
                    ->where('subject_id', $subjectId)
                    ->orWhere(function (Builder $q) use ($subjectId, $subjectCredits): void {
                        $q->where('equivalent_subject_id', $subjectId)
                            ->whereIn('subject_id', function ($query) use ($subjectCredits) {
                                $query->select('id')
                                    ->from('subjects')
                                    ->where('credits', '>=', $subjectCredits)
                                    ->whereNull('deleted_at');
                            });
                    })
                    ->delete();
            }

            if (empty($equivalentSubjectIds)) {
                return;
            }

            $equivalentSubjects = Subject::query()
                ->whereIn('id', $equivalentSubjectIds)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get(['id', 'credits']);

            $now = now();
            $rows = [];

            foreach ($equivalentSubjects as $equivalentSubject) {
                $equivalentCredits = (float) ($equivalentSubject->credits ?? 0);

                // Môn ít tín hơn không được thay môn nhiều tín hơn.
                if ($equivalentCredits < $subjectCredits) {
                    continue;
                }

                // Luôn lưu chiều: môn hiện tại -> môn có thể học thay.
                $rows[] = [
                    'subject_id' => $subjectId,
                    'equivalent_subject_id' => (int) $equivalentSubject->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Nếu bằng tín chỉ thì lưu thêm chiều ngược lại.
                if (abs($equivalentCredits - $subjectCredits) < 0.0001) {
                    $rows[] = [
                        'subject_id' => (int) $equivalentSubject->id,
                        'equivalent_subject_id' => $subjectId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $rows = collect($rows)
                ->unique(fn ($row) => $row['subject_id'] . '-' . $row['equivalent_subject_id'])
                ->values()
                ->all();

            if (!empty($rows)) {
                self::query()->insert($rows);
            }
        });
    }

    // Backward-compatible wrapper for existing callers.
    public static function syncForProgramSubject(int $trainingProgramId, int $subjectId, array $equivalentSubjectIds): void
    {
        self::syncForSubject($subjectId, $equivalentSubjectIds);
    }
}


