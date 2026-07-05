<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'academic_semester',
        'final_score',
        'score_10',
        'score_4',
        'letter_grade',
        'is_passed',
        'is_studying',
    ];

    protected $casts = [
        'final_score' => 'float',
        'score_10' => 'float',
        'score_4' => 'float',
        'is_passed' => 'integer',
        'is_studying' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
