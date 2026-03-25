<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lecturer extends Model
{
    protected $fillable = [
        'user_id',
        'staff_code',
        'slug',
//        'full_name',
        'gender',
        'department_id',
        'degree',
        'academic_title',
        'phone',
        'positions'
    ];

    protected $casts = [
        'positions' => 'array',
    ];

    public function positionForLocale(?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();
        $positions = $this->positions;

        if (is_array($positions)) {
            return $positions[$locale]
                ?? $positions['vi']
                ?? $positions['en']
                ?? null;
        }

        if (is_string($positions) && trim($positions) !== '') {
            $decoded = json_decode($positions, true);

            if (is_array($decoded)) {
                return $decoded[$locale]
                    ?? $decoded['vi']
                    ?? $decoded['en']
                    ?? null;
            }

            return $positions;
        }

        return null;
    }

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department():BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
