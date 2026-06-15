<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intake extends Model
{
    protected $fillable = [
        'name',
        'year_number',
    ];

    protected $casts = [
        'year_number' => 'integer',
    ];

    public function students():HasMany
    {
        return $this->hasMany(Student::class, 'intake_id');
    }

    public function trainingPrograms(): HasMany
    {
        return $this->hasMany(TrainingProgram::class, 'intake_id');
    }
}
