<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlbumImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'album_id',
        'image_path',
        'caption',
    ];

    protected $casts = [
        'album_id' => 'integer',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'album_image_album')
            ->withTimestamps();
    }
}
