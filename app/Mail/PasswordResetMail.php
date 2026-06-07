<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $expiresInMinutes;
    public string $expiresInHuman;
    public string $systemEmail;
    private string $renderedSubject;
    private string $renderedHtml;

    public function __construct(public User $user, public string $resetUrl)
    {
        $broker = (string) config('auth.defaults.passwords', 'users');
        $this->expiresInMinutes = max(1, (int) config("auth.passwords.{$broker}.expire", 60));
        $this->expiresInHuman = $this->formatExpiry($this->expiresInMinutes);
        $this->systemEmail = (string) config('mail.from.address');

        $this->renderTemplate();
    }

    private function renderTemplate(): void
    {
        try {
            $data = [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'actionUrl' => $this->resetUrl,
                'expiresInHuman' => $this->expiresInHuman,
                'systemEmail' => $this->systemEmail,
            ];

            $rendered = EmailTemplateService::render('password_reset', $data);
            $this->renderedSubject = $rendered['subject'];
            $this->renderedHtml = $rendered['body'];
        } catch (\Throwable $e) {
            $content = EmailTemplate::defaultContentBlocks('password_reset');

            // Fallback ke template cố định nếu render DB gặp lỗi
            $this->renderedSubject = EmailTemplate::defaultSubjectFor('password_reset');
            $this->renderedHtml = view('emails.password_action', [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'actionUrl' => $this->resetUrl,
                'expiresInHuman' => $this->expiresInHuman,
                'systemEmail' => $this->systemEmail,
                'content' => $content,
            ])->render();
        }
    }

    private function formatExpiry(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            $days = (int) ($minutes / 1440);

            return $days . ' ngày';
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours === 1 ? '60 phút' : $hours . ' giờ';
        }

        return $minutes . ' phút';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->user->email],
            subject: $this->renderedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.raw_html',
            with: [
                'html' => $this->renderedHtml,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}



