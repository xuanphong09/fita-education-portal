<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class ProgramSemester extends Model
{
    protected $fillable = [
        'training_program_id',
        'semester_no',
        'total_credits',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'semester_no' => 'integer',
        'total_credits' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'program_semester_subjects')
            ->withPivot(['type', 'notes', 'order'])
            ->withTimestamps();
    }

    // Mon bat buoc trong hoc ky hien tai.
    public function requiredSubjects(): BelongsToMany
    {
        return $this->subjects()
            ->wherePivotIn('type', ['required', 'pcbb'])
            ->orderBy('program_semester_subjects.order');
    }

    // Mon tu chon trong hoc ky hien tai.
    public function electiveSubjects(): BelongsToMany
    {
        return $this->subjects()
            ->wherePivot('type', 'elective')
            ->orderBy('program_semester_subjects.order');
    }

    // Scope tien ich de loc hoc ky co it nhat 1 mon bat buoc.
    public function scopeHasRequiredSubjects(Builder $query): Builder
    {
        return $query->whereHas('subjects', fn (Builder $q) => $q->whereIn('program_semester_subjects.type', ['required', 'pcbb']));
    }
}


