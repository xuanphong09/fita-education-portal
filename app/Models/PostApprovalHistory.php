<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostApprovalHistory extends Model
{
    protected $fillable = [
        'post_id',
        'action',
        'actor_id',
        'reviewer_id',
        'note',
        'scheduled_publish_at',
    ];

    protected $casts = [
        'scheduled_publish_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }


}

