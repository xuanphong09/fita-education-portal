<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Album extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'order',
        'is_featured_home',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_featured_home' => 'boolean',
    ];

    public function scopeFeaturedForHome($query)
    {
        return $query->where('is_featured_home', true);
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(AlbumImage::class, 'album_image_album')
            ->withTimestamps()
            ->orderByDesc('album_image_album.created_at');
    }
}
