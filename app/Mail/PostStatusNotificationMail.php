<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\Blade;

class PostStatusNotificationMail extends Mailable implements ShouldQueue, MailableContract
{
    use Queueable, SerializesModels;

    private string $templateType;
    private string $renderedSubject;
    private string $renderedHtml;

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
        $this->templateType = EmailTemplate::postStatusTemplateTypeForAction($this->action, $this->scheduledPublishAt);
        $this->renderTemplate();
    }

    private function renderTemplate(): void
    {
        try {
            $data = [
                'recipientName' => $this->recipientName,
                'postTitle' => $this->postTitle,
                'categoryNames' => $this->categoryNames,
                'action' => $this->action,
                'actorName' => $this->actorName,
                'postUrl' => $this->postUrl,
                'editUrl' => $this->editUrl,
                'reviewUrl' => $this->reviewUrl,
                'note' => $this->note,
                'scheduledPublishAt' => $this->scheduledPublishAt,
                'actionUrl' => $this->resolveActionUrl(),
            ];

            $rendered = EmailTemplateService::render($this->templateType, $data);
            $this->renderedSubject = $rendered['subject'];
            $this->renderedHtml = $rendered['body'];
        } catch (\Throwable $e) {
            report($e);

            $content = EmailTemplate::defaultContentBlocks($this->templateType);

            $categoryLabel = implode(', ', $this->categoryNames);

            $this->renderedSubject = $this->subjectLine();

            $this->renderedHtml = view('emails.post_status_notification', [
                'recipientName' => $this->recipientName,
                'postTitle' => $this->postTitle,
                'categoryNames' => $this->categoryNames,
                'actorName' => $this->actorName,
                'postUrl' => $this->postUrl,
                'editUrl' => $this->editUrl,
                'reviewUrl' => $this->reviewUrl,
                'note' => $this->note,
                'scheduledPublishAt' => $this->scheduledPublishAt,
                'content' => $content,
                'actionUrl' => $this->resolveActionUrl(),

                // Biến tiếng Việt
                'nguoi_dung' => $this->recipientName ?: 'người dùng',
                'tieu_de_bai_viet' => $this->postTitle,
                'danh_muc_bai_viet' => $categoryLabel,
                'nguoi_thuc_hien' => $this->actorName ?: 'Hệ thống',
                'ghi_chu' => $this->note ?? '',
                'lich_dang' => $this->scheduledPublishAt ?? '',
                'lien_ket_bai_viet' => $this->postUrl,
                'lien_ket_chinh_sua' => $this->editUrl,
                'lien_ket_duyet_bai' => $this->reviewUrl,
                'lien_ket_hanh_dong' => $this->resolveActionUrl(),
                'ten_hanh_dong' => $this->actionLabel(),
            ])->render();
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->recipientEmail],
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

    private function subjectLine(): string
    {
        return Blade::render(EmailTemplate::defaultSubjectFor($this->templateType), [
            'postTitle' => $this->postTitle,
            'tieu_de_bai_viet' => $this->postTitle,
            'actionLabel' => $this->actionLabel(),
            'ten_hanh_dong' => $this->actionLabel(),
        ]);
    }

    private function actionLabel(): string
    {
        return match ($this->action) {
            'submitted' => 'Bài viết chờ duyệt',
            'approved' => 'Bài viết đã được duyệt',
            'rejected' => 'Bài viết bị từ chối',
            'reverted_to_pending' => 'Nhắc duyệt lại bài viết',
            'reverted_to_pending_author' => 'Bài viết đã bị gỡ và chờ duyệt lại',
            default => 'Thông báo bài viết',
        };
    }

    private function resolveActionUrl(): string
    {
        return match ($this->templateType) {
            'post_status_submitted' => $this->reviewUrl ?: '#',

            'post_status_reverted_to_pending_author',
            'post_status_rejected' => $this->editUrl ?: '#',

            'post_status_approved_published' => $this->postUrl ?: '#',

            default => $this->postUrl ?: $this->editUrl ?: $this->reviewUrl ?: '#',
        };
    }
}
