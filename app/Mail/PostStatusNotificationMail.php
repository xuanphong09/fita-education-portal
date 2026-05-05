<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostStatusNotificationMail extends Mailable implements ShouldQueue, MailableContract
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $recipientEmail,
        public string $postTitle,
        public array $categoryNames,
        public string $action,
        public string $actorName,
        public string $postUrl,
        public string $editUrl,
        public string $reviewUrl,
        public ?string $note = null,
        public ?string $scheduledPublishAt = null,
    ) {
        $this->onQueue((string) config('queue.mail_queue', 'mail'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.post_status_notification');
    }

    public function attachments(): array
    {
        return [];
    }

    private function subjectLine(): string
    {
        return match ($this->action) {
            'submitted' => 'Bài viết chờ duyệt: ' . $this->postTitle,
            'approved' => 'Bài viết đã được duyệt: ' . $this->postTitle,
            'rejected' => 'Bài viết bị từ chối: ' . $this->postTitle,
            'reverted_to_pending' => 'Nhắc duyệt lại bài viết: ' . $this->postTitle,
            'reverted_to_pending_author' => 'Bài viết đã bị gỡ và chờ duyệt lại: ' . $this->postTitle,
            default => 'Thông báo bài viết: ' . $this->postTitle,
        };
    }
}
