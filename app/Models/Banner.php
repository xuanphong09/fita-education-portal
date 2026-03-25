<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Banner extends Model
{
    use HasTranslations, SoftDeletes;

    public const POSITIONS = [
        'top left' => 'Trên cùng bên trái',
        'top center' => 'Trên cùng giữa',
        'top right' => 'Trên cùng bên phải',
        'center left' => 'Giữa bên trái',
        'center center' => 'Giữa giữa',
        'center right' => 'Giữa bên phải',
        'bottom left' => 'Dưới cùng bên trái',
        'bottom center' => 'Dưới cùng giữa',
        'bottom right' => 'Dưới cùng bên phải',
    ];

    protected $fillable = [
        'title',
        'description',
        'url_text',
        'url',
        'image',
        'position',
        'order',
        'is_active',
    ];

    public array $translatable = [
        'title',
        'description',
        'url_text',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'url_text' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

