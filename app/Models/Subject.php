<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Subject extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'credits',
        'credits_theory',
        'credits_practice',
        'group_subject_id',
        'is_active',
    ];

    public array $translatable = [
        'name',
    ];

    protected $casts = [
        'credits' => 'decimal:1',
        'credits_theory' => 'decimal:1',
        'credits_practice' => 'decimal:1',
        'group_subject_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function groupSubject(): BelongsTo
    {
        return $this->belongsTo(GroupSubject::class);
    }

    public function programSemesters(): BelongsToMany
    {
        return $this->belongsToMany(ProgramSemester::class, 'program_semester_subjects')
            ->withPivot(['type', 'notes', 'order'])
            ->withTimestamps();
    }

    // Mon tien quyet cua mon hien tai (A -> B)
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'subject_prerequisites',
            'subject_id',
            'prerequisite_subject_id'
        )
            ->using(SubjectPrerequisite::class)
            ->withPivot(['id', 'training_program_id'])
            ->withTimestamps();
    }

    // Cac mon yeu cau mon hien tai lam tien quyet (B <- A)
    public function requiredBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'subject_prerequisites',
            'prerequisite_subject_id',
            'subject_id'
        )
            ->using(SubjectPrerequisite::class)
            ->withPivot(['id', 'training_program_id'])
            ->withTimestamps();
    }

    public function prerequisiteLinks(): HasMany
    {
        return $this->hasMany(SubjectPrerequisite::class, 'subject_id');
    }

    public function requiredByLinks(): HasMany
    {
        return $this->hasMany(SubjectPrerequisite::class, 'prerequisite_subject_id');
    }

    public function prerequisitesForProgram(int $trainingProgramId): BelongsToMany
    {
        return $this->prerequisites()->wherePivot('training_program_id', $trainingProgramId);
    }

    public function requiredByForProgram(int $trainingProgramId): BelongsToMany
    {
        return $this->requiredBy()->wherePivot('training_program_id', $trainingProgramId);
    }

    // Mon tuong duong cua mon hien tai (A <-> B).
    public function equivalents(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'subject_equivalents',
            'subject_id',
            'equivalent_subject_id'
        )
            ->using(SubjectEquivalent::class)
            ->withPivot(['id', 'training_program_id'])
            ->withTimestamps();
    }

    public function equivalentOf(): BelongsToMany
    {
        return $this->belongsToMany(
            Subject::class,
            'subject_equivalents',
            'equivalent_subject_id',
            'subject_id'
        )
            ->using(SubjectEquivalent::class)
            ->withPivot(['id', 'training_program_id'])
            ->withTimestamps();
    }

    public function equivalentLinks(): HasMany
    {
        return $this->hasMany(SubjectEquivalent::class, 'subject_id');
    }

    public function equivalentOfLinks(): HasMany
    {
        return $this->hasMany(SubjectEquivalent::class, 'equivalent_subject_id');
    }

    public function equivalentsForProgram(int $trainingProgramId): BelongsToMany
    {
        return $this->equivalents()->wherePivot('training_program_id', $trainingProgramId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('code');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

            $query->where(function (Builder $inner) use ($keyword) {
                $inner->where('code', 'like', $keyword)
                    ->orWhereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                        [$keyword]
                    )
                    ->orWhereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                        [$keyword]
                    )
                    ->orWhereHas('groupSubject', function (Builder $groupQuery) use ($keyword) {
                        $groupQuery->whereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                            [$keyword]
                        )->orWhereRaw(
                            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '\\\\'",
                            [$keyword]
                        );
                    });
            });
        }

        return $query;
    }

    public static function formatCredit(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if (!is_numeric($normalized)) {
            return '0';
        }

        $number = round((float) $normalized, 1);

        // Hide trailing ".0" for whole numbers, keep one decimal for fractional values.
        if (abs($number - floor($number)) < 0.0001) {
            return (string) (int) $number;
        }

        return number_format($number, 1, '.', '');
    }

    public function getCreditsDisplayAttribute(): string
    {
        return self::formatCredit($this->credits);
    }

    public function getCreditsTheoryDisplayAttribute(): string
    {
        return self::formatCredit($this->credits_theory);
    }

    public function getCreditsPracticeDisplayAttribute(): string
    {
        return self::formatCredit($this->credits_practice);
    }
}


