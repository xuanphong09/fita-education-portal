<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lecturer extends Model
{
    protected $fillable = [
        'user_id',
        'staff_code',
//        'full_name',
        'gender',
        'department_id',
        'degree',
        'academic_title',
        'phone',
        'positions'
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department():BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
