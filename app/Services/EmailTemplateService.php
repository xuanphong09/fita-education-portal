<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Blade;

class EmailTemplateService
{
    public static function getTemplate(string $templateType): ?EmailTemplate
    {
        return EmailTemplate::where('template_type', $templateType)->first();
    }

    public static function shouldSend(string $templateType): bool
    {
        if (EmailTemplate::isRequiredTemplate($templateType)) {
            return true;
        }

        $template = EmailTemplate::where('template_type', $templateType)->first();

        return (bool) ($template?->is_active ?? false);
    }

    public static function renderSubject(string $templateType, array $data = []): string
    {
        $template = self::getTemplate($templateType);
        $subject = $template?->subject ?: EmailTemplate::defaultSubjectFor($templateType);
        $contentBlocks = self::resolvedContentBlocks($templateType, $template);

        $payload = array_merge($data, $contentBlocks, [
            'actionUrl' => $data['actionUrl']
                ?? $data['setupUrl']
                    ?? $data['resetUrl']
                    ?? $data['passwordUrl']
                    ?? $data['postUrl']
                    ?? $data['editUrl']
                    ?? $data['reviewUrl']
                    ?? '#',
            'actionLabel' => $data['actionLabel'] ?? self::defaultActionLabel($data['action'] ?? null),
        ]);

        $payload = self::withVietnameseTemplateVariables($payload);

        return Blade::render($subject, $payload);
    }

    public static function renderBody(string $templateType, array $data = []): string
    {
        return self::render($templateType, $data)['body'];
    }

    /**
     * Render cả subject và body
     */
    public static function render(string $templateType, array $data = [], array $overrides = []): array
    {
        $template = self::getTemplate($templateType);
        $contentBlocks = self::resolvedContentBlocks($templateType, $template);

        if ($overrides) {
            $contentBlocks = array_replace_recursive($contentBlocks, $overrides);
        }

        // Tạo payload trước
        $payload = array_merge($data, $contentBlocks, [
            'actionUrl' => $data['actionUrl']
                ?? $data['setupUrl']
                    ?? $data['resetUrl']
                    ?? $data['passwordUrl']
                    ?? $data['postUrl']
                    ?? $data['editUrl']
                    ?? $data['reviewUrl']
                    ?? '#',

            'actionLabel' => $data['actionLabel'] ?? self::defaultActionLabel($data['action'] ?? null),
        ]);

        // Quan trọng: thêm biến tiếng Việt TRƯỚC khi Blade::render content block
        $payload = self::withVietnameseTemplateVariables($payload);

        $renderedBlocks = [];

        foreach ($contentBlocks as $key => $value) {
            if (! is_string($value)) {
                $renderedBlocks[$key] = $value;
                continue;
            }

            try {
                $renderedBlocks[$key] = Blade::render($value, $payload);
            } catch (\Throwable $e) {
                report($e);

                throw new \RuntimeException(
                    "Lỗi render email template [{$templateType}] tại block [{$key}]: " . $e->getMessage(),
                    previous: $e
                );
            }
        }

        $renderPayload = array_merge($payload, $renderedBlocks, [
            'content' => $renderedBlocks,
            'templateType' => $templateType,
            'actionUrl' => $payload['lien_ket_hanh_dong'] ?? $payload['actionUrl'] ?? '#',
            'primaryCtaUrl' => $payload['lien_ket_hanh_dong'] ?? $payload['actionUrl'] ?? '#',
        ]);

        return [
            'subject' => Blade::render(
                $template?->subject ?: EmailTemplate::defaultSubjectFor($templateType),
                $renderPayload
            ),

            'body' => view(
                EmailTemplate::viewNameFor($templateType),
                $renderPayload
            )->render(),
        ];
    }

    private static function resolvedContentBlocks(string $templateType, ?EmailTemplate $template): array
    {
        $contentBlocks = EmailTemplate::defaultContentBlocks($templateType);

        if ($template?->content_blocks) {
            // Use array_replace_recursive to properly merge nested arrays
            $contentBlocks = array_replace_recursive($contentBlocks, $template->content_blocks);
        }

        return $contentBlocks;
    }

    private static function withVietnameseTemplateVariables(array $payload): array
    {
        $user = $payload['user'] ?? null;

        $nguoiDung = data_get($user, 'name')
            ?? ($payload['userName'] ?? null)
            ?? ($payload['recipientName'] ?? null)
            ?? 'người dùng';

        $categoryNames = $payload['categoryNames'] ?? '';

        if (is_array($categoryNames)) {
            $categoryNames = implode(', ', $categoryNames);
        }

        return array_merge($payload, [
            'nguoi_dung' => $nguoiDung,
            'lien_ket_hanh_dong' => $payload['actionUrl']
                ?? $payload['setupUrl']
                    ?? $payload['resetUrl']
                    ?? $payload['passwordUrl']
                    ?? $payload['postUrl']
                    ?? $payload['editUrl']
                    ?? $payload['reviewUrl']
                    ?? '#',
            'ten_hanh_dong' => $payload['actionLabel'] ?? 'Xem chi tiết',
            'email_he_thong' => $payload['systemEmail'] ?? config('mail.from.address'),
            'thoi_gian_hieu_luc' => $payload['expiresInHuman'] ?? '60 phút',

            'lien_ket_thiet_lap_mat_khau' => $payload['setupUrl'] ?? $payload['actionUrl'] ?? '#',
            'lien_ket_dat_lai_mat_khau' => $payload['resetUrl'] ?? $payload['actionUrl'] ?? '#',

            'tieu_de_bai_viet' => $payload['postTitle'] ?? 'Bài viết',
            'danh_muc_bai_viet' => $categoryNames,
            'nguoi_thuc_hien' => $payload['actorName'] ?? 'Hệ thống',
            'ghi_chu' => $payload['note'] ?? '',
            'lich_dang' => self::formatDateTimeForEmail($payload['scheduledPublishAt'] ?? null),
            'lien_ket_bai_viet' => $payload['postUrl'] ?? '#',
            'lien_ket_chinh_sua' => $payload['editUrl'] ?? '#',
            'lien_ket_duyet_bai' => $payload['reviewUrl'] ?? '#',
        ]);
    }

    private static function formatDateTimeForEmail($value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private static function defaultActionLabel(?string $action): string
    {
        return match ($action) {
            'submitted' => 'Bài viết chờ duyệt',
            'approved' => 'Bài viết đã được duyệt',
            'rejected' => 'Bài viết bị từ chối',
            'reverted_to_pending' => 'Nhắc duyệt lại bài viết',
            'reverted_to_pending_author' => 'Bài viết đã bị gỡ và chờ duyệt lại',
            default => 'Thông báo bài viết',
        };
    }
}

