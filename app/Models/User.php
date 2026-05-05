<?php

namespace App\Models;

use App\Notifications\BrandedResetPasswordNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Searchable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sso_id',
        'name',
        'email',
        'password',
        'avatar',
        'is_active',
        'last_login_at',
        'access_token',
        'sso_password_setup_sent_at',
        'user_type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'access_token'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'sso_password_setup_sent_at' => 'datetime',
        ];
    }

    public function toSearchableArray()
    {
        // 3. 👇 Trả về mảng chứa các cột bạn muốn người dùng tìm thấy
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function student():hasOne
    {
        return $this->hasOne(Student::class);
    }
    public function lecturer():hasOne
    {
        return $this->hasOne(Lecturer::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    private function normalizePostCategoryIds(?array $categoryIds): array
    {
        return collect($categoryIds ?? [])
            ->map(fn ($categoryId) => (int) $categoryId)
            ->filter(fn ($categoryId) => $categoryId > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function hasAnyScopedPostPermission(string $basePermission): bool
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->contains(fn (string $permissionName) => Str::startsWith($permissionName, $basePermission . ':'));
    }

    public function scopedPostCategoryIds(string $basePermission): array
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->filter(fn (string $permissionName) => Str::startsWith($permissionName, $basePermission . ':'))
            ->map(function (string $permissionName) use ($basePermission) {
                $categoryId = (int) Str::after($permissionName, $basePermission . ':');

                return $categoryId > 0 ? $categoryId : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function canWritePosts(?array $categoryIds = null): bool
    {
        if ($this->can('quan_ly_bai_viet') || $this->can('viet_bai_viet')) {
            return true;
        }

        if ($categoryIds === null) {
            return $this->hasAnyScopedPostPermission('viet_bai_viet');
        }

        $categoryIds = $this->normalizePostCategoryIds($categoryIds);

        if ($categoryIds === []) {
            return false;
        }

        return collect($categoryIds)->every(fn (int $categoryId) => $this->can('viet_bai_viet:' . $categoryId));
    }

    public function canReviewPosts(?array $categoryIds = null): bool
    {
        if ($this->can('quan_ly_bai_viet') || $this->can('duyet_bai_viet')) {
            return true;
        }

        if ($categoryIds === null) {
            return $this->hasAnyScopedPostPermission('duyet_bai_viet');
        }

        $categoryIds = $this->normalizePostCategoryIds($categoryIds);

        if ($categoryIds === []) {
            return false;
        }

        return collect($categoryIds)->every(fn (int $categoryId) => $this->can('duyet_bai_viet:' . $categoryId));
    }

    public function canAccessPostModule(): bool
    {
        return $this->canWritePosts() || $this->canReviewPosts();
    }

    public function getUserTypeLabelAttribute()
    {
        return match($this->user_type) {
            'student' => 'Sinh viên',
            'lecturer' => 'Giảng viên',
            'admin' => 'Quản trị viên',
            default => 'Không xác định'
        };
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new BrandedResetPasswordNotification($token));
    }
}
