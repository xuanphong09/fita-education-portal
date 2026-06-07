<?php

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use Toast;

    public ?EmailTemplate $emailTemplate = null;

    public string $template_type = '';
    public string $subject = '';
    public bool $is_active = true;
    public string $description = '';
    public array $content_blocks = [];
    public array $fieldGroups = [];

    public string $previewHtml = '';
    public string $previewSubject = '';
    public bool $showPreviewModal = false;

    public bool $showEditBlockModal = false;
    public string $editingBlockKey = '';
    public string $editingBlockLabel = '';
    public string $editingBlockValue = '';
    public string $editingBlockType = 'text';

    public function mount($id): void
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            $this->error('Email template không tìm thấy');
            $this->emailTemplate = null;
            return;
        }

        $this->emailTemplate = $template;
        $this->template_type = $template->template_type;
        $this->subject = $template->subject ?: EmailTemplate::defaultSubjectFor($template->template_type);
        $this->is_active = EmailTemplate::isRequiredTemplate($template->template_type) ? true : (bool) $template->is_active;
        $this->description = (string)($template->description ?? '');
        $this->content_blocks = $template->mergedContentBlocks();
        $this->fieldGroups = EmailTemplate::fieldDefinitionsFor($template->template_type);
    }

    public function getCanToggleActiveProperty(): bool
    {
        return EmailTemplate::canDisable($this->template_type);
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content_blocks' => ['array'],
        ]);

        if (!$this->emailTemplate) {
            $this->error('Email template không tìm thấy');
            return;
        }

        $isRequiredEmail = EmailTemplate::isRequiredTemplate($this->template_type);

        try {
            $this->emailTemplate->forceFill([
                'template_type' => $this->template_type,
                'subject' => $this->subject,
                'content_blocks' => $this->content_blocks,
                'is_active' => $isRequiredEmail ? true : $this->is_active,
                'description' => $this->description,
            ]);

            $this->emailTemplate->save();

            $this->success('Template đã được lưu thành công');
        } catch (\Throwable $e) {
            $this->error('Lỗi: ' . $e->getMessage());
        }
    }

    public function preview(): void
    {
        if (!$this->emailTemplate) {
            $this->error('Vui lòng chọn template để xem trước');
            return;
        }

        try {
            $sampleData = $this->sampleDataForPreview();

            $rendered = EmailTemplateService::render(
                $this->template_type,
                $sampleData,
                $this->content_blocks
            );

            $this->previewSubject = (string)($rendered['subject'] ?? Blade::render($this->subject, $sampleData));

            $this->previewHtml = view('emails.raw_html', [
                'html' => $rendered['body'] ?? '',
            ])->render();

            $this->showPreviewModal = true;
        } catch (\Throwable $e) {
            $this->previewSubject = 'Lỗi xem trước email';

            $this->previewHtml = '
                <div style="font-family: Arial, sans-serif; padding: 24px; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;">
                    <h2 style="margin: 0 0 12px; font-size: 18px;">Không thể xem trước email template</h2>
                    <p style="margin: 0; font-size: 14px;">' . e($e->getMessage()) . '</p>
                </div>
            ';

            $this->showPreviewModal = true;

            $this->error('Lỗi xem trước: ' . $e->getMessage());
        }
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
    }

    public function resetToDefault(): void
    {
        if (!$this->emailTemplate) {
            return;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn khôi phục nội dung mặc định?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmResetToDefault',
            'id' => '',
        ]);
    }

    #[On('confirmResetToDefault')]
    public function confirmResetToDefault()
    {
        $this->content_blocks = EmailTemplate::defaultContentBlocks($this->template_type);
        $this->subject = EmailTemplate::defaultSubjectFor($this->template_type);
        $this->description = EmailTemplate::defaultDescriptionFor($this->template_type);

        $this->previewHtml = '';
        $this->previewSubject = '';
        $this->showPreviewModal = false;

        $this->closeEditBlockModal();

        $this->success('Đã khôi phục nội dung mặc định');
    }

    public function editBlock(string $key): void
    {
        $field = $this->findFieldDefinition($key);

        $this->editingBlockKey = $key;
        $this->editingBlockLabel = $field['label'] ?? $this->humanizeTemplateType($key);
        $this->editingBlockType = $field['type'] ?? 'text';
        $this->editingBlockValue = (string)data_get($this->content_blocks, $key, '');

        $this->showEditBlockModal = true;
    }

    public function saveEditingBlock(): void
    {
        if ($this->editingBlockKey === '') {
            return;
        }

        data_set($this->content_blocks, $this->editingBlockKey, $this->editingBlockValue);

        $this->previewHtml = '';
        $this->previewSubject = '';
        $this->showPreviewModal = false;
        $this->showEditBlockModal = false;

        $this->success('Đã cập nhật nội dung đoạn này');
    }

    public function closeEditBlockModal(): void
    {
        $this->showEditBlockModal = false;
        $this->editingBlockKey = '';
        $this->editingBlockLabel = '';
        $this->editingBlockValue = '';
        $this->editingBlockType = 'text';
    }

    public function getEditableFieldsProperty(): array
    {
        return collect($this->fieldGroups)
            ->flatMap(fn($group) => collect($group['fields'] ?? [])->map(function ($field) use ($group) {
                $field['group_title'] = $group['title'] ?? 'Nội dung';
                return $field;
            }))
            ->values()
            ->all();
    }

    public function blockValue(string $key, string $fallback = ''): string
    {
        $value = data_get($this->content_blocks, $key);

        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string)$value;
    }

    public function renderPreviewText(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        try {
            return Blade::render($text, $this->sampleDataForPreview());
        } catch (\Throwable) {
            return $text;
        }
    }

    public function fieldRenderType(array $field): string
    {
        $key = strtolower((string)($field['key'] ?? ''));
        $label = strtolower((string)($field['label'] ?? ''));
        $group = strtolower((string)($field['group_title'] ?? ''));

        if (str_contains($key, 'button') || str_contains($label, 'nút') || str_contains($label, 'button')) {
            return 'button';
        }

        if (
            str_contains($key, 'security')
            || str_contains($key, 'note')
            || str_contains($key, 'title_label')
            || str_contains($key, 'category_label')
            || str_contains($key, 'actor_label')
            || str_contains($key, 'schedule_label')
            || str_contains($label, 'lưu ý')
            || str_contains($label, 'bảo mật')
            || str_contains($group, 'lưu ý')
            || str_contains($group, 'bảo mật')
        ) {
            return 'note';
        }

        if (
            str_contains($key, 'signature')
            || str_contains($key, 'regards')
            || str_contains($label, 'chữ ký')
            || str_contains($label, 'trân trọng')
        ) {
            return 'signature';
        }

        if (
//            str_contains($key, 'title')||
            str_contains($key, 'heading')
            || str_contains($key, 'greeting')
//            || str_contains($label, 'tiêu đề')
            || str_contains($label, 'lời chào')
        ) {
            return 'heading';
        }

        return 'paragraph';
    }

    private function humanizeTemplateType(string $templateType): string
    {
        return match ($templateType) {
            'first_time_password_setup' => 'Email thiết lập mật khẩu lần đầu',
            'password_reset' => 'Email đặt lại mật khẩu',
            'post_status_submitted' => 'Gửi bài viết cần duyệt',
            'post_status_reverted_to_pending_author' => 'Thu hồi duyệt, trả về tác giả',
            'post_status_approved_published' => 'Duyệt bài viết',
            'post_status_rejected' => 'Từ chối bài viết',
            default => ucfirst(str_replace('_', ' ', $templateType)),
        };
    }

    private function sampleDataForPreview(): array
    {
        $data = match ($this->template_type) {
            'first_time_password_setup' => [
                'user' => (object)['name' => 'Nguyễn Văn A'],
                'setupUrl' => 'https://example.com/setup-password',
                'actionUrl' => 'https://example.com/setup-password',
                'expiresInHuman' => '24 giờ',
                'systemEmail' => 'noreply@fita.edu.vn',
            ],

            'password_reset' => [
                'user' => (object)['name' => 'Nguyễn Văn A'],
                'resetUrl' => 'https://example.com/reset-password',
                'actionUrl' => 'https://example.com/reset-password',
                'expiresInHuman' => '60 phút',
                'systemEmail' => 'noreply@fita.edu.vn',
            ],

            'post_status_submitted' => [
                'recipientName' => 'Quản trị hệ thống',
                'postTitle' => 'Tiều đề bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Nguyễn Văn A',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/review',
                'action' => 'submitted',
                'note' => null,
                'scheduledPublishAt' => null,
                'actionLabel' => 'Bài viết chờ duyệt',
            ],

            'post_status_reverted_to_pending' => [
                'recipientName' => 'Người duyệt bài',
                'postTitle' => 'Tiều đề bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/review',
                'action' => 'reverted_to_pending',
                'note' => null,
                'scheduledPublishAt' => null,
                'actionLabel' => 'Nhắc duyệt lại bài viết',
            ],

            'post_status_reverted_to_pending_author' => [
                'recipientName' => 'Nguyễn Văn A',
                'postTitle' => 'Tiều đề bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/edit',
                'action' => 'reverted_to_pending_author',
                'note' => null,
                'scheduledPublishAt' => null,
                'actionLabel' => 'Bài viết đã bị gỡ và chờ duyệt lại',
            ],

            'post_status_approved_published' => [
                'recipientName' => 'Nguyễn Văn A',
                'postTitle' => 'Tiều đề bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/posts/1',
                'action' => 'approved',
                'note' => null,
                'scheduledPublishAt' => now()->addDay()->toDateTimeString(),
                'actionLabel' => 'Bài viết đã được duyệt',
            ],

            'post_status_rejected' => [
                'recipientName' => 'Nguyễn Văn A',
                'postTitle' => 'Tiều đề bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/edit',
                'action' => 'rejected',
                'note' => 'Nội dung bài viết chưa phù hợp, vui lòng chỉnh sửa và gửi duyệt lại.',
                'scheduledPublishAt' => null,
                'actionLabel' => 'Bài viết bị từ chối',
            ],

            default => [],
        };

        return $this->withVietnameseTemplateVariables($data);
    }

    private function withVietnameseTemplateVariables(array $data): array
    {
        $user = $data['user'] ?? null;

        $nguoiDung = data_get($user, 'name')
            ?? ($data['userName'] ?? null)
            ?? ($data['recipientName'] ?? null)
            ?? 'người dùng';

        $categoryNames = $data['categoryNames'] ?? '';

        if (is_array($categoryNames)) {
            $categoryNames = implode(', ', $categoryNames);
        }

        return array_merge($data, [
            'nguoi_dung' => $nguoiDung,
            'lien_ket_hanh_dong' => $data['actionUrl']
                ?? $data['setupUrl']
                    ?? $data['resetUrl']
                    ?? $data['passwordUrl']
                    ?? $data['postUrl']
                    ?? $data['editUrl']
                    ?? $data['reviewUrl']
                    ?? '#',
            'ten_hanh_dong' => $data['actionLabel'] ?? 'Xem chi tiết',
            'email_he_thong' => $data['systemEmail'] ?? config('mail.from.address'),
            'thoi_gian_hieu_luc' => $data['expiresInHuman'] ?? '60 phút',

            'lien_ket_thiet_lap_mat_khau' => $data['setupUrl'] ?? $data['actionUrl'] ?? '#',
            'lien_ket_dat_lai_mat_khau' => $data['resetUrl'] ?? $data['actionUrl'] ?? '#',

            'tieu_de_bai_viet' => $data['postTitle'] ?? 'Bài viết',
            'danh_muc_bai_viet' => $categoryNames,
            'nguoi_thuc_hien' => $data['actorName'] ?? 'Hệ thống',
            'ghi_chu' => $data['note'] ?? '',
            'lich_dang' => $this->formatDateTimeForEmail($data['scheduledPublishAt'] ?? null),
            'lien_ket_bai_viet' => $data['postUrl'] ?? '#',
            'lien_ket_chinh_sua' => $data['editUrl'] ?? '#',
            'lien_ket_duyet_bai' => $data['reviewUrl'] ?? '#',
        ]);
    }

    private function formatDateTimeForEmail($value): string
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

    private function findFieldDefinition(string $key): ?array
    {
        foreach ($this->fieldGroups as $group) {
            foreach (($group['fields'] ?? []) as $field) {
                if (($field['key'] ?? null) === $key) {
                    return $field;
                }
            }
        }

        return null;
    }

    public function getAvailableVariablesProperty(): array
    {
        $common = [
            [
                'name' => '$nguoi_dung',
                'insert' => '{{ $nguoi_dung }}',
                'description' => 'Tên người dùng hoặc người nhận email.',
            ],
//            [
//                'name' => '$lien_ket_hanh_dong',
//                'insert' => '{{ $lien_ket_hanh_dong }}',
//                'description' => 'Đường dẫn hành động chính trong email.',
//            ],
            [
                'name' => '$email_he_thong',
                'insert' => '{{ $email_he_thong }}',
                'description' => 'Email hệ thống dùng để hỗ trợ người nhận.',
            ],
        ];

        $passwordVariables = [
            [
                'name' => '$thoi_gian_hieu_luc',
                'insert' => '{{ $thoi_gian_hieu_luc }}',
                'description' => 'Thời gian hiệu lực của liên kết, ví dụ 60 phút hoặc 24 giờ.',
            ],
            [
                'name' => '$lien_ket_thiet_lap_mat_khau',
                'insert' => '{{ $lien_ket_thiet_lap_mat_khau }}',
                'description' => 'Link thiết lập mật khẩu lần đầu.',
            ],
            [
                'name' => '$lien_ket_dat_lai_mat_khau',
                'insert' => '{{ $lien_ket_dat_lai_mat_khau }}',
                'description' => 'Link đặt lại mật khẩu.',
            ],
        ];

        $postVariables = [
            [
                'name' => '$tieu_de_bai_viet',
                'insert' => '{{ $tieu_de_bai_viet }}',
                'description' => 'Tiêu đề bài viết.',
            ],
            [
                'name' => '$danh_muc_bai_viet',
                'insert' => '{{ $danh_muc_bai_viet }}',
                'description' => 'Danh sách danh mục của bài viết.',
            ],
            [
                'name' => '$nguoi_thuc_hien',
                'insert' => '{{ $nguoi_thuc_hien }}',
                'description' => 'Người thực hiện thao tác, ví dụ tác giả hoặc người duyệt bài.',
            ],
            [
                'name' => '$lien_ket_chinh_sua',
                'insert' => '{{ $lien_ket_chinh_sua }}',
                'description' => 'Link chỉnh sửa bài viết trong trang quản trị.',
            ],
            [
                'name' => '$lien_ket_duyet_bai',
                'insert' => '{{ $lien_ket_duyet_bai }}',
                'description' => 'Link duyệt bài trong trang quản trị.',
            ],
        ];

        $postApprovedVariables = [
            [
                'name' => '$tieu_de_bai_viet',
                'insert' => '{{ $tieu_de_bai_viet }}',
                'description' => 'Tiêu đề bài viết.',
            ],
            [
                'name' => '$danh_muc_bai_viet',
                'insert' => '{{ $danh_muc_bai_viet }}',
                'description' => 'Danh sách danh mục của bài viết.',
            ],
            [
                'name' => '$nguoi_thuc_hien',
                'insert' => '{{ $nguoi_thuc_hien }}',
                'description' => 'Người thực hiện thao tác, ví dụ admin hoặc người duyệt bài.',
            ],
            [
                'name' => '$lich_dang',
                'insert' => '{{ $lich_dang }}',
                'description' => 'Thời gian lên lịch đăng bài nếu có.',
            ],
            [
                'name' => '$lien_ket_bai_viet',
                'insert' => '{{ $lien_ket_bai_viet }}',
                'description' => 'Link xem bài viết ngoài trang người dùng.',
            ],
            [
                'name' => '$lien_ket_chinh_sua',
                'insert' => '{{ $lien_ket_chinh_sua }}',
                'description' => 'Link chỉnh sửa bài viết trong trang quản trị.',
            ],
            [
                'name' => '$lien_ket_duyet_bai',
                'insert' => '{{ $lien_ket_duyet_bai }}',
                'description' => 'Link duyệt bài trong trang quản trị.',
            ],
        ];

        $postRejectedVariables = [
            [
                'name' => '$tieu_de_bai_viet',
                'insert' => '{{ $tieu_de_bai_viet }}',
                'description' => 'Tiêu đề bài viết.',
            ],
            [
                'name' => '$danh_muc_bai_viet',
                'insert' => '{{ $danh_muc_bai_viet }}',
                'description' => 'Danh sách danh mục của bài viết.',
            ],
            [
                'name' => '$nguoi_thuc_hien',
                'insert' => '{{ $nguoi_thuc_hien }}',
                'description' => 'Người thực hiện thao tác, ví dụ admin hoặc người duyệt bài.',
            ],
            [
                'name' => '$ghi_chu',
                'insert' => '{{ $ghi_chu }}',
                'description' => 'Ghi chú hoặc lý do từ chối bài viết.',
            ],
            [
                'name' => '$lien_ket_chinh_sua',
                'insert' => '{{ $lien_ket_chinh_sua }}',
                'description' => 'Link chỉnh sửa bài viết trong trang quản trị.',
            ],
            [
                'name' => '$lien_ket_duyet_bai',
                'insert' => '{{ $lien_ket_duyet_bai }}',
                'description' => 'Link duyệt bài trong trang quản trị.',
            ],
        ];

        return match ($this->template_type) {
            'first_time_password_setup',
            'password_reset' => array_values(array_merge($common, $passwordVariables)),

            'post_status_approved_published'=> array_values(array_merge($common, $postApprovedVariables)),
            'post_status_rejected'=> array_values(array_merge($common, $postRejectedVariables)),
            'post_status_submitted',
            'post_status_reverted_to_pending_author',
            'post_status_notification' => array_values(array_merge($common, $postVariables)),

            default => $common,
        };
    }
};
?>

<div class="space-y-6">
    <x-slot:title>Chỉnh sửa Email Template</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.email-template.index') }}" class="font-semibold text-slate-700" wire:navigate>Danh sách
            Email Template</a>
        <span class="mx-1">/</span><span>Chỉnh sửa Template</span>
    </x-slot:breadcrumb>

    @if($emailTemplate)
        <x-header
            title="Sửa {{ $this->humanizeTemplateType($template_type) }}"
            class="pb-3 mb-5! border-b border-gray-300"
        >
            <x-slot:actions>
                <div class="flex flex-wrap justify-end gap-2">
                    <x-toggle
                        label="Kích Hoạt Template"
                        wire:model.live="is_active"
                        class="toggle-primary mt-2"
                        hint="Bật/tắt việc hệ thống có gửi email này không."
                        :disabled="!$this->canToggleActive"
                    />
                    <x-button
                        label="Khôi Phục Mặc Định"
                        icon="o-arrow-path"
                        wire:click="resetToDefault"
                        class="btn-ghost"
                        spinner="resetToDefault"
                    />
                </div>
            </x-slot:actions>
        </x-header>

        <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]! mb-0!">
            @php
                $editableFields = $this->editableFields;
            @endphp
            <x-card class="space-y-6 xl:col-span-9 pb-0!">
                <div x-data="{ open: true }"
                     class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden mb-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                        <button type="button"
                                class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition"
                                @click="open = !open">Thông tin email
                            template: {{ $this->humanizeTemplateType($template_type) }}</button>
                        <div class="flex items-center gap-1">
                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform"
                                    x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                        </div>
                    </div>
                    <div x-show="open" x-collapse class="p-4 pt-0 bg-white border-t border-gray-100 text-md">
                        <x-input
                            label="Tiêu Đề Email"
                            wire:model.blur="subject" required
                        />

                        <x-textarea
                            label="Mô Tả"
                            wire:model.blur="description"
                            rows="2"
                        />
                    </div>
                </div>
                <div x-data="{ open: true }"
                     class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                        <button type="button"
                                class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition"
                                @click="open = !open">Nội dung email
                        </button>
                        <div class="flex items-center gap-1">
                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform"
                                    x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                        </div>
                    </div>
                    <div x-show="open" x-collapse class="p-4 bg-white border-t border-gray-100">
                        <div class="space-y-2 px-3 py-2 text-[16px] leading-8 text-slate-700">
                            {{--                            @dd($editableFields)--}}
                            @if (empty($editableFields))
                                <div
                                    class="rounded-xl border border-dashed border-base-300 p-8 text-center text-base-content/60">
                                    Template này chưa có nội dung để hiển thị.
                                </div>
                            @else
                                @foreach ($editableFields as $field)
                                    @php
                                        $key = $field['key'];
                                        $rawValue = $this->blockValue($key);
                                        $value = $this->renderPreviewText($rawValue);
                                        $renderType = $this->fieldRenderType($field);
                                    @endphp

                                    @if ($renderType === 'button')
                                        <div
                                            wire:key="preview-button-{{ $template_type }}-{{ $key }}"
                                            class="group relative flex justify-center rounded-xl border border-transparent px-3 py-2 transition hover:border-primary/40 hover:bg-primary/5"
                                        >
                                            <button
                                                type="button"
                                                wire:click="editBlock(@js($key))"
                                                class="inline-flex min-w-[280px] justify-center rounded-lg bg-warning px-8 py-3 text-center text-base font-bold text-white shadow-sm transition"
                                            >
                                                {{ $value ?: 'Nút Hành Động' }}
                                            </button>

                                            <x-button
                                                icon="o-pencil-square"
                                                wire:click.prevent="editBlock('{{ $key }}')"
                                                spinner
                                                class="btn-circle btn-sm btn-primary absolute right-2 top-2 z-10 hidden shadow-lg transition group-hover:inline-flex"
                                                title="Sửa đoạn này"
                                            />
                                        </div>
                                    @elseif ($renderType === 'note')
                                        <div
                                            wire:key="preview-note-{{ $template_type }}-{{ $key }}"
                                            class="group relative rounded-xl border-l-4 border-blue-500 bg-slate-50 px-7 py-3 transition hover:bg-blue-50/70"
                                        >
                                            <x-button
                                                icon="o-pencil-square"
                                                wire:click.prevent="editBlock('{{ $key }}')"
                                                spinner
                                                class="btn-circle btn-sm btn-primary absolute right-2 top-2 z-10 hidden shadow-lg transition group-hover:inline-flex"
                                                title="Sửa đoạn này"
                                            />

                                            {{--                                            <div class="font-bold text-blue-700">--}}
                                            {{--                                                {{ $field['label'] ?? 'Lưu Ý' }}--}}
                                            {{--                                            </div>--}}

                                            <div class="text-slate-700">
                                                @foreach(explode("\n", $value ?? 'Chưa có nội dung.') as $p)
                                                    @if(trim($p))
                                                        <p>{{ $p }}</p>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif ($renderType === 'signature')
                                        <div
                                            wire:key="preview-signature-{{ $template_type }}-{{ $key }}"
                                            class="group relative rounded-xl border border-transparent px-3 py-2 transition hover:border-primary/40 hover:bg-primary/5"
                                        >
                                            <x-button
                                                icon="o-pencil-square"
                                                wire:click.prevent="editBlock('{{ $key }}')"
                                                spinner
                                                class="btn-circle btn-sm btn-primary absolute right-2 top-2 z-10 hidden shadow-lg transition group-hover:inline-flex"
                                                title="Sửa đoạn này"
                                            />

                                            <div class="text-slate-700">
                                                Trân Trọng,
                                            </div>

                                            <div class="font-bold text-blue-700">
                                                {{ $value ?: 'Ban Quản Trị Website FITA VNUA' }}
                                            </div>
                                        </div>
                                    @elseif ($renderType === 'heading')
                                        <div
                                            wire:key="preview-heading-{{ $template_type }}-{{ $key }}"
                                            class="group relative rounded-xl border border-transparent px-3 py-2 transition hover:border-primary/40 hover:bg-primary/5"
                                        >
                                            <x-button
                                                icon="o-pencil-square"
                                                wire:click.prevent="editBlock('{{ $key }}')"
                                                spinner
                                                class="btn-circle btn-sm btn-primary absolute right-2 top-2 z-10 hidden shadow-lg transition group-hover:inline-flex"
                                                title="Sửa đoạn này"
                                            />

                                            <h1 class="pr-12 text-2xl font-semibold leading-9 text-slate-900">
                                                {{ $value ?:  'Tiêu Đề' }}
                                            </h1>
                                        </div>
                                    @else
                                        <div
                                            wire:key="preview-paragraph-{{ $template_type }}-{{ $key }}"
                                            class="group relative rounded-xl border border-transparent px-3 transition hover:border-primary/40 hover:bg-primary/5"
                                        >
                                            <x-button
                                                icon="o-pencil-square"
                                                wire:click.prevent="editBlock('{{ $key }}')"
                                                spinner
                                                class="btn-circle btn-sm btn-primary absolute right-2 top-0 z-10 hidden shadow-lg transition group-hover:inline-flex"
                                                title="Sửa đoạn này"
                                            />

                                            <p class="pr-12">
                                                {{ $value ?: 'Chưa có nội dung.' }}
                                            </p>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>

                        <div class="border-t border-slate-200 bg-slate-50 px-8 py-5 text-center text-sm text-slate-500">
                            © {{ now()->year }} Khoa Công Nghệ Thông Tin - Học Viện Nông Nghiệp Việt Nam.
                        </div>
                    </div>
                </div>
            </x-card>
            <div class="space-y-4 xl:col-span-3 sticky top-22 self-start">
                <x-card class="z-5 pb-0!" title="{{__('Action')}}" shadow separator progress-indicator="save">
                    <x-button label="Lưu cấu hình" class="bg-primary text-white my-1 w-full" wire:click="save"
                              wire:loading.attr="disabled" wire:target="save" spinner/>
                    <x-button label="Xem trước" wire:click="preview" wire:loading.attr="disabled" wire:target="preview"
                              class="bg-success text-white my-1 w-full" spinner/>
                </x-card>
                <x-card class="z-2 pb-0!" title="Các Vùng Có Thể Sửa" shadow separator>
                    <div class="space-y-2 max-h-[38vh] overflow-y-auto pr-1">
                        @if (empty($editableFields))
                            <p class="text-sm text-base-content/60">
                                Template này chưa có field để chỉnh sửa.
                            </p>
                        @else
                            @foreach ($editableFields as $field)
                                {{--                                <button--}}
                                {{--                                    type="button"--}}
                                {{--                                    wire:key="field-shortcut-{{ $template_type }}-{{ $field['key'] }}"--}}
                                {{--                                    wire:click="editBlock(@js($field['key']))"--}}
                                {{--                                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-base-300 px-3 py-2 text-left text-sm transition hover:border-primary hover:bg-primary/5"--}}
                                {{--                                >--}}
                                {{--                                    <span>--}}
                                {{--                                        {{ $field['label'] ?? $field['key'] }}--}}
                                {{--                                    </span>--}}

                                {{--                                    <x-icon name="o-pencil-square" class="h-4 w-4 text-primary" />--}}
                                {{--                                </button>--}}
                                <x-button
                                    wire:key="field-shortcut-{{ $template_type }}-{{ $field['key'] }}"
                                    wire:click.prevent="editBlock('{{ $field['key'] }}')"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-base-300 px-3 py-2 text-left text-sm transition btn-ghost text-primary hover:border-primary hover:bg-primary/5"
                                    spinner="editBlock('{{ $field['key'] }}')"
                                    icon-right="o-pencil-square"
                                >
                                    <span>
                                        {{ $field['label'] ?? $field['key'] }}
                                    </span>
                                </x-button>
                            @endforeach
                        @endif
                    </div>
                </x-card>
            </div>
        </div>

        <!-- Preview Email Modal -->
        <x-modal
            wire:model="showPreviewModal"
            title="Xem Trước Email"
            class="modalAddSubject"
            separator
        >
            <div class="space-y-4 max-h-[70vh] overflow-y-auto">
                <div class="bg-gray-50 mb-3">
                    <div class="px-4 py-1">
                        <span class="text-md font-semibold text-gray-500">Loại Email: </span>
                        <span
                            class="text-md font-semibold text-gray-900"> {{ $this->humanizeTemplateType($template_type) }}</span>
                    </div>

                    <div class="px-4 py-1">
                        <span class="text-md font-semibold text-gray-500">Tiêu Đề: </span>
                        <span
                            class="text-md font-semibold text-gray-900"> {{ $previewSubject ?: 'Chưa có tiêu đề email' }}</span>
                    </div>
                </div>
                @if($previewHtml)
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <iframe
                            wire:key="email-template-preview-{{ $emailTemplate?->id }}-{{ md5($previewHtml) }}"
                            title="Email preview"
                            class="h-[57vh] w-full bg-white"
                            sandbox
                            srcdoc="{!! e($previewHtml) !!}"
                        ></iframe>
                    </div>
                @else
                    <div
                        class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                        Chưa có nội dung xem trước.
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Đóng" wire:click="$wire.showPreviewModal = false" class="btn-ghost"/>
            </x-slot:actions>
        </x-modal>

        <!-- Edit Block Modal -->
        <x-modal
            wire:model="showEditBlockModal"
            title="Sửa Nội Dung Email: {{ $editingBlockLabel }}"
            separator
            class="modalAddSubject"
        >
            <div
                x-data="emailTemplateBlockEditor(@entangle('editingBlockValue').live)"
                x-init="init()"
                class="space-y-4 max-h-[70vh] overflow-y-auto"
            >
                <div class="grid gap-4 lg:grid-cols-12">
                    <!-- Cột trái -->
                    <div class="space-y-3 lg:col-span-7">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Nội Dung
                            </label>

                            <div class="relative ms-1">
                                <pre
                                    x-ref="highlight"
                                    x-html="highlightedValue"
                                    class="pointer-events-none absolute inset-0 min-h-[280px] overflow-auto whitespace-pre-wrap break-words rounded-xl border border-base-300 bg-white px-4 py-3 font-mono text-sm leading-6 text-slate-800"
                                ></pre>

                                <textarea
                                    x-ref="editor"
                                    x-model="value"
                                    rows="{{ $editingBlockType === 'textarea' ? 10 : 7 }}"
                                    class="textarea textarea-bordered relative z-10 min-h-[280px] w-full resize-y bg-transparent px-4 py-3 font-mono text-sm leading-6 text-transparent caret-slate-900 selection:bg-primary/20"
                                    placeholder="Nhập nội dung email..."
                                    spellcheck="false"
                                    x-on:keydown="handleKeydown($event)"
                                    x-on:input="refreshHighlight()"
                                    x-on:scroll="$refs.highlight.scrollTop = $refs.editor.scrollTop; $refs.highlight.scrollLeft = $refs.editor.scrollLeft"
                                ></textarea>
                            </div>
                        </div>

                        <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-slate-700">
                            <div class="font-semibold text-warning">
                                Lưu ý
                            </div>

                            <div class="mt-1">
                                Bấm vào biến ở cột bên phải để chèn vào vị trí con trỏ trong ô nội dung.
                                Sau khi cập nhật, nội dung chỉ mới đổi tạm trên giao diện. Bạn vẫn cần bấm
                                <span class="font-semibold">Lưu cấu hình</span> để lưu lại.
                            </div>
                        </div>
                    </div>

                    <!-- Cột phải -->
                    <div class="lg:col-span-5">
                        <div class="rounded-xl border border-info/30 bg-info/5">
                            <div class="border-b border-info/20 px-4 py-3">
                                <p class="font-semibold text-info">
                                    Biến Có Thể Dùng
                                </p>

                                <p class="mt-1 text-xs text-slate-500">
                                    Click vào biến để chèn vào nội dung bên trái.
                                </p>
                            </div>

                            <div class="max-h-[55vh] space-y-2 overflow-y-auto p-3">
                                @foreach ($this->availableVariables as $variable)
                                    <button
                                        type="button"
                                        class="group w-full rounded-xl border border-base-300 bg-white px-3 py-2 text-left transition hover:border-primary hover:bg-primary/5"
                                        data-insert64="{{ base64_encode($variable['insert']) }}"
                                        x-on:click="insertVariableFromBase64($el.dataset.insert64)"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                    <span class="font-mono text-sm font-semibold text-primary">
                                        {{ $variable['name'] }}
                                    </span>

                                            <x-icon
                                                name="o-plus-circle"
                                                class="h-4 w-4 shrink-0 text-primary opacity-70 transition group-hover:opacity-100"
                                            />
                                        </div>

                                        <div class="mt-1 text-xs leading-5 text-slate-600">
                                            {{ $variable['description'] }}
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <x-button
                    label="Hủy"
                    wire:click="$wire.showEditBlockModal = false"
                    class="btn-ghost"
                />

                <x-button
                    label="Cập Nhật Đoạn Này"
                    icon="o-check"
                    wire:click="saveEditingBlock"
                    class="btn-primary"
                />
            </x-slot:actions>
        </x-modal>

        @script
        <script>
            Alpine.data('emailTemplateBlockEditor', (entangledValue) => ({
                value: entangledValue,
                highlightedValue: '',

                init() {
                    this.refreshHighlight();

                    this.$watch('value', () => {
                        this.refreshHighlight();
                    });
                },

                refreshHighlight() {
                    this.highlightedValue = this.buildHighlightedHtml(this.value || '');
                },

                insertVariableFromBase64(encodedText) {
                    this.insertVariable(this.decodeBase64(encodedText));
                },

                decodeBase64(encodedText) {
                    try {
                        return decodeURIComponent(escape(window.atob(encodedText)));
                    } catch (e) {
                        return window.atob(encodedText);
                    }
                },

                insertVariable(insertText) {
                    const editor = this.$refs.editor;

                    if (!editor) {
                        return;
                    }

                    const currentValue = this.value || '';

                    const start = editor.selectionStart ?? currentValue.length;
                    const end = editor.selectionEnd ?? currentValue.length;

                    const before = currentValue.slice(0, start);
                    const after = currentValue.slice(end);

                    const needSpaceBefore = before.length > 0 && !/\s$/.test(before) ? ' ' : '';
                    const needSpaceAfter = after.length > 0 && !/^\s/.test(after) ? ' ' : '';

                    const textToInsert = needSpaceBefore + insertText + needSpaceAfter;

                    this.value = before + textToInsert + after;
                    this.refreshHighlight();

                    this.$nextTick(() => {
                        const cursorPosition = before.length + textToInsert.length;

                        editor.focus();
                        editor.setSelectionRange(cursorPosition, cursorPosition);

                        this.syncScroll();
                    });
                },

                handleKeydown(event) {
                    if (!['Backspace', 'Delete'].includes(event.key)) {
                        return;
                    }

                    const editor = this.$refs.editor;

                    if (!editor) {
                        return;
                    }

                    const currentValue = this.value || '';
                    const start = editor.selectionStart ?? 0;
                    const end = editor.selectionEnd ?? start;

                    let range = null;

                    if (start !== end) {
                        range = this.expandSelectionToFullVariables(currentValue, start, end);
                    } else {
                        range = this.findVariableRangeForDelete(currentValue, start, event.key);
                    }

                    if (!range) {
                        return;
                    }

                    event.preventDefault();

                    this.deleteTextRange(range.start, range.end);
                },

                findVariableRangeForDelete(text, cursorPosition, key) {
                    const regex = /\{\{[\s\S]*?\}\}/g;
                    let match;

                    while ((match = regex.exec(text)) !== null) {
                        const start = match.index;
                        const end = start + match[0].length;

                        if (key === 'Backspace') {
                            // Cursor nằm trong biến hoặc ngay sau biến
                            if (cursorPosition > start && cursorPosition <= end) {
                                return { start, end };
                            }
                        }

                        if (key === 'Delete') {
                            // Cursor nằm trong biến hoặc ngay trước ký tự đầu của biến
                            if (cursorPosition >= start && cursorPosition < end) {
                                return { start, end };
                            }
                        }
                    }

                    return null;
                },

                expandSelectionToFullVariables(text, selectionStart, selectionEnd) {
                    const regex = /\{\{[\s\S]*?\}\}/g;
                    let match;

                    let newStart = selectionStart;
                    let newEnd = selectionEnd;
                    let hasTouchedVariable = false;

                    while ((match = regex.exec(text)) !== null) {
                        const start = match.index;
                        const end = start + match[0].length;

                        const isOverlap = start < selectionEnd && end > selectionStart;

                        if (isOverlap) {
                            hasTouchedVariable = true;
                            newStart = Math.min(newStart, start);
                            newEnd = Math.max(newEnd, end);
                        }
                    }

                    if (!hasTouchedVariable) {
                        return null;
                    }

                    return {
                        start: newStart,
                        end: newEnd,
                    };
                },

                deleteTextRange(start, end) {
                    const editor = this.$refs.editor;
                    const currentValue = this.value || '';

                    let before = currentValue.slice(0, start);
                    let after = currentValue.slice(end);

                    // Nếu sau khi xóa biến bị dư 2 khoảng trắng thì gom lại cho đẹp
                    if (/\s$/.test(before) && /^\s/.test(after)) {
                        after = after.replace(/^\s+/, '');
                    }

                    this.value = before + after;
                    this.refreshHighlight();

                    this.$nextTick(() => {
                        const cursorPosition = before.length;

                        editor.focus();
                        editor.setSelectionRange(cursorPosition, cursorPosition);

                        this.syncScroll();
                    });
                },

                syncScroll() {
                    const editor = this.$refs.editor;
                    const highlight = this.$refs.highlight;

                    if (!editor || !highlight) {
                        return;
                    }

                    highlight.scrollTop = editor.scrollTop;
                    highlight.scrollLeft = editor.scrollLeft;
                },

                buildHighlightedHtml(text) {
                    const escaped = this.escapeHtml(text);

                    return escaped
                        .replace(
                            /(\{\{[\s\S]*?\}\})/g,
                            '<mark class="rounded bg-warning/30 px-0 py-0.5 font-semibold text-slate-950">$1</mark>'
                        )
                        .replace(/\n$/g, '\n ');
                },

                escapeHtml(text) {
                    return String(text)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                },
            }));
        </script>
        @endscript

    @else
        <x-card title="Email Template Không Tìm Thấy" class="border border-error">
            <p class="text-error">Email template không tồn tại trong hệ thống.</p>

            <x-button
                label="Quay Lại Danh Sách"
                link="{{ route('admin.email-template.index') }}"
                class="btn-primary mt-4"
            />
        </x-card>
    @endif
</div>
