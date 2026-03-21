<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class GroupSubject extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->orderBy('code');
    }
}
