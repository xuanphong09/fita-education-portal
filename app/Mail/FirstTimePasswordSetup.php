<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirstTimePasswordSetup extends Mailable
{
    use Queueable, SerializesModels;

    public int $expiresInMinutes;
    public string $expiresInHuman;
    public string $systemEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user, public string $setupUrl)
    {
        $broker = (string) config('auth.defaults.passwords', 'users');
        $this->expiresInMinutes = max(1, (int) config("auth.passwords.{$broker}.expire", 60));
        $this->expiresInHuman = $this->formatExpiry($this->expiresInMinutes);
        $this->systemEmail = (string) config('mail.from.address');
    }

    private function formatExpiry(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            $days = (int) ($minutes / 1440);
            return $days.' ngày';
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);
            if($hours === 1){
                return '60 phút';
            }
            return $hours.' giờ';
        }

        return $minutes.' phút';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thiết lập mật khẩu tài khoản FITA VNUA',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.first_time_setup',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
