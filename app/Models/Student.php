<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'student_code',
        'full_name',
        'class_name',
        'gender',
        'intake_id',
        'major_id',
        'date_of_birth',
        'phone',
        'program_major_id',
        'vnua_password',
        'gpa_4',
        'gpa_10',
        'total_credits_earned',
        'last_academic_stats_updated_at'
    ];

    protected function casts(): array
    {
        return [
            'vnua_password' => 'encrypted',

            'date_of_birth' => 'date',
            'last_academic_stats_updated_at' => 'datetime',

            'grade_sync_started_at' => 'datetime',
            'grade_sync_success_at' => 'datetime',
            'grade_sync_failed_at' => 'datetime',
            'grade_sync_failed_count' => 'integer',
        ];
    }

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function major():BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }
    public function programMajor():BelongsTo
    {
        return $this->belongsTo(ProgramMajor::class, 'program_major_id');
    }

    public function intake():BelongsTo
    {
        return $this->belongsTo(Intake::class, 'intake_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(StudentGrade::class, 'student_id');
    }

    // Hàm tiện ích lấy nhanh các môn sinh viên ĐÃ HỌC QUA
    public function passedSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_grades')
            ->withPivot(['score_10', 'score_4', 'letter_grade', 'is_passed', 'academic_semester'])
            ->wherePivot('is_passed', true)
            ->withTimestamps();
    }

    public function setVnuaPasswordAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['vnua_password'] = null;
        }
        else {
            $this->attributes['vnua_password'] = encrypt($value);
        }
    }

    public function getVnuaPasswordAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }
}
