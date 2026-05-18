<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostDefaultImage extends Model
{
    protected $fillable = [
        'name',
        'image_path',
        'show_title',
        'text_color',
        'text_size',
        'text_alignment',
        'text_y_offset',
        'is_active',
        'order',
    ];

    protected $casts = [
        'text_size' => 'integer',
        'text_y_offset' => 'integer',
        'order' => 'integer',
        'show_title' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'post_default_image_id');
    }
}
