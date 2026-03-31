<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Major extends Model
{
    use HasTranslations;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'order',
        'is_active',
    ];

    public array $translatable = [
        'name',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name->vi');
    }

    public function students():HasMany
    {
        return $this->hasMany(Student::class, 'major_id');
    }

    public function trainingPrograms(): HasMany
    {
        return $this->hasMany(TrainingProgram::class);
    }
}
