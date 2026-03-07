<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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

    public function getUserTypeLabelAttribute()
    {
        return match($this->user_type) {
            'student' => 'Sinh viên',
            'lecturer' => 'Giảng viên',
            'admin' => 'Quản trị viên',
            default => 'Không xác định'
        };
    }
}
