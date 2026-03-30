<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasTranslations, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'slug_translations',
        'description',
        'thumbnail',
        'order',
        'parent_id',
        'is_active',
    ];

    public array $translatable = ['name', 'description', 'slug_translations'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'category_post')
            ->withTimestamps()
            ->whereNull('posts.deleted_at');
    }

    // Helper to get translated name
    public function getTranslatedName(string $locale = null): string
    {
        return $this->getTranslation('name', $locale ?? app()->getLocale(), true);
    }
}

