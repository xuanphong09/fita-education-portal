<?php

namespace App\Notifications;

use App\Mail\PasswordResetMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class BrandedResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public int $expiresInMinutes;
    public string $expiresInHuman;

    public function __construct(string $token)
    {
        parent::__construct($token);

        $this->onQueue((string) config('queue.mail_queue', 'mail'));

        $broker = (string) config('auth.defaults.passwords', 'users');
        $this->expiresInMinutes = max(1, (int) config("auth.passwords.{$broker}.expire", 60));
        $this->expiresInHuman = $this->formatExpiry($this->expiresInMinutes);
    }

    public function toMail($notifiable)
    {
        return new PasswordResetMail($notifiable, $this->resetUrl($notifiable));
    }

    private function formatExpiry(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ((int) ($minutes / 1440)).' ngày';
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);
            return $hours === 1 ? '60 phút' : $hours.' giờ';
        }

        return $minutes.' phút';
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Gửi email quên mật khẩu thất bại sau khi retry', [
            'error' => $exception->getMessage(),
        ]);
    }
}




