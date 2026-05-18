<?php

use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use Toast, WithFileUploads;

    public string $selectedTab = 'tab-vi';
    public array $data = [];
    protected array $originalData = [];

    public array $popularIcons = [
        'o-academic-cap', 'o-adjustments-horizontal', 'o-adjustments-vertical', 'o-archive-box-arrow-down', 'o-archive-box-x-mark', 'o-archive-box', 'o-arrow-down-circle', 'o-arrow-down-left', 'o-arrow-down-on-square-stack', 'o-arrow-down-on-square', 'o-arrow-down-right', 'o-arrow-down-tray', 'o-arrow-down', 'o-arrow-left-circle', 'o-arrow-left-end-on-rectangle', 'o-arrow-left-start-on-rectangle', 'o-arrow-left', 'o-arrow-long-down', 'o-arrow-long-left', 'o-arrow-long-right', 'o-arrow-long-up', 'o-arrow-path-rounded-square', 'o-arrow-path', 'o-arrow-right-circle', 'o-arrow-right-end-on-rectangle', 'o-arrow-right-start-on-rectangle', 'o-arrow-right', 'o-arrow-top-right-on-square', 'o-arrow-trending-down', 'o-arrow-trending-up', 'o-arrow-turn-down-left', 'o-arrow-turn-down-right', 'o-arrow-turn-left-down', 'o-arrow-turn-left-up', 'o-arrow-turn-right-down', 'o-arrow-turn-right-up', 'o-arrow-turn-up-left', 'o-arrow-turn-up-right', 'o-arrow-up-circle', 'o-arrow-up-left', 'o-arrow-up-on-square-stack', 'o-arrow-up-on-square', 'o-arrow-up-right', 'o-arrow-up-tray', 'o-arrow-up', 'o-arrow-uturn-down', 'o-arrow-uturn-left', 'o-arrow-uturn-right', 'o-arrow-uturn-up',
        'o-arrows-pointing-in', 'o-arrows-pointing-out', 'o-arrows-right-left', 'o-arrows-up-down', 'o-at-symbol', 'o-backspace', 'o-backward', 'o-banknotes', 'o-bars-2', 'o-bars-3-bottom-left', 'o-bars-3-bottom-right', 'o-bars-3-center-left', 'o-bars-3', 'o-bars-4', 'o-bars-arrow-down', 'o-bars-arrow-up', 'o-battery-0', 'o-battery-100', 'o-battery-50', 'o-beaker', 'o-bell-alert', 'o-bell-slash', 'o-bell-snooze', 'o-bell', 'o-bold', 'o-bolt-slash', 'o-bolt', 'o-book-open', 'o-bookmark-slash', 'o-bookmark-square', 'o-bookmark', 'o-briefcase', 'o-bug-ant', 'o-building-library', 'o-building-office-2', 'o-building-office', 'o-building-storefront', 'o-cake', 'o-calculator', 'o-calendar-date-range', 'o-calendar-days', 'o-calendar', 'o-camera', 'o-chart-bar-square', 'o-chart-bar', 'o-chart-pie', 'o-chat-bubble-bottom-center-text', 'o-chat-bubble-bottom-center', 'o-chat-bubble-left-ellipsis', 'o-chat-bubble-left-right', 'o-chat-bubble-left', 'o-chat-bubble-oval-left-ellipsis', 'o-chat-bubble-oval-left', 'o-check-badge', 'o-check-circle', 'o-check',
        'o-chevron-double-down', 'o-chevron-double-left', 'o-chevron-double-right', 'o-chevron-double-up', 'o-chevron-down', 'o-chevron-left', 'o-chevron-right', 'o-chevron-up-down', 'o-chevron-up', 'o-circle-stack', 'o-clipboard-document-check', 'o-clipboard-document-list', 'o-clipboard-document', 'o-clipboard', 'o-clock', 'o-cloud-arrow-down', 'o-cloud-arrow-up', 'o-cloud', 'o-code-bracket-square', 'o-code-bracket', 'o-cog-6-tooth', 'o-cog-8-tooth', 'o-cog', 'o-command-line', 'o-computer-desktop', 'o-cpu-chip', 'o-credit-card', 'o-cube-transparent', 'o-cube', 'o-currency-bangladeshi', 'o-currency-dollar', 'o-currency-euro', 'o-currency-pound', 'o-currency-rupee', 'o-currency-yen', 'o-cursor-arrow-rays', 'o-cursor-arrow-ripple', 'o-device-phone-mobile', 'o-device-tablet', 'o-divide', 'o-document-arrow-down', 'o-document-arrow-up', 'o-document-chart-bar', 'o-document-check', 'o-document-currency-bangladeshi', 'o-document-currency-dollar', 'o-document-currency-euro', 'o-document-currency-pound', 'o-document-currency-rupee', 'o-document-currency-yen',
        'o-document-duplicate', 'o-document-magnifying-glass', 'o-document-minus', 'o-document-plus', 'o-document-text', 'o-document', 'o-ellipsis-horizontal-circle', 'o-ellipsis-horizontal', 'o-ellipsis-vertical', 'o-envelope-open', 'o-envelope', 'o-equals', 'o-exclamation-circle', 'o-exclamation-triangle', 'o-eye-dropper', 'o-eye-slash', 'o-eye', 'o-face-frown', 'o-face-smile', 'o-film', 'o-finger-print', 'o-fire', 'o-flag', 'o-folder-arrow-down', 'o-folder-minus', 'o-folder-open', 'o-folder-plus', 'o-folder', 'o-forward', 'o-funnel', 'o-gif', 'o-gift-top', 'o-gift', 'o-globe-alt', 'o-globe-americas', 'o-globe-asia-australia', 'o-globe-europe-africa', 'o-h1', 'o-h2', 'o-h3', 'o-hand-raised', 'o-hand-thumb-down', 'o-hand-thumb-up', 'o-hashtag', 'o-heart', 'o-home-modern', 'o-home', 'o-identification', 'o-inbox-arrow-down', 'o-inbox-stack', 'o-inbox', 'o-information-circle', 'o-italic', 'o-key', 'o-language', 'o-lifebuoy', 'o-light-bulb', 'o-link-slash', 'o-link', 'o-list-bullet', 'o-lock-closed', 'o-lock-open',
        'o-magnifying-glass-circle', 'o-magnifying-glass-minus', 'o-magnifying-glass-plus', 'o-magnifying-glass', 'o-map-pin', 'o-map', 'o-megaphone', 'o-microphone', 'o-minus-circle', 'o-minus', 'o-moon', 'o-musical-note', 'o-newspaper', 'o-no-symbol', 'o-numbered-list', 'o-paint-brush', 'o-paper-airplane', 'o-paper-clip', 'o-pause-circle', 'o-pause', 'o-pencil-square', 'o-pencil', 'o-percent-badge', 'o-phone-arrow-down-left', 'o-phone-arrow-up-right', 'o-phone-x-mark', 'o-phone', 'o-photo', 'o-play-circle', 'o-play-pause', 'o-play', 'o-plus-circle', 'o-plus', 'o-power', 'o-presentation-chart-bar', 'o-presentation-chart-line', 'o-printer', 'o-puzzle-piece', 'o-qr-code', 'o-question-mark-circle', 'o-queue-list', 'o-radio', 'o-receipt-percent', 'o-receipt-refund', 'o-rectangle-group', 'o-rectangle-stack', 'o-rocket-launch', 'o-rss', 'o-scale', 'o-scissors', 'o-server-stack', 'o-server', 'o-share', 'o-shield-check', 'o-shield-exclamation', 'o-shopping-bag', 'o-shopping-cart', 'o-signal-slash', 'o-signal', 'o-slash',
        'o-sparkles', 'o-speaker-wave', 'o-speaker-x-mark', 'o-square-2-stack', 'o-square-3-stack-3d', 'o-squares-2x2', 'o-squares-plus', 'o-star', 'o-stop-circle', 'o-stop', 'o-strikethrough', 'o-sun', 'o-swatch', 'o-table-cells', 'o-tag', 'o-ticket', 'o-trash', 'o-trophy', 'o-truck', 'o-tv', 'o-underline', 'o-user-circle', 'o-user-group', 'o-user-minus', 'o-user-plus', 'o-user', 'o-users', 'o-variable', 'o-video-camera-slash', 'o-video-camera', 'o-view-columns', 'o-viewfinder-circle', 'o-wallet', 'o-wifi', 'o-window', 'o-wrench-screwdriver', 'o-wrench', 'o-x-circle', 'o-x-mark'
    ];

    protected $listeners = [
        'confirmSave' => 'confirmSave',
        'confirmRemoveQuickLink' => 'confirmRemoveQuickLink',
        'confirmRemoveTrainingProgram' => 'confirmRemoveTrainingProgram',
        'confirmRemoveCounterStat' => 'confirmRemoveCounterStat',
        'confirmRemoveTestimonial' => 'confirmRemoveTestimonial',
    ];

    protected function isUpload($value): bool
    {
        return $value instanceof UploadedFile
            || (is_object($value) && method_exists($value, 'temporaryUrl'));
    }

    protected function resolveMediaUrl(?string $path, string $fallback = ''): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return $fallback;
        }

        if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        return asset($path);
    }

    protected function displayMedia($uploadField, ?string $pathField, string $fallback = ''): string
    {
        if (!empty($uploadField) && $this->isUpload($uploadField)) {
            return $uploadField->temporaryUrl();
        }

        return $this->resolveMediaUrl($pathField, $fallback);
    }

    protected function storeMedia($value, string $directory): ?string
    {
        if ($this->isUpload($value)) {
            return $value->store($directory, 'public');
        }

        return is_string($value) ? trim($value) : null;
    }

    protected function ensureUploadFields(array $items, array $fields): array
    {
        return array_map(function ($item) use ($fields) {
            foreach ($fields as $field) {
                $item[$field] = $item[$field] ?? null;
            }

            return $item;
        }, $items);
    }

    protected function previewCacheKey(): string
    {
        return 'preview_home3_data_' . (auth()->id() ?? 'guest');
    }

    protected function preparePreviewData(): array
    {
        $previewData = $this->data;

        foreach (['vi', 'en'] as $locale) {
            foreach ($previewData[$locale]['quick_links'] ?? [] as $index => $item) {
                if (!empty($item['img_file']) && $this->isUpload($item['img_file'])) {
                    $previewData[$locale]['quick_links'][$index]['img'] = $item['img_file']->temporaryUrl();
                }
                $previewData[$locale]['quick_links'][$index]['img_file'] = null;
            }

            foreach ($previewData[$locale]['training_programs'] ?? [] as $index => $item) {
                if (!empty($item['image_file']) && $this->isUpload($item['image_file'])) {
                    $previewData[$locale]['training_programs'][$index]['image'] = $item['image_file']->temporaryUrl();
                }
                $previewData[$locale]['training_programs'][$index]['image_file'] = null;
            }

            foreach ($previewData[$locale]['testimonials'] ?? [] as $index => $item) {
                if (!empty($item['avatar_file']) && $this->isUpload($item['avatar_file'])) {
                    $previewData[$locale]['testimonials'][$index]['avatar'] = $item['avatar_file']->temporaryUrl();
                }
                $previewData[$locale]['testimonials'][$index]['avatar_file'] = null;
            }
        }

        return $previewData;
    }

    protected function syncToPreviewCache(): void
    {
        Cache::put($this->previewCacheKey(), $this->preparePreviewData(), now()->addMinutes(15));
    }

    protected function normalizeStoredMediaPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            $path = ltrim(substr($path, 9), '/');
        }

        if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:')) {
            return null;
        }

        return $path;
    }

    protected function deleteStoredMedia(?string $path): void
    {
        $normalized = $this->normalizeStoredMediaPath($path);

        if ($normalized && Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }

    protected function isMediaPathStillReferenced(string $path, ?string $excludeLocale = null, ?string $excludeSection = null, ?string $excludeId = null, ?string $excludeField = null): bool
    {
        $normalized = $this->normalizeStoredMediaPath($path);

        if (!$normalized) {
            return false;
        }

        $sections = [
            'quick_links' => 'img',
            'training_programs' => 'image',
            'testimonials' => 'avatar',
        ];

        foreach (['vi', 'en'] as $locale) {
            foreach ($sections as $section => $field) {
                foreach ($this->data[$locale][$section] ?? [] as $item) {
                    if (
                        $locale === $excludeLocale &&
                        $section === $excludeSection &&
                        ($item['id'] ?? null) === $excludeId &&
                        $field === $excludeField
                    ) {
                        continue;
                    }

                    if ($this->normalizeStoredMediaPath($item[$field] ?? null) === $normalized) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function cleanupReplacedMedia(array $originalData): void
    {
        $sections = [
            'quick_links' => 'img',
            'training_programs' => 'image',
            'testimonials' => 'avatar',
        ];

        foreach (['vi', 'en'] as $locale) {
            foreach ($sections as $section => $field) {
                $currentById = collect($this->data[$locale][$section] ?? [])->keyBy('id');
                foreach ($originalData[$locale][$section] ?? [] as $originalItem) {
                    $id = $originalItem['id'] ?? null;
                    if (!$id || !$currentById->has($id)) {
                        continue;
                    }

                    $currentItem = $currentById->get($id);
                    $oldPath = $originalItem[$field] ?? null;
                    $newPath = $currentItem[$field] ?? null;

                    if ($oldPath && $oldPath !== $newPath && !$this->isMediaPathStillReferenced($oldPath, $locale, $section, $id, $field)) {
                        $this->deleteStoredMedia($oldPath);
                    }
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'data.vi.section_titles.news' => 'nullable|string|max:255',
            'data.vi.section_titles.training' => 'nullable|string|max:255',
            'data.vi.section_titles.partners' => 'nullable|string|max:255',
            'data.vi.section_titles.testimonials' => 'nullable|string|max:255',
            'data.vi.section_titles.gallery' => 'nullable|string|max:255',
            'data.en.section_titles.news' => 'nullable|string|max:255',
            'data.en.section_titles.training' => 'nullable|string|max:255',
            'data.en.section_titles.partners' => 'nullable|string|max:255',
            'data.en.section_titles.testimonials' => 'nullable|string|max:255',
            'data.en.section_titles.gallery' => 'nullable|string|max:255',

            'data.vi.quick_links' => 'array',
            'data.vi.quick_links.*.app' => 'required|string|max:255',
            'data.vi.quick_links.*.desc' => 'required|string|max:255',
            'data.vi.quick_links.*.link' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
            'data.vi.quick_links.*.color' => 'required|string|max:50',
            'data.vi.quick_links.*.img' => 'nullable|string|max:255',
            'data.vi.quick_links.*.img_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'data.en.quick_links' => 'array',
            'data.en.quick_links.*.app' => 'required|string|max:255',
            'data.en.quick_links.*.desc' => 'required|string|max:255',
            'data.en.quick_links.*.link' => ['required', 'string', 'max:255', 'regex:/^(https?:\/\/|\/|#).+/i'],
            'data.en.quick_links.*.color' => 'required|string|max:50',
            'data.en.quick_links.*.img' => 'nullable|string|max:255',
            'data.en.quick_links.*.img_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'data.vi.training_programs' => 'array',
            'data.vi.training_programs.*.title' => 'required|string|max:255',
            'data.vi.training_programs.*.description' => 'required|string',
            'data.vi.training_programs.*.detail_url' => 'nullable|string|max:255',
            'data.vi.training_programs.*.roadmap_url' => 'nullable|string|max:255',
            'data.vi.training_programs.*.image' => 'nullable|string|max:255',
            'data.vi.training_programs.*.image_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'data.en.training_programs' => 'array',
            'data.en.training_programs.*.title' => 'required|string|max:255',
            'data.en.training_programs.*.description' => 'required|string',
            'data.en.training_programs.*.detail_url' => 'required|string|max:255',
            'data.en.training_programs.*.roadmap_url' => 'required|string|max:255',
            'data.en.training_programs.*.image' => 'nullable|string|max:255',
            'data.en.training_programs.*.image_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'data.vi.counter_stats' => 'array',
            'data.vi.counter_stats.*.label' => 'required|string|max:255',
            'data.vi.counter_stats.*.value' => 'required|integer|min:0',
            'data.vi.counter_stats.*.suffix' => 'required|string|max:5',
            'data.vi.counter_stats.*.icon' => 'required|string|max:255',

            'data.en.counter_stats' => 'array',
            'data.en.counter_stats.*.label' => 'required|string|max:255',
            'data.en.counter_stats.*.value' => 'required|integer|min:0',
            'data.en.counter_stats.*.suffix' => 'required|string|max:5',
            'data.en.counter_stats.*.icon' => 'required|string|max:255',

            'data.vi.testimonials' => 'array',
            'data.vi.testimonials.*.name' => 'required|string|max:255',
            'data.vi.testimonials.*.role' => 'required|string|max:255',
            'data.vi.testimonials.*.content' => 'required|string',
            'data.vi.testimonials.*.avatar' => 'nullable|string|max:255',
            'data.vi.testimonials.*.avatar_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'data.en.testimonials' => 'array',
            'data.en.testimonials.*.name' => 'required|string|max:255',
            'data.en.testimonials.*.role' => 'required|string|max:255',
            'data.en.testimonials.*.content' => 'required|string',
            'data.en.testimonials.*.avatar' => 'nullable|string|max:255',
            'data.en.testimonials.*.avatar_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    protected $messages = [
        // --- TIÊU ĐỀ CÁC KHỐI ---
        'data.*.section_titles.*.string' => 'Trường tiêu đề phải là chuỗi.',
        'data.*.section_titles.*.max'    => 'Trường tiêu đề không được vượt quá :max ký tự.',

        // --- LỐI TẮT NHANH (QUICK LINKS) ---
        'data.*.quick_links.*.app.required'   => 'Trường tiêu đề lối tắt là bắt buộc.',
        'data.*.quick_links.*.app.string'     => 'Trường tiêu đề lối tắt phải là chuỗi.',
        'data.*.quick_links.*.app.max'        => 'Trường tiêu đề lối tắt không được vượt quá :max ký tự.',
        'data.*.quick_links.*.desc.required'  => 'Trường mô tả là bắt buộc.',
        'data.*.quick_links.*.desc.string'    => 'Trường mô tả phải là chuỗi.',
        'data.*.quick_links.*.desc.max'       => 'Trường mô tả không được vượt quá :max ký tự.',
        'data.*.quick_links.*.link.required'  => 'Trường liên kết là bắt buộc.',
        'data.*.quick_links.*.link.string'    => 'Trường liên kết phải là chuỗi.',
        'data.*.quick_links.*.link.max'       => 'Trường liên kết không được vượt quá :max ký tự.',
        'data.*.quick_links.*.link.regex'     => 'Trường liên kết phải bắt đầu bằng http://, https://, / hoặc #.',
        'data.*.quick_links.*.color.required' => 'Trường màu sắc là bắt buộc.',
        'data.*.quick_links.*.color.string'   => 'Trường màu sắc phải là chuỗi.',
        'data.*.quick_links.*.color.max'      => 'Trường màu sắc không được vượt quá :max ký tự.',
        'data.*.quick_links.*.img.string'     => 'Trường hình ảnh phải là chuỗi.',
        'data.*.quick_links.*.img.max'        => 'Trường hình ảnh không được vượt quá :max ký tự.',
        'data.*.quick_links.*.img_file.image' => 'Tệp tải lên phải là một hình ảnh.',
        'data.*.quick_links.*.img_file.mimes' => 'Hình ảnh phải có định dạng: jpg, jpeg, png, webp.',
        'data.*.quick_links.*.img_file.max'   => 'Hình ảnh không được vượt quá 2MB.',

        // --- CHƯƠNG TRÌNH ĐÀO TẠO (TRAINING PROGRAMS) ---
        'data.*.training_programs.*.title.required'       => 'Trường tiêu đề thẻ là bắt buộc.',
        'data.*.training_programs.*.title.string'         => 'Trường tiêu đề thẻ phải là chuỗi.',
        'data.*.training_programs.*.title.max'            => 'Trường tiêu đề thẻ không được vượt quá :max ký tự.',
        'data.*.training_programs.*.description.required' => 'Trường mô tả là bắt buộc.',
        'data.*.training_programs.*.description.string'   => 'Trường mô tả phải là chuỗi.',
        'data.*.training_programs.*.detail_url.required'  => 'Trường link chi tiết là bắt buộc.',
        'data.*.training_programs.*.detail_url.string'    => 'Trường link chi tiết phải là chuỗi.',
        'data.*.training_programs.*.detail_url.max'       => 'Trường link chi tiết không được vượt quá :max ký tự.',
        'data.*.training_programs.*.roadmap_url.required' => 'Trường link lộ trình là bắt buộc.',
        'data.*.training_programs.*.roadmap_url.string'   => 'Trường link lộ trình phải là chuỗi.',
        'data.*.training_programs.*.roadmap_url.max'      => 'Trường link lộ trình không được vượt quá :max ký tự.',
        'data.*.training_programs.*.image_file.image'     => 'Tệp tải lên phải là một hình ảnh.',
        'data.*.training_programs.*.image_file.mimes'     => 'Hình ảnh phải có định dạng: jpg, jpeg, png, webp.',
        'data.*.training_programs.*.image_file.max'       => 'Hình ảnh không được vượt quá 2MB.',

        // --- CHỈ SỐ THỐNG KÊ (COUNTER STATS) ---
        'data.*.counter_stats.*.label.required' => 'Trường nhãn chỉ số là bắt buộc.',
        'data.*.counter_stats.*.label.string'   => 'Trường nhãn chỉ số phải là chuỗi.',
        'data.*.counter_stats.*.label.max'      => 'Trường nhãn chỉ số không được vượt quá :max ký tự.',
        'data.*.counter_stats.*.value.required' => 'Trường giá trị là bắt buộc.',
        'data.*.counter_stats.*.value.integer'  => 'Trường giá trị phải là số nguyên.',
        'data.*.counter_stats.*.value.min'      => 'Giá trị không được nhỏ hơn :min.',
        'data.*.counter_stats.*.suffix.required'=> 'Trường hậu tố là bắt buộc.',
        'data.*.counter_stats.*.suffix.string'  => 'Trường hậu tố phải là chuỗi.',
        'data.*.counter_stats.*.suffix.max'     => 'Trường hậu tố không được vượt quá :max ký tự.',
        'data.*.counter_stats.*.icon.required'  => 'Bạn chưa chọn Icon cho chỉ số.',
        'data.*.counter_stats.*.icon.string'    => 'Icon phải là chuỗi hợp lệ.',
        'data.*.counter_stats.*.icon.max'       => 'Icon không được vượt quá :max ký tự.',

        // --- LỜI CHIA SẺ (TESTIMONIALS) ---
        'data.*.testimonials.*.name.required'      => 'Trường họ tên là bắt buộc.',
        'data.*.testimonials.*.name.string'        => 'Trường họ tên phải là chuỗi.',
        'data.*.testimonials.*.name.max'           => 'Trường họ tên không được vượt quá :max ký tự.',
        'data.*.testimonials.*.role.required'      => 'Trường chức danh là bắt buộc.',
        'data.*.testimonials.*.role.string'        => 'Trường chức danh phải là chuỗi.',
        'data.*.testimonials.*.role.max'           => 'Trường chức danh không được vượt quá :max ký tự.',
        'data.*.testimonials.*.content.required'   => 'Trường nội dung chia sẻ là bắt buộc.',
        'data.*.testimonials.*.content.string'     => 'Trường nội dung chia sẻ phải là chuỗi.',
        'data.*.testimonials.*.avatar_file.image'  => 'Ảnh đại diện tải lên phải là hình ảnh.',
        'data.*.testimonials.*.avatar_file.mimes'  => 'Ảnh đại diện phải có định dạng: jpg, jpeg, png, webp.',
        'data.*.testimonials.*.avatar_file.max'    => 'Ảnh đại diện không được vượt quá 2MB.',
    ];

    protected function defaultData(string $locale): array
    {
        if ($locale === 'en') {
            return [
                'section_titles' => [
                    'news' => 'News and events',
                    'training' => 'Training programs',
                    'partners' => 'NETWORK OF BUSINESS PARTNERS',
                    'testimonials' => 'Perspectives from businesses and alumni',
                    'gallery' => 'Photo library',
                ],
                'quick_links' => [
                    [
                        'id' => 'quick-link-1',
                        'app' => 'ST-CARE',
                        'desc' => 'Q&A & student support',
                        'link' => 'https://st-dse.vnua.edu.vn:6896',
                        'color' => '#0961AA',
                        'img' => 'assets/images/question-and-answer.png',
                    ],
                    [
                        'id' => 'quick-link-2',
                        'app' => 'CONSULTING',
                        'desc' => 'Choose a specialization',
                        'link' => 'https://st-dse.vnua.edu.vn:6879',
                        'color' => '#F6A309',
                        'img' => 'assets/images/health.png',
                    ],
                    [
                        'id' => 'quick-link-3',
                        'app' => 'REGISTER',
                        'desc' => 'Internship & thesis',
                        'link' => 'https://st-dse.vnua.edu.vn:6875',
                        'color' => '#066140',
                        'img' => 'assets/images/register.png',
                    ],
                    [
                        'id' => 'quick-link-4',
                        'app' => 'MANAGE',
                        'desc' => 'Lab activities',
                        'link' => 'https://st-dse.vnua.edu.vn:6888',
                        'color' => '#4E3636',
                        'img' => 'assets/images/calendar1.png',
                    ],
                ],
                'training_programs' => [
                    [
                        'id' => 'program-1',
                        'title' => 'Information Technology',
                        'description' => 'The Information Technology program trains IT graduates with strong political awareness, professional ethics, responsibility, and good health; advanced knowledge and practical skills; creativity, self-learning, and research capacity; and a spirit of entrepreneurship and international integration.',
                        'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/cong-nghe-thong-tin',
                        'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=cong-nghe-thong-tin',
                        'image' => 'assets/images/nganh-cntt.jpg',
                    ],
                    [
                        'id' => 'program-2',
                        'title' => 'Computer Networks and Data Communications',
                        'description' => 'This program trains graduates with strong political awareness, good health, solid knowledge, and practical skills in computing and IT; capable of self-learning and research to meet the needs of organizations and companies in the computing and IT sector.',
                        'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-mang-may-tinh-va-truyen-thong-du-lieu',
                        'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=mang-may-tinh-truyen-thong-du-lieu',
                        'image' => 'assets/images/nganh-mmt.jpg',
                    ],
                    [
                        'id' => 'program-3',
                        'title' => 'Data Science and Artificial Intelligence',
                        'description' => 'The Data Science and Artificial Intelligence program trains graduates with strong political awareness, professional ethics, responsibility, and good health; advanced knowledge and practical skills; creativity, self-learning, and research capacity; and a spirit of entrepreneurship and international integration.',
                        'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-khoa-hoc-du-lieu-va-tri-tue-nhan-tao',
                        'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=khoa-hoc-du-lieu-va-tri-tue-nhan-tao',
                        'image' => 'assets/images/nganh-khdlttnt.jpg',
                    ],
                ],
                'counter_stats' => [
                    ['id' => 'stat-1', 'label' => 'Years of Training Experience', 'value' => 20, 'suffix' => '+', 'icon' => 'o-calendar-date-range'],
                    ['id' => 'stat-2', 'label' => 'Students currently enrolled', 'value' => 3500, 'suffix' => '+', 'icon' => 'o-user-group'],
                    ['id' => 'stat-3', 'label' => 'Graduated students', 'value' => 12000, 'suffix' => '+', 'icon' => 'o-academic-cap'],
                    ['id' => 'stat-4', 'label' => 'Graduates find jobs.', 'value' => 96, 'suffix' => '%', 'icon' => 'o-briefcase'],
                ],
                'testimonials' => [
                    [
                        'id' => 'testimonial-1',
                        'name' => 'Mr. Le Doan Phuoc',
                        'role' => 'Director, Mai A Technology Co., Ltd.',
                        'content' => 'The IT faculty patiently guides students and helps them gain confidence to improve. Thanks to this foundation, when joining MaiA Tech, students show a proactive attitude, strong will, and adapt very quickly to real projects.',
                        'avatar' => 'assets/images/ldphuoc.jpg',
                    ],
                ],
            ];
        }

        return [
            'section_titles' => [
                'news' => 'Tin tức và sự kiện',
                'training' => 'Chương trình đào tạo',
                'partners' => 'Mạng lưới đối tác doanh nghiệp',
                'testimonials' => 'Góc nhìn từ doanh nghiệp và cựu sinh viên',
                'gallery' => 'Thư viện ảnh',
            ],
            'quick_links' => [
                [
                    'id' => 'quick-link-1',
                    'app' => 'ST-CARE',
                    'desc' => 'Hỏi đáp & Hỗ trợ sinh viên',
                    'link' => 'https://st-dse.vnua.edu.vn:6896',
                    'color' => '#0961AA',
                    'img' => 'assets/images/question-and-answer.png',
                ],
                [
                    'id' => 'quick-link-2',
                    'app' => 'TƯ VẤN',
                    'desc' => 'Chọn hướng chuyên sâu',
                    'link' => 'https://st-dse.vnua.edu.vn:6879',
                    'color' => '#F6A309',
                    'img' => 'assets/images/health.png',
                ],
                [
                    'id' => 'quick-link-3',
                    'app' => 'ĐĂNG KÝ',
                    'desc' => 'Thực tập nghề nghiệp & KLTN',
                    'link' => 'https://st-dse.vnua.edu.vn:6875',
                    'color' => '#066140',
                    'img' => 'assets/images/register.png',
                ],
                [
                    'id' => 'quick-link-4',
                    'app' => 'QUẢN LÝ',
                    'desc' => 'Hoạt động phòng lab',
                    'link' => 'https://st-dse.vnua.edu.vn:6888',
                    'color' => '#4E3636',
                    'img' => 'assets/images/calendar1.png',
                ],
            ],
            'training_programs' => [
                [
                    'id' => 'program-1',
                    'title' => 'Công nghệ thông tin',
                    'description' => 'Chương trình đào tạo ngành Công nghệ thông tin (CNTT) nhằm đào tạo ra cử nhân CNTT có phẩm chất chính trị vững vàng, có đạo đức nghề nghiệp, có trách nhiệm cao và sức khỏe tốt; có kiến thức chuyên sâu và thành thạo kỹ năng nghề nghiệp; có năng lực sáng tạo, tự học, tự nghiên cứu nhằm không ngừng nâng cao trình độ; có tinh thần lập nghiệp, hội nhập quốc tế; đóng góp nguồn nhân lực chất lượng cao trong lĩnh vực CNTT và lĩnh vực nông nghiệp hiện đại.',
                    'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/cong-nghe-thong-tin',
                    'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=cong-nghe-thong-tin',
                    'image' => 'assets/images/nganh-cntt.jpg',
                ],
                [
                    'id' => 'program-2',
                    'title' => 'Mạng máy tính và TTDL',
                    'description' => 'Chương trình đào tạo ngành mạng máy tính và truyền thông dữ liệu (MMT&TTDL) nhằm đào tạo cử nhân có phẩm chất chính trị vững vàng, có sức khỏe tốt; có kiến thức và kỹ năng vững vàng về lĩnh vực máy tính và công nghệ thông tin (CNTT); có khả năng tự học, tự nghiên cứu nhằm đáp ứng được yêu cầu công việc tại các cơ quan, các công ty liên quan đến lĩnh vực máy tính và CNTT.',
                    'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-mang-may-tinh-va-truyen-thong-du-lieu',
                    'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=mang-may-tinh-truyen-thong-du-lieu',
                    'image' => 'assets/images/nganh-mmt.jpg',
                ],
                [
                    'id' => 'program-3',
                    'title' => 'Khoa học dữ liệu và TTNT',
                    'description' => 'Chương trình đào tạo ngành Khoa học dữ liệu và Trí tuệ nhân tạo (KHDL&TTNT) nhằm đào tạo ra cử nhân có phẩm chất chính trị vững vàng, có đạo đức nghề nghiệp, có trách nhiệm cao và sức khỏe tốt; có kiến thức chuyên sâu và thành thạo kỹ năng nghề nghiệp; có năng lực sáng tạo, tự học, tự nghiên cứu nhằm không ngừng nâng cao trình độ; có tinh thần lập nghiệp, hội nhập quốc tế; đóng góp nguồn nhân lực chất lượng cao trong lĩnh vực KHDL&TTNT và lĩnh vực nông nghiệp hiện đại.',
                    'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-khoa-hoc-du-lieu-va-tri-tue-nhan-tao',
                    'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=khoa-hoc-du-lieu-va-tri-tue-nhan-tao',
                    'image' => 'assets/images/nganh-khdlttnt.jpg',
                ],
            ],
            'counter_stats' => [
                ['id' => 'stat-1', 'label' => 'Số năm kinh nghiệm đào tạo', 'value' => 20, 'suffix' => '+', 'icon' => 'o-calendar-date-range'],
                ['id' => 'stat-2', 'label' => 'Sinh viên đang theo học', 'value' => 3500, 'suffix' => '+', 'icon' => 'o-user-group'],
                ['id' => 'stat-3', 'label' => 'Sinh viên đã tốt nghiệp', 'value' => 12000, 'suffix' => '+', 'icon' => 'o-academic-cap'],
                ['id' => 'stat-4', 'label' => 'Sinh viên có việc làm.', 'value' => 96, 'suffix' => '%', 'icon' => 'o-briefcase'],
            ],
            'testimonials' => [
                [
                    'id' => 'testimonial-1',
                    'name' => 'Ông Lê Doãn Phước',
                    'role' => 'Giám đốc Công ty TNHH Công nghệ Mai A',
                    'content' => 'Các thầy cô Khoa CNTT rất kiên trì dìu dắt sinh viên, giúp các em có niềm tin để tiến bộ. Nhờ nền tảng và sự rèn giũa này, khi gia nhập MaiA Tech, các em đều thể hiện thái độ làm việc cầu thị, ý chí vươn lên và thích ứng rất nhanh với dự án thực tế.',
                    'avatar' => 'assets/images/ldphuoc.jpg',
                ],
            ],
        ];
    }

    protected function normalizeLocaleData(string $locale, array $data): array
    {
        $defaults = $this->defaultData($locale);

        $data['quick_links'] = $this->ensureUploadFields(array_values(array_map(function ($item) use ($defaults) {
            $template = $defaults['quick_links'][0] ?? [];
            return is_array($item) ? array_merge($template, $item) : $template;
        }, $data['quick_links'] ?? [])), ['img_file']);

        $data['training_programs'] = $this->ensureUploadFields(array_values(array_map(function ($item) use ($defaults) {
            $template = $defaults['training_programs'][0] ?? [];
            return is_array($item) ? array_merge($template, $item) : $template;
        }, $data['training_programs'] ?? [])), ['image_file']);

        $data['counter_stats'] = array_values(array_map(function ($item) use ($defaults) {
            $template = $defaults['counter_stats'][0] ?? [];
            return is_array($item) ? array_merge($template, $item) : $template;
        }, $data['counter_stats'] ?? []));

        $data['testimonials'] = $this->ensureUploadFields(array_values(array_map(function ($item) use ($defaults) {
            $template = $defaults['testimonials'][0] ?? [];
            return is_array($item) ? array_merge($template, $item) : $template;
        }, $data['testimonials'] ?? [])), ['avatar_file']);

        $data['section_titles'] = array_merge($defaults['section_titles'], $data['section_titles'] ?? []);

        return $data;
    }

    protected function ensureIdsForLocale(string $locale): void
    {
        foreach (['quick_links', 'training_programs', 'counter_stats', 'testimonials'] as $section) {
            foreach (($this->data[$locale][$section] ?? []) as $index => $item) {
                if (empty($this->data[$locale][$section][$index]['id'])) {
                    $this->data[$locale][$section][$index]['id'] = Str::random(8);
                }
            }
        }
    }

    protected function loadLocalePageData(?Page $page, string $locale): array
    {
        if (!$page) {
            return $this->normalizeLocaleData($locale, $this->defaultData($locale));
        }

        $translations = $page->getTranslations('content_data');

        if (array_key_exists($locale, $translations)) {
            $data = $page->getTranslation('content_data', $locale, false);
            return is_array($data) ? $this->normalizeLocaleData($locale, $data) : $this->normalizeLocaleData($locale, $this->defaultData($locale));
        }

        if (array_key_exists('vi', $translations)) {
            $data = $page->getTranslation('content_data', 'vi', false);
            return is_array($data) ? $this->normalizeLocaleData($locale, $data) : $this->normalizeLocaleData($locale, $this->defaultData($locale));
        }

        return $this->normalizeLocaleData($locale, $this->defaultData($locale));
    }

    public function mount(): void
    {
        $page = Page::where('slug', 'home3')->first();

        $this->data = [
            'vi' => $this->loadLocalePageData($page, 'vi'),
            'en' => $this->loadLocalePageData($page, 'en'),
        ];

        $this->ensureIdsForLocale('vi');
        $this->ensureIdsForLocale('en');
        $this->originalData = $this->data;
        $this->syncToPreviewCache();
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'data.')) {
            $this->validateOnly($property);
//            $this->syncToPreviewCache();
        }
    }

    public function addQuickLink(string $locale): void
    {
        $this->data[$locale]['quick_links'][] = [
            'id' => Str::random(8),
            'app' => '',
            'desc' => '',
            'link' => '',
            'color' => '#0961AA',
            'img' => '',
            'img_file' => null,
        ];
        $this->success($locale === 'vi' ? 'Đã thêm lối tắt mới thành công' : 'Added new quick link successfully');
    }

    public function removeQuickLinkImage(string $locale, int $index): void
    {
        if (!isset($this->data[$locale]['quick_links'][$index])) {
            return;
        }
        $this->data[$locale]['quick_links'][$index]['img'] = null;
        $this->data[$locale]['quick_links'][$index]['img_file'] = null;
    }

    public function updateQuickLinksOrder($locale, $orderedIds): void
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $items = collect($this->data[$locale]['quick_links'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $items
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['quick_links'] = array_values(array_merge($newOrder, $remaining));
    }

    public function removeQuickLink(string $locale, string $id): void
    {
        $title = $locale === 'vi' ? 'Bạn có chắc muốn xóa ô lối tắt này không?' : 'Are you sure you want to remove this quick link?';
        $confirm = $locale === 'vi' ? 'Xác nhận' : 'Confirm';
        $cancel = $locale === 'vi' ? 'Hủy' : 'Cancel';

        $this->dispatch('modal:confirm', [
            'title' => $title,
            'icon' => 'question',
            'confirmButtonText' => $confirm,
            'cancelButtonText' => $cancel,
            'method' => 'confirmRemoveQuickLink',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveQuickLink')]
    public function confirmRemoveQuickLink($id): void
    {
        [$locale, $quickLinkId] = $id;
        $item = collect($this->data[$locale]['quick_links'] ?? [])->firstWhere('id', $quickLinkId);
        if ($item && !$this->isMediaPathStillReferenced($item['img'] ?? '', $locale, 'quick_links', $quickLinkId, 'img')) {
            $this->deleteStoredMedia($item['img'] ?? null);
        }
        $this->data[$locale]['quick_links'] = collect($this->data[$locale]['quick_links'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $quickLinkId)
            ->values()
            ->toArray();
        $this->success($locale === 'vi' ? 'Đã xóa lối tắt thành công' : 'Quick link removed successfully');
    }

    public function addTrainingProgram(string $locale): void
    {
        $this->data[$locale]['training_programs'][] = [
            'id' => Str::random(8),
            'title' => '',
            'description' => '',
            'detail_url' => '',
            'roadmap_url' => '',
            'image' => '',
            'image_file' => null,
        ];
        $this->success($locale === 'vi' ? 'Đã thêm thẻ mới thành công' : 'Added new program successfully');
    }

    public function removeTrainingProgramImage(string $locale, int $index): void
    {
        if (!isset($this->data[$locale]['training_programs'][$index])) {
            return;
        }
        $this->data[$locale]['training_programs'][$index]['image'] = null;
        $this->data[$locale]['training_programs'][$index]['image_file'] = null;
    }

    public function removeTrainingProgram(string $locale, string $id): void
    {
        $title = $locale === 'vi' ? 'Bạn có chắc muốn xóa thẻ chương trình này không?' : 'Are you sure you want to remove this program?';
        $confirm = $locale === 'vi' ? 'Xác nhận' : 'Confirm';
        $cancel = $locale === 'vi' ? 'Hủy' : 'Cancel';

        $this->dispatch('modal:confirm', [
            'title' => $title,
            'icon' => 'question',
            'confirmButtonText' => $confirm,
            'cancelButtonText' => $cancel,
            'method' => 'confirmRemoveTrainingProgram',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveTrainingProgram')]
    public function confirmRemoveTrainingProgram(array $id): void
    {
        [$locale, $TrainingId] = $id;
        $item = collect($this->data[$locale]['training_programs'] ?? [])->firstWhere('id', $TrainingId);
        if ($item && !$this->isMediaPathStillReferenced($item['image'] ?? '', $locale, 'training_programs', $TrainingId, 'image')) {
            $this->deleteStoredMedia($item['image'] ?? null);
        }
        $this->data[$locale]['training_programs'] = collect($this->data[$locale]['training_programs'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $TrainingId)
            ->values()
            ->toArray();
        $this->success($locale === 'vi' ? 'Đã xóa thẻ chương trình thành công' : 'Program removed successfully');
    }

    public function updateTrainingOrder($locale, $orderedIds): void
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $items = collect($this->data[$locale]['training_programs'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $items
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['training_programs'] = array_values(array_merge($newOrder, $remaining));
    }

    public function addCounterStat(string $locale): void
    {
        $this->data[$locale]['counter_stats'][] = [
            'id' => Str::random(8),
            'label' => '',
            'value' => 0,
            'suffix' => '+',
            'icon' => 'o-star',
        ];
        $this->success($locale === 'vi' ? 'Đã thêm chỉ số thống kê mới thành công' : 'Added new statistic successfully');
    }

    public function removeCounterStat(string $locale, string $id): void
    {
        $title = $locale === 'vi' ? 'Bạn có chắc muốn xóa chỉ số thống kê này không?' : 'Are you sure you want to remove this statistic?';
        $confirm = $locale === 'vi' ? 'Xác nhận' : 'Confirm';
        $cancel = $locale === 'vi' ? 'Hủy' : 'Cancel';

        $this->dispatch('modal:confirm', [
            'title' => $title,
            'icon' => 'question',
            'confirmButtonText' => $confirm,
            'cancelButtonText' => $cancel,
            'method' => 'confirmRemoveCounterStat',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveCounterStat')]
    public function confirmRemoveCounterStat(array $id): void
    {
        [$locale, $CounterStartId] = $id;
        $this->data[$locale]['counter_stats'] = collect($this->data[$locale]['counter_stats'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $CounterStartId)
            ->values()
            ->toArray();
        $this->success($locale === 'vi' ? 'Đã xóa chỉ số thống kê thành công' : 'Statistic removed successfully');
    }

    public function updateCounterStarOrder($locale, $orderedIds): void
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $items = collect($this->data[$locale]['counter_stats'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $items
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['counter_stats'] = array_values(array_merge($newOrder, $remaining));
    }

    public function addTestimonial(string $locale): void
    {
        $this->data[$locale]['testimonials'][] = [
            'id' => Str::random(8),
            'name' => '',
            'role' => '',
            'content' => '',
            'avatar' => '',
            'avatar_file' => null,
        ];
        $this->success($locale === 'vi' ? 'Đã thêm lời chia sẻ mới thành công' : 'Added new testimonial successfully');
    }

    public function removeTestimonialImage(string $locale, int $index): void
    {
        if (!isset($this->data[$locale]['testimonials'][$index])) {
            return;
        }
        $this->data[$locale]['testimonials'][$index]['avatar'] = null;
        $this->data[$locale]['testimonials'][$index]['avatar_file'] = null;
    }

    protected function persistUploads(): void
    {
        foreach (['vi', 'en'] as $locale) {
            foreach ($this->data[$locale]['quick_links'] ?? [] as $index => $item) {
                if (!empty($item['img_file']) && $this->isUpload($item['img_file'])) {
                    $this->data[$locale]['quick_links'][$index]['img'] = $item['img_file']->store('uploads/home/quick-links', 'public');
                }
                $this->data[$locale]['quick_links'][$index]['img_file'] = null;
            }

            foreach ($this->data[$locale]['training_programs'] ?? [] as $index => $item) {
                if (!empty($item['image_file']) && $this->isUpload($item['image_file'])) {
                    $this->data[$locale]['training_programs'][$index]['image'] = $item['image_file']->store('uploads/home/training-programs', 'public');
                }
                $this->data[$locale]['training_programs'][$index]['image_file'] = null;
            }

            foreach ($this->data[$locale]['testimonials'] ?? [] as $index => $item) {
                if (!empty($item['avatar_file']) && $this->isUpload($item['avatar_file'])) {
                    $this->data[$locale]['testimonials'][$index]['avatar'] = $item['avatar_file']->store('uploads/home/testimonials', 'public');
                }
                $this->data[$locale]['testimonials'][$index]['avatar_file'] = null;
            }
        }
    }

    public function removeTestimonial(string $locale, string $id): void
    {
        $title = $locale === 'vi' ? 'Bạn có chắc muốn xóa lời chia sẻ này không?' : 'Are you sure you want to remove this testimonial?';
        $confirm = $locale === 'vi' ? 'Xác nhận' : 'Confirm';
        $cancel = $locale === 'vi' ? 'Hủy' : 'Cancel';

        $this->dispatch('modal:confirm', [
            'title' => $title,
            'icon' => 'question',
            'confirmButtonText' => $confirm,
            'cancelButtonText' => $cancel,
            'method' => 'confirmRemoveTestimonial',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveTestimonial')]
    public function confirmRemoveTestimonial($id): void
    {
        [$locale, $testimonialsId] = $id;
        $item = collect($this->data[$locale]['testimonials'] ?? [])->firstWhere('id', $testimonialsId);
        if ($item && !$this->isMediaPathStillReferenced($item['avatar'] ?? '', $locale, 'testimonials', $testimonialsId, 'avatar')) {
            $this->deleteStoredMedia($item['avatar'] ?? null);
        }
        $this->data[$locale]['testimonials'] = collect($this->data[$locale]['testimonials'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $testimonialsId)
            ->values()
            ->toArray();
        $this->success($locale === 'vi' ? 'Đã xóa lời chia sẻ thành công' : 'Testimonial removed successfully');
    }

    public function updateTestimonialsOrder($locale, $orderedIds): void
    {
        if (!in_array($locale, ['vi', 'en'], true)) {
            return;
        }

        $items = collect($this->data[$locale]['testimonials'] ?? []);
        $newOrder = [];

        foreach ($orderedIds as $id) {
            $item = $items->firstWhere('id', $id);
            if ($item) {
                $newOrder[] = $item;
            }
        }

        $remaining = $items
            ->reject(fn($item) => in_array($item['id'] ?? null, $orderedIds, true))
            ->values()
            ->all();

        $this->data[$locale]['testimonials'] = array_values(array_merge($newOrder, $remaining));
    }

    public function save(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại các trường dữ liệu.');
            throw $e;
        }

        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn lưu cấu hình trang chủ không?',
            'icon' => 'question',
            'confirmButtonText' => 'Lưu',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmSave',
        ]);
    }

    #[On('confirmSave')]
    public function confirmSave(): void
    {
        $originalData = $this->originalData;
        $this->persistUploads();
        $this->cleanupReplacedMedia($originalData);

        $page = Page::updateOrCreate(
            ['slug' => 'home3'],
            ['layout' => 'home3']
        );

        $page->setTranslations('content_data', $this->data);
        $page->save();

        $this->originalData = $this->data;
        Cache::forget($this->previewCacheKey());

        $this->success('Lưu cấu hình trang chủ thành công.');
    }

    public function preview(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại các trường dữ liệu.');
            throw $e;
        }

        $this->syncToPreviewCache();
        $this->dispatch('open-new-tab', url: route('client.home', ['preview_home3' => 1]));
    }
};
?>

<div x-data="{
        storageKey: 'page_home_open_state',
        openStates: {},

        init() {
            try {
                const raw = localStorage.getItem(this.storageKey);
                this.openStates = raw ? JSON.parse(raw) : {};
            } catch (e) {
                this.openStates = {};
            }
        },

        saveToLocal() {
            localStorage.setItem(this.storageKey, JSON.stringify(this.openStates));
        },

        ensureState(id, defaultState = true) {
            if (this.openStates[id] === undefined) {
                this.openStates[id] = defaultState;
                this.saveToLocal();
            }
        },

        isOpen(id) {
            return this.openStates[id] !== false;
        },

        toggle(id) {
            this.ensureState(id);
            this.openStates[id] = !this.openStates[id];
            this.saveToLocal();
        }
    }"
>
    <x-slot:title>Cấu hình trang chủ</x-slot:title>
    <x-slot:breadcrumb><span>Cấu hình trang chủ</span></x-slot:breadcrumb>
    <x-header title="Cấu hình trang chủ" class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:actions>
            <x-button label="Banner" link="{{ route('admin.banner.index') }}" class="btn-ghost" />
            <x-button label="Bài viết" link="{{ route('admin.post.index') }}" class="btn-ghost" />
            <x-button label="Album ảnh" link="{{ route('admin.album.index') }}" class="btn-ghost" />
            <x-button label="Đối tác" link="{{ route('admin.partner.index') }}" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <x-card class="col-span-10 flex flex-col p-3!">
            <x-tabs wire:model="selectedTab">
                <!-- ============================ TAB TIẾNG VIỆT ============================ -->
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <div class="space-y-5">

                        <!-- LỐI TẮT NHANH (VI) -->
                        <div x-data="{
                            sortableQuickLinks: null,
                                 initQuickLinksTable() {
                                     if (this.sortableQuickLinks) this.sortableQuickLinks.destroy();
                                     if (!this.$refs.quickLinksVi) return;
                                     this.sortableQuickLinks = new Sortable(this.$refs.quickLinksVi, {
                                         animation: 150,
                                         handle: '.drag-quick-links-handle-vi',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.quickLinksVi.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateQuickLinksOrder('vi', order);
                                         }
                                     });
                                 }
                            }"
                             x-init="$nextTick(() => initQuickLinksTable()); ensureState('vi-quick-links', true)"
                             class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                        >
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-md text-gray-700 hover:text-primary transition" @click="toggle('vi-quick-links')">
                                    Khối lối tắt nhanh
                                </button>
                                <x-button icon="o-plus" label="Thêm lối tắt" class="btn-sm bg-emerald-600 text-white" wire:click="addQuickLink('vi')" spinner="addQuickLink('vi')" />
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('vi-quick-links') ? 'rotate-180' : ''" @click="toggle('vi-quick-links')"/>
                            </div>
                            <div x-show="isOpen('vi-quick-links')" x-collapse x-ref="quickLinksVi" class="p-4 space-y-4">
                                @foreach($data['vi']['quick_links'] as $index => $item)
                                    @php $itemId = 'vi-quick-link-' . ($item['id'] ?? $index); @endphp
                                    <div data-id="{{ $item['id'] }}"
                                         wire:key="vi-quick-link-{{ $item['id'] ?? $index }}"
                                         x-init="ensureState('{{ $itemId }}', false)"
                                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-quick-links-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{ $itemId }}')">
                                                {{ $item['app'] ? 'Lối tắt - '. $item['app'] : 'Lối tắt #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeQuickLink('vi', '{{ $item['id'] }}')" spinner="removeQuickLink('vi', '{{ $item['id'] }}')"/>
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{ $itemId }}') ? 'rotate-180' : ''" @click="toggle('{{ $itemId }}')"/>
                                        </div>
                                        <div x-show="isOpen('{{ $itemId }}')" x-collapse class="p-4 pt-0 space-y-0">
                                            <div class="grid md:grid-cols-1 lg:grid-cols-2 gap-x-4">
                                                <div class="lg:col-span-2">
                                                    <x-input label="Tiêu đề lối tắt" wire:model="data.vi.quick_links.{{ $index }}.app" placeholder="Nhập tiêu đề lối tắt" required/>
                                                </div>
                                                <x-input label="Mô tả" wire:model="data.vi.quick_links.{{ $index }}.desc" placeholder="Nhập mô tả (tooltip)"/>
                                                <x-input label="Đường dẫn" wire:model="data.vi.quick_links.{{ $index }}.link" required placeholder="/duong-dan hoặc https://example.com hoặc ###"/>
                                                <div class="lg:col-span-2 space-y-2 mt-2">
                                                    <label class="font-medium text-sm">Ảnh icon</label>
                                                    <input
                                                        wire:key="vi-quick-link-image-{{ $item['id'] }}"
                                                        type="file"
                                                        wire:model="data.vi.quick_links.{{ $index }}.img_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center group">
                                                        <div wire:loading.flex wire:target="data.vi.quick_links.{{ $index }}.img_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'img_file') || data_get($item, 'img'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'img_file'), data_get($item, 'img')) }}" class="h-28 rounded-lg object-cover" alt="Ảnh icon" />
                                                            <x-button type="button"
                                                                      wire:click="removeQuickLinkImage('vi', {{ $index }})"
                                                                      class="absolute top-2 right-2 z-5 btn btn-circle btn-xs btn-error text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                                                      tooltip-left="Xóa ảnh" icon="o-x-mark" spinner="removeQuickLinkImage('vi', {{ $index }})">
                                                            </x-button>
                                                        @else
                                                            <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- CHƯƠNG TRÌNH ĐÀO TẠO (VI) -->
                        <div x-data="{
                            sortableTraining: null,
                                 initTrainingSortable() {
                                     if (this.sortableTraining) this.sortableTraining.destroy();
                                     if (!this.$refs.trainingVi) return;
                                     this.sortableTraining = new Sortable(this.$refs.trainingVi, {
                                         animation: 150,
                                         handle: '.drag-training-handle-vi',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.trainingVi.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateTrainingOrder('vi', order);
                                         }
                                     });
                                 }
                            }"
                             x-init="$nextTick(() => initTrainingSortable()); ensureState('vi-training', true)"
                             class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('vi-training')">
                                    Chương trình đào tạo
                                </button>
                                <x-button label="Thêm thẻ mới" icon="o-plus" class="btn-sm bg-emerald-600 text-white" wire:click="addTrainingProgram('vi')" spinner="addTrainingProgram('vi')"/>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('vi-training') ? 'rotate-180' : ''" @click="toggle('vi-training')"/>
                            </div>
                            <div x-show="isOpen('vi-training')" x-collapse x-ref="trainingVi" class="p-4 space-y-4">
                                @foreach($data['vi']['training_programs'] as $index => $item)
                                    @php $itemId = 'vi-training-' . ($item['id'] ?? $index); @endphp
                                    <div
                                        data-id="{{$item['id']}}"
                                        wire:key="{{$itemId}}"
                                        x-init="ensureState('{{$itemId}}', true)" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-training-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{$itemId}}', true)">
                                                {{ $item['title'] ? 'Thẻ - '. $item['title'] : 'Thẻ #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTrainingProgram('vi', '{{ $item['id'] }}')" spinner="removeTrainingProgram('vi', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{$itemId}}') ? 'rotate-180' : ''" @click="toggle('{{$itemId}}', true)"/>
                                        </div>
                                        <div x-show="isOpen('{{$itemId}}')" x-collapse class="p-4 pt-0 space-y-4">
                                            <div class="grid md:grid-cols-2 gap-x-4">
                                                <div class="md:col-span-2">
                                                    <x-input label="Tiêu đề thẻ" wire:model="data.vi.training_programs.{{ $index }}.title" required placeholder="Nhập tiêu đề thẻ "/>
                                                    <x-textarea label="Mô tả" wire:model="data.vi.training_programs.{{ $index }}.description" rows="4" required placeholder="Nhập mô tả"/>
                                                </div>
                                                <x-input label="Link chi tiết" wire:model="data.vi.training_programs.{{ $index }}.detail_url" placeholder="/duong-dan hoặc https://example.com hoặc ###"/>
                                                <x-input label="Link lộ trình" wire:model="data.vi.training_programs.{{ $index }}.roadmap_url" placeholder="/duong-dan hoặc https://example.com hoặc ###"/>
                                                <div class="md:col-span-2 space-y-2 mt-2">
                                                    <label class="font-medium text-sm">Ảnh chương trình</label>
                                                    <input
                                                        wire:key="vi-training-image-{{ $item['id'] }}"
                                                        type="file"
                                                        wire:model="data.vi.training_programs.{{ $index }}.image_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center group">
                                                        <div wire:loading.flex wire:target="data.vi.training_programs.{{ $index }}.image_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'image_file') || data_get($item, 'image'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'image_file'), data_get($item, 'image')) }}" class="h-34 rounded-lg object-cover" alt="Ảnh chương trình" />
                                                            <x-button type="button"
                                                                      wire:click="removeTrainingProgramImage('vi', {{ $index }})"
                                                                      class="absolute top-2 right-2 z-5 btn btn-circle btn-xs btn-error text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                                                      tooltip-left="Xóa ảnh" icon="o-x-mark" spinner="removeTrainingProgramImage('vi', {{ $index }})">
                                                            </x-button>
                                                        @else
                                                            <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- CHỈ SỐ THỐNG KÊ (VI) -->
                        <div x-data="{
                            sortableCounterStar: null,
                                 initStatsSortable() {
                                     if (this.sortableCounterStar) this.sortableCounterStar.destroy();
                                     if (!this.$refs.counterStarVi) return;
                                     this.sortableCounterStar = new Sortable(this.$refs.counterStarVi, {
                                         animation: 150,
                                         handle: '.drag-stats-handle-vi',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.counterStarVi.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateCounterStarOrder('vi', order);
                                         }
                                     });
                                 }
                            }" x-init="$nextTick(() => initStatsSortable()); ensureState('vi-stats', true)" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('vi-stats')">
                                    Những con số ấn tượng
                                </button>
                                <x-button label="Thêm chỉ số" icon="o-plus" class="btn-sm bg-emerald-600 text-white" wire:click="addCounterStat('vi')" spinner="addCounterStat('vi')"/>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('vi-stats') ? 'rotate-180' : ''" @click="toggle('vi-stats')"/>
                            </div>
                            <div x-show="isOpen('vi-stats')" x-collapse x-ref="counterStarVi" class="p-4 space-y-4">
                                @foreach($data['vi']['counter_stats'] as $index => $item)
                                    @php $itemId = 'vi-counter-star-' . ($item['id'] ?? $index); @endphp
                                    <div
                                        data-id="{{$item['id']}}"
                                        wire:key="{{$itemId}}"
                                        x-init="ensureState('{{$itemId}}', true)"
                                        class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                                    >
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-stats-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{$itemId}}', true)">
                                                {{ $item['label'] ? 'Chỉ số - '. $item['label'] : 'Chỉ số #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeCounterStat('vi', '{{ $item['id'] }}')" spinner="removeCounterStat('vi', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{$itemId}}') ? 'rotate-180' : ''" @click="toggle('{{$itemId}}', true)"/>
                                        </div>
                                        <div x-show="isOpen('{{$itemId}}')" x-collapse class="p-4 pt-0 space-y-4">
                                            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <x-input label="Nhãn" wire:model="data.vi.counter_stats.{{ $index }}.label" placeholder="Nhập nhãn cho chỉ số" required/>
                                                <x-input label="Giá trị" type="number" min="0" wire:model="data.vi.counter_stats.{{ $index }}.value" required/>
                                                <x-input label="Hậu tố" wire:model="data.vi.counter_stats.{{ $index }}.suffix" required/>

                                                <div x-data="{ showIcons: false, search: '' }" class="relative">
                                                    <div class="flex items-start flex-col justify-center gap-2">
                                                        <label for="data.vi.counter_stats.{{ $index }}.icon" class="mt-3 font-medium">Icon (Heroicons)</label>
                                                        <x-button type="button" @click="showIcons = true"
                                                                  class="h-10 w-full shrink-0 rounded-md bg-gray-50 border border-gray-300 text-primary hover:border-primary hover:bg-primary/10 flex items-center justify-center shadow-sm transition"
                                                                  tooltip="Bấm để chọn Icon" spinner="data.vi.counter_stats.{{ $index }}.icon">
                                                            <div wire:loading.remove wire:target="data.vi.counter_stats.{{ $index }}.icon">
                                                                @if(!empty($item['icon']))
                                                                    <x-icon name="{{ $item['icon'] }}" class="w-7 h-7 text-primary" />
                                                                @else
                                                                    <span class="text-xl text-gray-400">?</span>
                                                                @endif
                                                            </div>
                                                        </x-button>
                                                    </div>

                                                    <template x-teleport="body">
                                                        <div x-show="showIcons" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4">
                                                            <div @click.outside="showIcons = false" x-transition.scale.95 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[85vh]">
                                                                <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-gray-50">
                                                                    <h3 class="font-bold text-gray-800 text-lg">Chọn Icon</h3>
                                                                    <button type="button" @click="showIcons = false" class="btn btn-sm btn-circle btn-ghost text-gray-500">✕</button>
                                                                </div>
                                                                <div class="p-4 border-b border-gray-100">
                                                                    <input type="text" x-model="search" placeholder="🔍 Tìm icon (vd: user, star, heart, book)..." class="input input-bordered w-full" />
                                                                </div>
                                                                <div class="p-4 overflow-y-auto custom-scrollbar bg-white">
                                                                    <div class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                                                                        @foreach($this->popularIcons as $icon)
                                                                            <button type="button"
                                                                                    x-show="search === '' || '{{ $icon }}'.includes(search.toLowerCase())"
                                                                                    @click="$wire.set('data.vi.counter_stats.{{ $index }}.icon', '{{ $icon }}'); showIcons = false"
                                                                                    class="p-2 rounded-xl bg-gray-50 hover:bg-primary/20 text-gray-600 hover:text-primary transition flex flex-col items-center justify-center gap-1 border border-gray-100 hover:border-primary/30"
                                                                                    title="{{ $icon }}">
                                                                                <x-icon name="{{ $icon }}" class="w-7 h-7" />
                                                                            </button>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- TESTIMONIALS (VI) -->
                        <div x-data="{
                            sortableTestimonials: null,
                                 initTestimonialsSortable() {
                                     if (this.sortableTestimonials) this.sortableTestimonials.destroy();
                                     if (!this.$refs.testimonialsVi) return;
                                     this.sortableTestimonials = new Sortable(this.$refs.testimonialsVi, {
                                         animation: 150,
                                         handle: '.drag-testimonials-handle-vi',
                                         onEnd: () => {
                                             let order = Array.from(this.$refs.testimonialsVi.children)
                                                 .map(el => el.dataset.id)
                                                 .filter(Boolean);
                                             $wire.updateTestimonialsOrder('vi', order);
                                         }
                                     });
                                 }
                            }" x-init="$nextTick(() => initTestimonialsSortable()); ensureState('vi-testimonials', true)"
                             class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                        >
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('vi-testimonials')">
                                    Góc nhìn từ doanh nghiệp và cựu sinh viên
                                </button>
                                <x-button label="Thêm lời chia sẻ" icon="o-plus" class="btn-sm bg-emerald-600 text-white" wire:click="addTestimonial('vi')" spinner="addTestimonial('vi')" />
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('vi-testimonials') ? 'rotate-180' : ''" @click="toggle('vi-testimonials')"/>
                            </div>
                            <div x-show="isOpen('vi-testimonials')" x-collapse x-ref="testimonialsVi" class="p-4 space-y-4">
                                @foreach($data['vi']['testimonials'] as $index => $item)
                                    @php $itemId = 'vi-testimonials-' . ($item['id'] ?? $index); @endphp
                                    <div
                                        data-id="{{$item['id']}}"
                                        wire:key="{{$itemId}}"
                                        x-init="ensureState('{{$itemId}}', true)"
                                        class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                                    >
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-testimonials-handle-vi w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{$itemId}}', true)">
                                                {{ $item['name'] ? 'Lời chia sẻ - '. $item['name'] : 'Lời chia sẻ #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTestimonial('vi', '{{ $item['id'] }}')" spinner="removeTestimonial('vi', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{$itemId}}') ? 'rotate-180' : ''" @click="toggle('{{$itemId}}', true)"/>
                                        </div>
                                        <div x-show="isOpen('{{$itemId}}')" x-collapse class="p-4 pt-0 space-y-0">
                                            <div class="grid md:grid-cols-2 gap-x-4">
                                                <x-input label="Họ tên" wire:model="data.vi.testimonials.{{ $index }}.name" placeholder="Nhập họ và tên" required/>
                                                <x-input label="Chức danh" wire:model="data.vi.testimonials.{{ $index }}.role" placeholder="Nhập chức danh" required/>
                                                <div class="md:col-span-2">
                                                    <x-textarea label="Nội dung" wire:model="data.vi.testimonials.{{ $index }}.content" rows="4" placeholder="Nhập nội dung chia sẻ" required/>
                                                </div>
                                                <div class="md:col-span-2 space-y-2 mt-2">
                                                    <label class="font-medium text-sm">Ảnh đại diện</label>
                                                    <input
                                                        wire:key="vi-testimonial-avatar-{{ $item['id'] }}"
                                                        type="file"
                                                        wire:model="data.vi.testimonials.{{ $index }}.avatar_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center group">
                                                        <div wire:loading.flex wire:target="data.vi.testimonials.{{ $index }}.avatar_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'avatar_file') || data_get($item, 'avatar'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'avatar_file'), data_get($item, 'avatar')) }}" class="h-32 w-32 rounded-full object-cover" alt="Ảnh đại diện" />
                                                            <x-button type="button"
                                                                      wire:click="removeTestimonialImage('vi', {{ $index }})"
                                                                      class="absolute top-2 right-2 z-5 btn btn-circle btn-xs btn-error text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                                                      tooltip-left="Xóa ảnh" icon="o-x-mark" spinner="removeTestimonialImage('vi', {{ $index }})">
                                                            </x-button>
                                                        @else
                                                            <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-tab>

                <!-- ============================ TAB TIẾNG ANH ============================ -->
                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <div class="space-y-5">

                        <!-- LỐI TẮT NHANH (EN) -->
                        <div x-data="{
            sortableQuickLinks: null,
                 initQuickLinksTable() {
                     if (this.sortableQuickLinks) this.sortableQuickLinks.destroy();
                     if (!this.$refs.quickLinksEn) return;
                     this.sortableQuickLinks = new Sortable(this.$refs.quickLinksEn, {
                         animation: 150,
                         handle: '.drag-quick-links-handle-en',
                         onEnd: () => {
                             let order = Array.from(this.$refs.quickLinksEn.children)
                                 .map(el => el.dataset.id)
                                 .filter(Boolean);
                             $wire.updateQuickLinksOrder('en', order);
                         }
                     });
                 }
            }"
                             x-init="$nextTick(() => initQuickLinksTable()); ensureState('en-quick-links', true)"
                             class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                        >
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('en-quick-links')">
                                    Khối lối tắt nhanh (Tiếng Anh)
                                </button>
                                <x-button icon="o-plus" label="Thêm lối tắt" class="btn-sm bg-emerald-600 text-white" wire:click="addQuickLink('en')" spinner="addQuickLink('en')" />
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('en-quick-links') ? 'rotate-180' : ''" @click="toggle('en-quick-links')"/>
                            </div>
                            <div x-show="isOpen('en-quick-links')" x-collapse x-ref="quickLinksEn" class="p-4 space-y-4">
                                @foreach($data['en']['quick_links'] as $index => $item)
                                    @php $itemId = 'en-quick-link-' . ($item['id'] ?? $index); @endphp
                                    <div data-id="{{ $item['id'] }}"
                                         wire:key="en-quick-link-{{ $item['id'] ?? $index }}"
                                         x-init="ensureState('{{ $itemId }}', false)"
                                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-quick-links-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{ $itemId }}')">
                                                {{ $item['app'] ? 'Lối tắt - '. $item['app'] : 'Lối tắt #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeQuickLink('en', '{{ $item['id'] }}')" spinner="removeQuickLink('en', '{{ $item['id'] }}')"/>
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{ $itemId }}') ? 'rotate-180' : ''" @click="toggle('{{ $itemId }}')"/>
                                        </div>
                                        <div x-show="isOpen('{{ $itemId }}')" x-collapse class="p-4 pt-0 space-y-0">
                                            <div class="grid md:grid-cols-1 lg:grid-cols-2 gap-x-4">
                                                <div class="lg:col-span-2">
                                                    <x-input label="Tiêu đề lối tắt" wire:model="data.en.quick_links.{{ $index }}.app" placeholder="Nhập tiêu đề lối tắt" required/>
                                                </div>
                                                <x-input label="Mô tả" wire:model="data.en.quick_links.{{ $index }}.desc" placeholder="Nhập mô tả (tooltip)"/>
                                                <x-input label="Đường dẫn" wire:model="data.en.quick_links.{{ $index }}.link" required placeholder="/duong-dan hoặc https://example.com hoặc ###"/>
                                                <div class="lg:col-span-2 space-y-2 mt-2">
                                                    <label class="font-medium text-sm">Ảnh icon</label>
                                                    <input
                                                        wire:key="en-quick-link-image-{{ $item['id'] }}"
                                                        type="file"
                                                        wire:model="data.en.quick_links.{{ $index }}.img_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center group">
                                                        <div wire:loading.flex wire:target="data.en.quick_links.{{ $index }}.img_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'img_file') || data_get($item, 'img'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'img_file'), data_get($item, 'img')) }}" class="h-28 rounded-lg object-cover" alt="Ảnh icon" />
                                                            <x-button type="button"
                                                                      wire:click="removeQuickLinkImage('en', {{ $index }})"
                                                                      class="absolute top-2 right-2 z-5 btn btn-circle btn-xs btn-error text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                                                      tooltip-left="Xóa ảnh" icon="o-x-mark" spinner="removeQuickLinkImage('en', {{ $index }})">
                                                            </x-button>
                                                        @else
                                                            <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- CHƯƠNG TRÌNH ĐÀO TẠO (EN) -->
                        <div x-data="{
            sortableTraining: null,
                 initTrainingSortable() {
                     if (this.sortableTraining) this.sortableTraining.destroy();
                     if (!this.$refs.trainingEn) return;
                     this.sortableTraining = new Sortable(this.$refs.trainingEn, {
                         animation: 150,
                         handle: '.drag-training-handle-en',
                         onEnd: () => {
                             let order = Array.from(this.$refs.trainingEn.children)
                                 .map(el => el.dataset.id)
                                 .filter(Boolean);
                             $wire.updateTrainingOrder('en', order);
                         }
                     });
                 }
            }"
                             x-init="$nextTick(() => initTrainingSortable()); ensureState('en-training', true)"
                             class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('en-training')">
                                    Chương trình đào tạo (Tiếng Anh)
                                </button>
                                <x-button label="Thêm thẻ mới" icon="o-plus" class="btn-sm bg-emerald-600 text-white" wire:click="addTrainingProgram('en')" spinner="addTrainingProgram('en')"/>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('en-training') ? 'rotate-180' : ''" @click="toggle('en-training')"/>
                            </div>
                            <div x-show="isOpen('en-training')" x-collapse x-ref="trainingEn" class="p-4 space-y-4">
                                @foreach($data['en']['training_programs'] as $index => $item)
                                    @php $itemId = 'en-training-' . ($item['id'] ?? $index); @endphp
                                    <div
                                        data-id="{{$item['id']}}"
                                        wire:key="{{$itemId}}"
                                        x-init="ensureState('{{$itemId}}', true)" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-training-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{$itemId}}', true)">
                                                {{ $item['title'] ? 'Thẻ - '. $item['title'] : 'Thẻ #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTrainingProgram('en', '{{ $item['id'] }}')" spinner="removeTrainingProgram('en', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{$itemId}}') ? 'rotate-180' : ''" @click="toggle('{{$itemId}}', true)"/>
                                        </div>
                                        <div x-show="isOpen('{{$itemId}}')" x-collapse class="p-4 pt-0 space-y-4">
                                            <div class="grid md:grid-cols-2 gap-x-4">
                                                <div class="md:col-span-2">
                                                    <x-input label="Tiêu đề thẻ" wire:model="data.en.training_programs.{{ $index }}.title" required placeholder="Nhập tiêu đề thẻ"/>
                                                    <x-textarea label="Mô tả" wire:model="data.en.training_programs.{{ $index }}.description" rows="4" required placeholder="Nhập mô tả"/>
                                                </div>
                                                <x-input label="Link chi tiết" wire:model="data.en.training_programs.{{ $index }}.detail_url" placeholder="/duong-dan hoặc https://example.com hoặc ###"/>
                                                <x-input label="Link lộ trình" wire:model="data.en.training_programs.{{ $index }}.roadmap_url" placeholder="/duong-dan hoặc https://example.com hoặc ###"/>
                                                <div class="md:col-span-2 space-y-2 mt-2">
                                                    <label class="font-medium text-sm">Ảnh chương trình</label>
                                                    <input
                                                        wire:key="en-training-image-{{ $item['id'] }}"
                                                        type="file"
                                                        wire:model="data.en.training_programs.{{ $index }}.image_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center group">
                                                        <div wire:loading.flex wire:target="data.en.training_programs.{{ $index }}.image_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'image_file') || data_get($item, 'image'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'image_file'), data_get($item, 'image')) }}" class="h-34 rounded-lg object-cover" alt="Ảnh chương trình" />
                                                            <x-button type="button"
                                                                      wire:click="removeTrainingProgramImage('en', {{ $index }})"
                                                                      class="absolute top-2 right-2 z-5 btn btn-circle btn-xs btn-error text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                                                      tooltip-left="Xóa ảnh" icon="o-x-mark" spinner="removeTrainingProgramImage('en', {{ $index }})">
                                                            </x-button>
                                                        @else
                                                            <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- CHỈ SỐ THỐNG KÊ (EN) -->
                        <div x-data="{
            sortableCounterStar: null,
                 initStatsSortable() {
                     if (this.sortableCounterStar) this.sortableCounterStar.destroy();
                     if (!this.$refs.counterStarEn) return;
                     this.sortableCounterStar = new Sortable(this.$refs.counterStarEn, {
                         animation: 150,
                         handle: '.drag-stats-handle-en',
                         onEnd: () => {
                             let order = Array.from(this.$refs.counterStarEn.children)
                                 .map(el => el.dataset.id)
                                 .filter(Boolean);
                             $wire.updateCounterStarOrder('en', order);
                         }
                     });
                 }
            }" x-init="$nextTick(() => initStatsSortable()); ensureState('en-stats', true)" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('en-stats')">
                                    Những con số ấn tượng (Tiếng Anh)
                                </button>
                                <x-button label="Thêm chỉ số" icon="o-plus" class="btn-sm bg-emerald-600 text-white" wire:click="addCounterStat('en')" spinner="addCounterStat('en')"/>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('en-stats') ? 'rotate-180' : ''" @click="toggle('en-stats')"/>
                            </div>
                            <div x-show="isOpen('en-stats')" x-collapse x-ref="counterStarEn" class="p-4 space-y-4">
                                @foreach($data['en']['counter_stats'] as $index => $item)
                                    @php $itemId = 'en-counter-star-' . ($item['id'] ?? $index); @endphp
                                    <div
                                        data-id="{{$item['id']}}"
                                        wire:key="{{$itemId}}"
                                        x-init="ensureState('{{$itemId}}', true)"
                                        class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                                    >
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-stats-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{$itemId}}', true)">
                                                {{ $item['label'] ? 'Chỉ số - '. $item['label'] : 'Chỉ số #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeCounterStat('en', '{{ $item['id'] }}')" spinner="removeCounterStat('en', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{$itemId}}') ? 'rotate-180' : ''" @click="toggle('{{$itemId}}', true)"/>
                                        </div>
                                        <div x-show="isOpen('{{$itemId}}')" x-collapse class="p-4 pt-0 space-y-4">
                                            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <x-input label="Nhãn" wire:model="data.en.counter_stats.{{ $index }}.label" placeholder="Nhập nhãn cho chỉ số" required/>
                                                <x-input label="Giá trị" type="number" min="0" wire:model="data.en.counter_stats.{{ $index }}.value" required/>
                                                <x-input label="Hậu tố" wire:model="data.en.counter_stats.{{ $index }}.suffix" required/>

                                                <div x-data="{ showIcons: false, search: '' }" class="relative">
                                                    <div class="flex items-start flex-col justify-center gap-2">
                                                        <label for="data.en.counter_stats.{{ $index }}.icon" class="mt-3 font-medium">Icon (Heroicons)</label>
                                                        <x-button type="button" @click="showIcons = true"
                                                                  class="h-10 w-full shrink-0 rounded-md bg-gray-50 border border-gray-300 text-primary hover:border-primary hover:bg-primary/10 flex items-center justify-center shadow-sm transition"
                                                                  tooltip="Bấm để chọn Icon" spinner="data.en.counter_stats.{{ $index }}.icon">
                                                            <div wire:loading.remove wire:target="data.en.counter_stats.{{ $index }}.icon">
                                                                @if(!empty($item['icon']))
                                                                    <x-icon name="{{ $item['icon'] }}" class="w-7 h-7 text-primary" />
                                                                @else
                                                                    <span class="text-xl text-gray-400">?</span>
                                                                @endif
                                                            </div>
                                                        </x-button>
                                                    </div>

                                                    <template x-teleport="body">
                                                        <div x-show="showIcons" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4">
                                                            <div @click.outside="showIcons = false" x-transition.scale.95 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[85vh]">
                                                                <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-gray-50">
                                                                    <h3 class="font-bold text-gray-800 text-lg">Chọn Icon</h3>
                                                                    <button type="button" @click="showIcons = false" class="btn btn-sm btn-circle btn-ghost text-gray-500">✕</button>
                                                                </div>
                                                                <div class="p-4 border-b border-gray-100">
                                                                    <input type="text" x-model="search" placeholder="🔍 Tìm icon (vd: user, star, heart, book)..." class="input input-bordered w-full" />
                                                                </div>
                                                                <div class="p-4 overflow-y-auto custom-scrollbar bg-white">
                                                                    <div class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                                                                        @foreach($this->popularIcons as $icon)
                                                                            <button type="button"
                                                                                    x-show="search === '' || '{{ $icon }}'.includes(search.toLowerCase())"
                                                                                    @click="$wire.set('data.en.counter_stats.{{ $index }}.icon', '{{ $icon }}'); showIcons = false"
                                                                                    class="p-2 rounded-xl bg-gray-50 hover:bg-primary/20 text-gray-600 hover:text-primary transition flex flex-col items-center justify-center gap-1 border border-gray-100 hover:border-primary/30"
                                                                                    title="{{ $icon }}">
                                                                                <x-icon name="{{ $icon }}" class="w-7 h-7" />
                                                                            </button>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- LỜI CHIA SẺ (EN) -->
                        <div x-data="{
            sortableTestimonials: null,
                 initTestimonialsSortable() {
                     if (this.sortableTestimonials) this.sortableTestimonials.destroy();
                     if (!this.$refs.testimonialsEn) return;
                     this.sortableTestimonials = new Sortable(this.$refs.testimonialsEn, {
                         animation: 150,
                         handle: '.drag-testimonials-handle-en',
                         onEnd: () => {
                             let order = Array.from(this.$refs.testimonialsEn.children)
                                 .map(el => el.dataset.id)
                                 .filter(Boolean);
                             $wire.updateTestimonialsOrder('en', order);
                         }
                     });
                 }
            }" x-init="$nextTick(() => initTestimonialsSortable()); ensureState('en-testimonials', true)"
                             class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                        >
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('en-testimonials')">
                                    Góc nhìn từ doanh nghiệp và cựu sinh viên (Tiếng Anh)
                                </button>
                                <x-button label="Thêm lời chia sẻ" icon="o-plus" class="btn-sm bg-emerald-600 text-white" wire:click="addTestimonial('en')" spinner="addTestimonial('en')" />
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('en-testimonials') ? 'rotate-180' : ''" @click="toggle('en-testimonials')"/>
                            </div>
                            <div x-show="isOpen('en-testimonials')" x-collapse x-ref="testimonialsEn" class="p-4 space-y-4">
                                @foreach($data['en']['testimonials'] as $index => $item)
                                    @php $itemId = 'en-testimonials-' . ($item['id'] ?? $index); @endphp
                                    <div
                                        data-id="{{$item['id']}}"
                                        wire:key="{{$itemId}}"
                                        x-init="ensureState('{{$itemId}}', true)"
                                        class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden"
                                    >
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <x-icon name="o-bars-3" class="drag-testimonials-handle-en w-5 h-5 text-gray-400 cursor-move hover:text-gray-700"/>
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="toggle('{{$itemId}}', true)">
                                                {{ $item['name'] ? 'Lời chia sẻ - '. $item['name'] : 'Lời chia sẻ #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTestimonial('en', '{{ $item['id'] }}')" spinner="removeTestimonial('en', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="isOpen('{{$itemId}}') ? 'rotate-180' : ''" @click="toggle('{{$itemId}}', true)"/>
                                        </div>
                                        <div x-show="isOpen('{{$itemId}}')" x-collapse class="p-4 pt-0 space-y-0">
                                            <div class="grid md:grid-cols-2 gap-x-4">
                                                <x-input label="Họ tên" wire:model="data.en.testimonials.{{ $index }}.name" placeholder="Nhập họ và tên" required/>
                                                <x-input label="Chức danh" wire:model="data.en.testimonials.{{ $index }}.role" placeholder="Nhập chức danh" required/>
                                                <div class="md:col-span-2">
                                                    <x-textarea label="Nội dung" wire:model="data.en.testimonials.{{ $index }}.content" rows="4" placeholder="Nhập nội dung chia sẻ" required/>
                                                </div>
                                                <div class="md:col-span-2 space-y-2 mt-2">
                                                    <label class="font-medium text-sm">Ảnh đại diện</label>
                                                    <input
                                                        wire:key="en-testimonial-avatar-{{ $item['id'] }}"
                                                        type="file"
                                                        wire:model="data.en.testimonials.{{ $index }}.avatar_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center group">
                                                        <div wire:loading.flex wire:target="data.en.testimonials.{{ $index }}.avatar_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'avatar_file') || data_get($item, 'avatar'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'avatar_file'), data_get($item, 'avatar')) }}" class="h-32 w-32 rounded-full object-cover" alt="Ảnh đại diện" />
                                                            <x-button type="button"
                                                                      wire:click="removeTestimonialImage('en', {{ $index }})"
                                                                      class="absolute top-2 right-2 z-5 btn btn-circle btn-xs btn-error text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                                                      tooltip-left="Xóa ảnh" icon="o-x-mark" spinner="removeTestimonialImage('en', {{ $index }})">
                                                            </x-button>
                                                        @else
                                                            <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-tab>
            </x-tabs>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="Hành động" shadow separator progress-indicator="save">
            <div class="space-y-2">
                <x-button label="Lưu cấu hình" class="bg-primary text-white w-full" wire:click="save" wire:loading.attr="disabled" wire:target="save" spinner />
                <x-button label="Xem trước" wire:click="preview" wire:loading.attr="disabled" wire:target="preview" class="bg-success text-white w-full" spinner />
            </div>
        </x-card>
    </div>
</div>
