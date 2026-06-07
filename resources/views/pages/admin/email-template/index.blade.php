 <?php

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new
#[Layout('layouts.app')]
class extends Component {
    use WithPagination, Toast;

    public string $search = '';
    public array $sortBy = ['column' => 'template_type', 'direction' => 'asc'];
    public int $perPage = 10;

    public bool $showPreviewModal = false;
    public ?EmailTemplate $previewTemplate = null;
    public string $previewHtml = '';
    public string $previewSubject = '';
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-5 p-2! text-center'],
            ['key' => 'template_type_label', 'label' => 'Loại Email', 'sortable' => false],
            ['key' => 'description', 'label' => 'Mô Tả','class' => 'w-80', 'sortable' => false],
            ['key' => 'is_active', 'label' => 'Trạng Thái', 'sortable' => true, 'class' => 'w-24'],
            ['key' => 'updated_at', 'label' => 'Cập Nhật', 'sortable' => true, 'class' => 'w-40'],
            ['key' => 'actions', 'label' => 'Hành Động', 'sortable' => false, 'class' => 'w-32 pe-6'],
        ];
    }

    public function getEmailTemplatesProperty()
    {
        $query = EmailTemplate::query();

        if (trim($this->search) !== '') {
            $this->applySearchFilter($query, trim($this->search));
        }

        $allowedSortColumns = ['id', 'template_type', 'is_active', 'updated_at'];
        $column = $this->sortBy['column'] ?? 'template_type';
        $direction = strtolower($this->sortBy['direction'] ?? 'asc');

        if (! in_array($column, $allowedSortColumns, true)) {
            $column = 'template_type';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return $query
            ->orderBy($column, $direction)
            ->paginate($this->perPage);
    }

    protected function applySearchFilter($query, string $search): void
    {
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

            $query->where(function ($q) use ($keyword) {
                $q->where('template_type', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }
    }

    public function openPreview(EmailTemplate $template): void
    {
        $this->previewTemplate = $template;

        try {
            $sampleData = $this->sampleDataForPreview($template->template_type);

            $rendered = EmailTemplateService::render(
                $template->template_type,
                $sampleData,
                $template->content_blocks
            );

            $this->previewSubject = $rendered['subject'] ?? $template->subject ?? '';

            $this->previewHtml = view('emails.raw_html', [
                'html' => $rendered['body'],
            ])->render();

            $this->showPreviewModal = true;
        } catch (\Throwable $e) {
            $this->error('Lỗi xem trước: ' . $e->getMessage());
        }
    }

    public function toggleActive(int $id): void
    {
        $template = EmailTemplate::find($id);

        if (!$template) {
            $this->error('Template không tìm thấy');
            return;
        }

        $template->update(['is_active' => !$template->is_active]);
        $this->success($template->is_active ? 'Template đã được kích hoạt' : 'Template đã bị tắt');
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
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

    private function sampleDataForPreview(string $templateType): array
    {
        $data = match ($templateType) {
            'first_time_password_setup' => [
                'user' => (object) ['name' => 'Nguyễn Văn A'],
                'setupUrl' => 'https://example.com/setup-password',
                'actionUrl' => 'https://example.com/setup-password',
                'expiresInHuman' => '24 giờ',
                'systemEmail' => 'noreply@fita.edu.vn',
            ],

            'password_reset' => [
                'user' => (object) ['name' => 'Nguyễn Văn A'],
                'resetUrl' => 'https://example.com/reset-password',
                'actionUrl' => 'https://example.com/reset-password',
                'expiresInHuman' => '60 phút',
                'systemEmail' => 'noreply@fita.edu.vn',
            ],

            'post_status_submitted' => [
                'recipientName' => 'Quản trị hệ thống',
                'postTitle' => 'Bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Nguyễn Văn A',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/review',
                'action' => 'submitted',
                'actionLabel' => 'Bài viết chờ duyệt',
                'note' => null,
                'scheduledPublishAt' => null,
            ],

            'post_status_reverted_to_pending' => [
                'recipientName' => 'Người duyệt bài',
                'postTitle' => 'Bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/review',
                'action' => 'reverted_to_pending',
                'actionLabel' => 'Nhắc duyệt lại bài viết',
                'note' => null,
                'scheduledPublishAt' => null,
            ],

            'post_status_reverted_to_pending_author' => [
                'recipientName' => 'Nguyễn Văn A',
                'postTitle' => 'Bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/edit',
                'action' => 'reverted_to_pending_author',
                'actionLabel' => 'Bài viết đã bị gỡ và chờ duyệt lại',
                'note' => null,
                'scheduledPublishAt' => null,
            ],

            'post_status_approved_published' => [
                'recipientName' => 'Nguyễn Văn A',
                'postTitle' => 'Bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/posts/1',
                'action' => 'approved',
                'actionLabel' => 'Bài viết đã được duyệt',
                'note' => null,
                'scheduledPublishAt' => now()->addDay()->toDateTimeString()
            ],

            'post_status_rejected' => [
                'recipientName' => 'Nguyễn Văn A',
                'postTitle' => 'Bài viết mẫu',
                'categoryNames' => ['Tin tức', 'Sự kiện'],
                'actorName' => 'Admin',
                'postUrl' => 'https://example.com/posts/1',
                'editUrl' => 'https://example.com/admin/posts/1/edit',
                'reviewUrl' => 'https://example.com/admin/posts/1/review',
                'actionUrl' => 'https://example.com/admin/posts/1/edit',
                'action' => 'rejected',
                'actionLabel' => 'Bài viết bị từ chối',
                'note' => 'Nội dung bài viết chưa phù hợp, vui lòng chỉnh sửa và gửi duyệt lại.',
                'scheduledPublishAt' => null,
            ],

            default => [],
        };

        return $data;
    }
};
?>

<div>
    <x-slot:title>Quản Lý Email Template</x-slot:title>

    <x-slot:breadcrumb>
        Quản Lý Email Template
    </x-slot:breadcrumb>

    <x-header title="Danh Sách Email Template" class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <x-input
                icon="o-magnifying-glass"
                placeholder="Tìm kiếm..."
                wire:model.live.debounce.300ms="search"
                clearable="true"
                class="w-full lg:w-96"
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button icon="o-cog-6-tooth" class="btn-ghost" label="Cấu Hình" link="{{ route('admin.configuration.email') }}"/>
        </x-slot:actions>
    </x-header>

    <div class="shadow-md ring-1 ring-gray-200 rounded-md relative">
        <x-table
            :headers="$this->headers()"
            :rows="$this->emailTemplates"
            :sort-by="$this->sortBy"
            striped
            :per-page-values="[5, 10, 20, 50]"
            per-page="perPage"
            with-pagination
            class="
                bg-white
                [&_table]:border-collapse [&_table]:rounded-md [&_th]:text-left
                [&_th]:bg-white [&_th]:text-black! [&_th]:rounded-md [&_th]:hover:bg-gray-100/50
                [&_td]:text-black [&_td]:border-t [&_td]:border-gray-200 [&_td]:text-left
            "
        >
            @scope('cell_id', $template)
            {{ ($this->emailTemplates->currentPage() - 1) * $this->emailTemplates->perPage() + $loop->iteration }}
            @endscope

            @scope('cell_template_type_label', $template)
            <div class="font-medium">{{ $this->humanizeTemplateType($template->template_type) }}</div>
            @endscope

            @scope('cell_description', $template)
            <div class="text-sm text-gray-600 line-clamp-2">{{ $template->description ?: '—' }}</div>
            @endscope

            @scope('cell_is_active', $template)
            <div class="flex items-center gap-2">
                @if($template->is_active)
                    <x-badge value="Kích Hoạt" class="badge-success whitespace-nowrap text-white font-medium"/>
                @else
                    <x-badge value="Tắt" class="badge-warning whitespace-nowrap text-white font-medium"/>
                @endif
            </div>
            @endscope

            @scope('cell_updated_at', $template)
            <span class="text-sm text-gray-600">{{ $template->updated_at->format('d/m/Y H:i') }}</span>
            @endscope

            @scope('cell_actions', $template)
            <div class="flex gap-2">
                <x-button
                    icon="o-eye"
                    class="btn-xs btn-ghost text-info"
                    wire:click="openPreview({{ $template->id }})"
                    spinner="openPreview({{ $template->id }})"
                    tooltip="Xem Trước"
                />
                <x-button
                    icon="o-pencil"
                    class="btn-xs btn-ghost text-primary"
                    link="{{ route('admin.email-template.edit', $template->id) }}"
                    tooltip="Sửa"
                />
                <x-button
                    icon="{{ $template->is_active ? 'o-check-circle' : 'o-x-circle' }}"
                    class="btn-xs btn-ghost {{ $template->is_active ? 'text-success' : 'text-error' }}"
                    wire:click="toggleActive({{ $template->id }})"
                    spinner="toggleActive({{ $template->id }})"
                    tooltip="{{ $template->is_active ? 'Tắt' : 'Kích Hoạt' }}"
                    :hidden="!EmailTemplate::canDisable($template->template_type)"
                />
            </div>
            @endscope

            <x-slot:empty>
                <div class="text-center py-6">
                    <x-icon name="o-envelope" class="w-10 h-10 text-gray-400 mx-auto"/>
                    <p class="mt-2 text-gray-500">Không có email template nào.</p>
                </div>
            </x-slot:empty>
        </x-table>

        <div wire:loading.flex
             wire:target="search, sortBy, perPage"
             class="absolute inset-0 z-5 items-center justify-center bg-white/30 backdrop-blur-sm rounded-md transition-all duration-300">
            <div class="flex flex-col items-center gap-2 flex-1">
                <x-loading class="text-primary loading-lg"/>
                <span class="text-sm font-medium text-gray-500">Đang tải dữ liệu...</span>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <x-modal
        wire:model="showPreviewModal"
        title="Xem Trước Email"
        class="modalPreviewEmail"
        box-class="!w-[95vw] !max-w-5xl"
        separator
    >
        @if($previewTemplate)
            <div class="space-y-2 max-h-[70vh] overflow-y-auto">
                <div class="bg-gray-50 mb-3">
                    <div class="px-4 py-1">
                        <span class="text-md font-semibold text-gray-500">Loại Email: </span>
                        <span class="text-md font-semibold text-gray-900">{{ $this->humanizeTemplateType($previewTemplate->template_type) }}</span>
                    </div>

                    <div class="px-4 py-1">
                        <span class="text-md font-semibold text-gray-500">Tiêu Đề: </span>
                        <span class="text-md font-semibold text-gray-900">{{ $previewSubject }}</span>
                    </div>
                </div>

                @if($previewHtml)
                    <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                        <iframe
                            wire:key="email-preview-{{ $previewTemplate?->id }}-{{ md5($previewHtml) }}"
                            title="Email preview"
                            class="w-full  h-[57vh] bg-white"
                            sandbox
                            srcdoc="{!! e($previewHtml) !!}"
                        ></iframe>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-button label="Đóng" wire:click="$set('showPreviewModal', false)" class="btn-ghost"/>
        </x-slot:actions>
    </x-modal>
</div>

