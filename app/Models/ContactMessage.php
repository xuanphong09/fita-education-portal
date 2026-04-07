<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactMessage extends Model
{
    use SoftDeletes;

    public const STATUS_NEW = 'new';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'subject',
        'message',
        'ip_address',
        'user_agent',
        'locale',
        'recaptcha_score',
        'recaptcha_action',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'recaptcha_score' => 'decimal:2',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => ['label' => 'Mới', 'class' => 'badge-info'],
            self::STATUS_RECEIVED => ['label' => 'Đã tiếp nhận', 'class' => 'badge-primary'],
            self::STATUS_IN_PROGRESS => ['label' => 'Đang xử lý', 'class' => 'badge-warning'],
            self::STATUS_RESPONDED => ['label' => 'Đã phản hồi', 'class' => 'badge-success'],
            self::STATUS_RESOLVED => ['label' => 'Hoàn tất', 'class' => 'badge-success'],
            self::STATUS_PENDING => ['label' => 'Tạm hoãn', 'class' => 'badge-ghost text-black!'],
            self::STATUS_CANCELLED => ['label' => 'Đã hủy', 'class' => 'badge-error'],
        ];
    }

    public static function statusMeta(?string $status): array
    {
        return self::statusOptions()[$status] ?? self::statusOptions()[self::STATUS_NEW];
    }
}



