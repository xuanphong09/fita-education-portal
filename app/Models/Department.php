<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Department extends Model
{
    use HasTranslations;

    protected $fillable = [
        'name',
    ];

    public array $translatable = ['name'];

    public function lecturer():HasMany
    {
        return $this->hasMany(Lecturer::class, 'department_id');
    }

}
