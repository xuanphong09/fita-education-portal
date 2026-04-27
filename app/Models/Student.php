<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'program_major_id'
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', // Ép kiểu về chuẩn ngày tháng
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
}
