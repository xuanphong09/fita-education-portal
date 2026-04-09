<?php

namespace App\Jobs;

use App\Mail\FirstTimePasswordSetup;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendFirstSsoPasswordSetupEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $userId)
    {
        $this->onQueue((string) config('queue.mail_queue', 'mail'));
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        // 1. Kiểm tra an toàn: Nếu không có user hoặc ĐÃ CÓ pass thì DỪNG
        if (!$user || $user->password) {
            return;
        }

        try {
            // 2. Tạo Token an toàn qua broker mặc định của Laravel
            $broker = (string) config('auth.defaults.passwords', 'users');
            $token = Password::broker($broker)->createToken($user);

            // 3. Tạo URL đổi mật khẩu
            $setupUrl = route('password.setup', [
                'token' => $token,
                'email' => $user->email
            ]);

            // 4. Gọi Mailable Class (Đã bao gồm cấu hình Queue bên trong)
            Mail::to($user->email)->send(new FirstTimePasswordSetup($user, $setupUrl));

        } catch (\Exception $e) {
            Log::error('Lỗi khi xử lý gửi email thiết lập mật khẩu lần đầu (Handle)', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            // Bắn lỗi ra để Queue Manager biết Job này failed và tiến hành Retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {

        Log::error('Queue hoàn toàn thất bại (Failed) cho SSO password setup email', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
