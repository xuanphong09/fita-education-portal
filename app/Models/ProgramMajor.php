<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ProgramMajor extends Model
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

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name->vi');
    }

    public function majors(): HasMany
    {
        return $this->hasMany(Major::class, 'program_major_id');
    }
}

