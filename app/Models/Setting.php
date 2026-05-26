<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    public static function get(string $key, $default = null)
    {
        // Sử dụng Cache để lưu lại giá trị, tránh query nhiều lần trên mỗi request
        $value = Cache::rememberForever("setting_{$key}", function () use ($key) {
            $row = static::where('key', $key)->first();
            return $row ? $row->value : null;
        });

        if ($value === null) {
            return $default;
        }

        if (stripos($key, 'PASSWORD') !== false || stripos($key, 'SECRET') !== false) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        $store = $value;
        if (stripos($key, 'PASSWORD') !== false || stripos($key, 'SECRET') !== false) {
            // Không mã hóa nếu giá trị null/rỗng
            if (filled($value)) {
                $store = Crypt::encryptString((string) $value);
            }
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $store]
        );

        // Xóa cache ngay khi có thay đổi setting
        Cache::forget("setting_{$key}");
    }
}

