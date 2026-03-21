<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intake extends Model
{
    protected $fillable = [
        'name',
    ];

    public function students():HasMany
    {
        return $this->hasMany(Student::class, 'major_id');
    }

    public function trainingPrograms(): HasMany
    {
        return $this->hasMany(TrainingProgram::class);
    }
}
