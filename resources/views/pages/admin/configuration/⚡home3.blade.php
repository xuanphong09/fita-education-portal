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

    /**
     * Method hiển thị media - hỗ trợ cả file đang upload và file đã lưu
     * @param mixed $uploadField - File đang upload (UploadedFile hoặc null)
     * @param string|null $pathField - Đường dẫn file đã lưu
     * @param string $fallback - URL dự phòng nếu không có ảnh
     * @return string - URL để hiển thị ảnh
     */
    protected function displayMedia($uploadField, ?string $pathField, string $fallback = ''): string
    {
        // Ưu tiên file đang upload (preview)
        if (!empty($uploadField) && $this->isUpload($uploadField)) {
            return $uploadField->temporaryUrl();
        }

        // Sau đó dùng file đã lưu
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

                    if ($oldPath && $newPath && $oldPath !== $newPath && !$this->isMediaPathStillReferenced($oldPath, $locale, $section, $id, $field)) {
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
            'data.vi.quick_links.*.link' => 'required|string|max:255',
            'data.vi.quick_links.*.color' => 'required|string|max:50',
            'data.vi.quick_links.*.img' => 'nullable|string|max:255',
            'data.vi.quick_links.*.img_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'data.en.quick_links' => 'array',
            'data.en.quick_links.*.app' => 'required|string|max:255',
            'data.en.quick_links.*.desc' => 'required|string|max:255',
            'data.en.quick_links.*.link' => 'required|string|max:255',
            'data.en.quick_links.*.color' => 'required|string|max:50',
            'data.en.quick_links.*.img' => 'nullable|string|max:255',
            'data.en.quick_links.*.img_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'data.vi.training_programs' => 'array',
            'data.vi.training_programs.*.title' => 'required|string|max:255',
            'data.vi.training_programs.*.description' => 'required|string',
            'data.vi.training_programs.*.detail_url' => 'required|string|max:255',
            'data.vi.training_programs.*.roadmap_url' => 'required|string|max:255',
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
            $this->syncToPreviewCache();
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
    }

    public function removeQuickLink(string $locale, string $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa ô lối tắt này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveQuickLink',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveQuickLink')]
    public function confirmRemoveQuickLink(array $payload): void
    {
        [$locale, $id] = $payload;
        $item = collect($this->data[$locale]['quick_links'] ?? [])->firstWhere('id', $id);
        if ($item && !$this->isMediaPathStillReferenced($item['img'] ?? '', $locale, 'quick_links', $id, 'img')) {
            $this->deleteStoredMedia($item['img'] ?? null);
        }
        $this->data[$locale]['quick_links'] = collect($this->data[$locale]['quick_links'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $id)
            ->values()
            ->toArray();
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
    }

    public function removeTrainingProgram(string $locale, string $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa chương trình đào tạo này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveTrainingProgram',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveTrainingProgram')]
    public function confirmRemoveTrainingProgram(array $payload): void
    {
        [$locale, $id] = $payload;
        $item = collect($this->data[$locale]['training_programs'] ?? [])->firstWhere('id', $id);
        if ($item && !$this->isMediaPathStillReferenced($item['image'] ?? '', $locale, 'training_programs', $id, 'image')) {
            $this->deleteStoredMedia($item['image'] ?? null);
        }
        $this->data[$locale]['training_programs'] = collect($this->data[$locale]['training_programs'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $id)
            ->values()
            ->toArray();
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
    }

    public function removeCounterStat(string $locale, string $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa chỉ số thống kê này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveCounterStat',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveCounterStat')]
    public function confirmRemoveCounterStat(array $payload): void
    {
        [$locale, $id] = $payload;
        $this->data[$locale]['counter_stats'] = collect($this->data[$locale]['counter_stats'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $id)
            ->values()
            ->toArray();
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
    }

    protected function persistUploads(): void
    {
        foreach (['vi', 'en'] as $locale) {
            foreach ($this->data[$locale]['quick_links'] ?? [] as $index => $item) {
                if (!empty($item['img_file']) && $this->isUpload($item['img_file'])) {
                    $this->data[$locale]['quick_links'][$index]['img'] = $item['img_file']->store('uploads/home3/quick-links', 'public');
                }
                $this->data[$locale]['quick_links'][$index]['img_file'] = null;
            }

            foreach ($this->data[$locale]['training_programs'] ?? [] as $index => $item) {
                if (!empty($item['image_file']) && $this->isUpload($item['image_file'])) {
                    $this->data[$locale]['training_programs'][$index]['image'] = $item['image_file']->store('uploads/home3/training-programs', 'public');
                }
                $this->data[$locale]['training_programs'][$index]['image_file'] = null;
            }

            foreach ($this->data[$locale]['testimonials'] ?? [] as $index => $item) {
                if (!empty($item['avatar_file']) && $this->isUpload($item['avatar_file'])) {
                    $this->data[$locale]['testimonials'][$index]['avatar'] = $item['avatar_file']->store('uploads/home3/testimonials', 'public');
                }
                $this->data[$locale]['testimonials'][$index]['avatar_file'] = null;
            }
        }
    }

    public function removeTestimonial(string $locale, string $id): void
    {
        $this->dispatch('modal:confirm', [
            'title' => 'Bạn có chắc muốn xóa lời chia sẻ này không?',
            'icon' => 'question',
            'confirmButtonText' => 'Xác nhận',
            'cancelButtonText' => 'Hủy',
            'method' => 'confirmRemoveTestimonial',
            'id' => [$locale, $id],
        ]);
    }

    #[On('confirmRemoveTestimonial')]
    public function confirmRemoveTestimonial(array $payload): void
    {
        [$locale, $id] = $payload;
        $item = collect($this->data[$locale]['testimonials'] ?? [])->firstWhere('id', $id);
        if ($item && !$this->isMediaPathStillReferenced($item['avatar'] ?? '', $locale, 'testimonials', $id, 'avatar')) {
            $this->deleteStoredMedia($item['avatar'] ?? null);
        }
        $this->data[$locale]['testimonials'] = collect($this->data[$locale]['testimonials'] ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $id)
            ->values()
            ->toArray();
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

<div>
    <x-slot:title>Cấu hình trang chủ </x-slot:title>
    <x-slot:breadcrumb><span>Cấu hình trang chủ </span></x-slot:breadcrumb>
    <x-header title="Cấu hình trang chủ " class="pb-3 mb-5! border-b border-gray-300">
        <x-slot:middle class="justify-end!">
            <div class="flex flex-wrap gap-2 items-center">
                <x-button label="Xem trang" link="{{ route('client.home') }}" external class="bg-warning text-white" />
                <x-button label="Xem trước" wire:click="preview" wire:loading.attr="disabled" wire:target="preview" class="bg-success text-white" spinner />
            </div>
        </x-slot:middle>
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
                <x-tab name="tab-vi" label="Tiếng Việt" class="pt-2!">
                    <div class="space-y-5">
{{--                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">--}}
{{--                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">--}}
{{--                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">--}}
{{--                                    Tiêu đề các khối--}}
{{--                                </button>--}}
{{--                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>--}}
{{--                            </div>--}}
{{--                            <div x-show="open" x-collapse class="p-4 space-y-4">--}}
{{--                                <div class="grid md:grid-cols-2 gap-4">--}}
{{--                                    <x-input label="Tin tức và sự kiện" wire:model.live.debounce.300ms="data.vi.section_titles.news" />--}}
{{--                                    <x-input label="Chương trình đào tạo" wire:model.live.debounce.300ms="data.vi.section_titles.training" />--}}
{{--                                    <x-input label="Mạng lưới đối tác doanh nghiệp" wire:model.live.debounce.300ms="data.vi.section_titles.partners" />--}}
{{--                                    <x-input label="Góc nhìn từ doanh nghiệp và cựu sinh viên" wire:model.live.debounce.300ms="data.vi.section_titles.testimonials" />--}}
{{--                                    <x-input label="Thư viện ảnh" wire:model.live.debounce.300ms="data.vi.section_titles.gallery" />--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Khối lối tắt nhanh
                                </button>
                                <x-button
                                    icon="o-plus"
                                    label="Thêm lối tắt"
                                    class="btn-sm bg-emerald-600 text-white"
                                    wire:click="addQuickLink('vi')"
                                    spinner="addQuickLink('vi')"
                                />
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['vi']['quick_links'] as $index => $item)
                                    <div data-id="{{ $item['id'] }}"
                                         wire:key="vi-quick-link-{{ $item['id'] ?? $index }}"
                                         x-data="{ openQuickLink: true }"
                                         class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                                        <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                            <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="openQuickLink = !openQuickLink">
                                                {{ $item['app'] ? 'Lối tắt - '. $item['app'] : 'Lối tắt #' . ($index + 1) }}
                                            </button>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeQuickLink('vi', '{{ $item['id'] }}')" />
                                            <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="openQuickLink ? 'rotate-180' : ''" @click="openQuickLink = !openQuickLink"/>
                                        </div>
                                        <div x-show="openQuickLink" x-collapse class="p-4 space-y-4">
                                            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                <x-input label="Tên ứng dụng" wire:model.live.debounce.300ms="data.vi.quick_links.{{ $index }}.app" />
                                                <x-input label="Mô tả" wire:model.live.debounce.300ms="data.vi.quick_links.{{ $index }}.desc" />
                                                <x-input label="Đường dẫn" wire:model.live.debounce.300ms="data.vi.quick_links.{{ $index }}.link" />
                                                <div class="lg:col-span-3 space-y-2">
                                                    <label class="font-medium text-sm">Ảnh icon</label>
                                                    <input
                                                        wire:key="vi-quick-link-image-{{ $item['id'] }}-{{ $loop->index }}"
                                                        type="file"
                                                        wire:model="data.vi.quick_links.{{ $index }}.img_file"
                                                        accept="image/png, image/jpeg, image/webp"
                                                        class="file-input file-input-bordered w-full"
                                                    >
                                                    <div class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                                                        <div wire:loading.flex wire:target="data.vi.quick_links.{{ $index }}.img_file"
                                                             class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                                            <span class="loading loading-spinner text-primary"></span>
                                                            <span class="mt-2 text-xs text-gray-600 font-medium">Đang tải ảnh...</span>
                                                        </div>
                                                        @if(data_get($item, 'img_file') || data_get($item, 'img'))
                                                            <img src="{{ $this->displayMedia(data_get($item, 'img_file'), data_get($item, 'img')) }}"
                                                                 class="h-28 rounded-lg object-cover"
                                                                 alt="Ảnh icon" />
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

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Chương trình đào tạo
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['vi']['training_programs'] as $index => $item)
                                    <div wire:key="vi-training-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Chương trình #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTrainingProgram('vi', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <x-input label="Tên chương trình" wire:model.live.debounce.300ms="data.vi.training_programs.{{ $index }}.title" />
                                            <x-input label="Link chi tiết" wire:model.live.debounce.300ms="data.vi.training_programs.{{ $index }}.detail_url" />
                                            <x-input label="Link lộ trình" wire:model.live.debounce.300ms="data.vi.training_programs.{{ $index }}.roadmap_url" />
                                            <x-textarea label="Mô tả" wire:model.live.debounce.300ms="data.vi.training_programs.{{ $index }}.description" class="md:col-span-2" rows="4" />
                                            <div class="md:col-span-2 space-y-2">
                                                <label class="font-medium text-sm">Ảnh chương trình</label>
                                                <input
                                                    wire:key="vi-training-image-{{ $item['id'] }}-{{ $loop->index }}"
                                                    type="file"
                                                    wire:model="data.vi.training_programs.{{ $index }}.image_file"
                                                    accept="image/png, image/jpeg, image/webp"
                                                    class="file-input file-input-bordered w-full"
                                                >
                                                <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                                                    @if(data_get($item, 'image_file') || data_get($item, 'image'))
                                                        <img src="{{ $this->displayMedia(data_get($item, 'image_file'), data_get($item, 'image')) }}" class="h-32 w-full rounded-lg object-cover" alt="Ảnh chương trình" />
                                                    @else
                                                        <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Thêm chương trình" icon="o-plus" class="btn-primary" wire:click="addTrainingProgram('vi')" />
                            </div>
                        </div>

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Chỉ số thống kê
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['vi']['counter_stats'] as $index => $item)
                                    <div wire:key="vi-stat-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Chỉ số #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeCounterStat('vi', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <x-input label="Nhãn" wire:model.live.debounce.300ms="data.vi.counter_stats.{{ $index }}.label" />
                                            <x-input label="Giá trị" type="number" min="0" wire:model.live.debounce.300ms="data.vi.counter_stats.{{ $index }}.value" />
                                            <x-input label="Hậu tố" wire:model.live.debounce.300ms="data.vi.counter_stats.{{ $index }}.suffix" />
                                            <x-input label="Icon" wire:model.live.debounce.300ms="data.vi.counter_stats.{{ $index }}.icon" />
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Thêm chỉ số" icon="o-plus" class="btn-primary" wire:click="addCounterStat('vi')" />
                            </div>
                        </div>

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Cảm nhận từ doanh nghiệp và cựu sinh viên
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['vi']['testimonials'] as $index => $item)
                                    <div wire:key="vi-testimonial-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Lời chia sẻ #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTestimonial('vi', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <x-input label="Họ tên" wire:model.live.debounce.300ms="data.vi.testimonials.{{ $index }}.name" />
                                            <x-input label="Chức danh" wire:model.live.debounce.300ms="data.vi.testimonials.{{ $index }}.role" />
                                            <x-textarea label="Nội dung" wire:model.live.debounce.300ms="data.vi.testimonials.{{ $index }}.content" class="md:col-span-2" rows="4" />
                                            <div class="md:col-span-2 space-y-2">
                                                <label class="font-medium text-sm">Ảnh đại diện</label>
                                                <input
                                                    wire:key="vi-testimonial-avatar-{{ $item['id'] }}-{{ $loop->index }}"
                                                    type="file"
                                                    wire:model="data.vi.testimonials.{{ $index }}.avatar_file"
                                                    accept="image/png, image/jpeg, image/webp"
                                                    class="file-input file-input-bordered w-full"
                                                >
                                                <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                                                    @if(data_get($item, 'avatar_file') || data_get($item, 'avatar'))
                                                        <img src="{{ $this->displayMedia(data_get($item, 'avatar_file'), data_get($item, 'avatar')) }}" class="h-32 w-32 rounded-full object-cover" alt="Ảnh đại diện" />
                                                    @else
                                                        <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Thêm lời chia sẻ" icon="o-plus" class="btn-primary" wire:click="addTestimonial('vi')" />
                            </div>
                        </div>
                    </div>
                </x-tab>

                <x-tab name="tab-en" label="Tiếng Anh" class="pt-2!">
                    <div class="space-y-5">
                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Section titles
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                <div class="grid md:grid-cols-2 gap-4">
                                    <x-input label="News and events" wire:model.live.debounce.300ms="data.en.section_titles.news" />
                                    <x-input label="Training programs" wire:model.live.debounce.300ms="data.en.section_titles.training" />
                                    <x-input label="Business partners" wire:model.live.debounce.300ms="data.en.section_titles.partners" />
                                    <x-input label="Testimonials" wire:model.live.debounce.300ms="data.en.section_titles.testimonials" />
                                    <x-input label="Photo library" wire:model.live.debounce.300ms="data.en.section_titles.gallery" />
                                </div>
                            </div>
                        </div>

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Quick links
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['en']['quick_links'] as $index => $item)
                                    <div wire:key="en-quick-link-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Quick link #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeQuickLink('en', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <x-input label="App name" wire:model.live.debounce.300ms="data.en.quick_links.{{ $index }}.app" />
                                            <x-input label="Description" wire:model.live.debounce.300ms="data.en.quick_links.{{ $index }}.desc" />
                                            <x-input label="Link" wire:model.live.debounce.300ms="data.en.quick_links.{{ $index }}.link" />
                                            <x-input label="Color" wire:model.live.debounce.300ms="data.en.quick_links.{{ $index }}.color" />
                                            <div class="lg:col-span-3 space-y-2">
                                                <label class="font-medium text-sm">Icon image</label>
                                                <input
                                                    wire:key="en-quick-link-image-{{ $item['id'] }}-{{ $loop->index }}"
                                                    type="file"
                                                    wire:model="data.en.quick_links.{{ $index }}.img_file"
                                                    accept="image/png, image/jpeg, image/webp"
                                                    class="file-input file-input-bordered w-full"
                                                >
                                                <div class="relative min-h-32 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                                                    @if(data_get($item, 'img_file') || data_get($item, 'img'))
                                                        <img src="{{ $this->displayMedia(data_get($item, 'img_file'), data_get($item, 'img')) }}" class="h-28 w-28 rounded-lg object-cover" alt="Icon image" />
                                                    @else
                                                        <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Add quick link" icon="o-plus" class="btn-primary" wire:click="addQuickLink('en')" />
                            </div>
                        </div>

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Training programs
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['en']['training_programs'] as $index => $item)
                                    <div wire:key="en-training-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Program #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTrainingProgram('en', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <x-input label="Title" wire:model.live.debounce.300ms="data.en.training_programs.{{ $index }}.title" />
                                            <x-input label="Detail URL" wire:model.live.debounce.300ms="data.en.training_programs.{{ $index }}.detail_url" />
                                            <x-input label="Roadmap URL" wire:model.live.debounce.300ms="data.en.training_programs.{{ $index }}.roadmap_url" />
                                            <x-textarea label="Description" wire:model.live.debounce.300ms="data.en.training_programs.{{ $index }}.description" class="md:col-span-2" rows="4" />
                                            <div class="md:col-span-2 space-y-2">
                                                <label class="font-medium text-sm">Program image</label>
                                                <input
                                                    wire:key="en-training-image-{{ $item['id'] }}-{{ $loop->index }}"
                                                    type="file"
                                                    wire:model="data.en.training_programs.{{ $index }}.image_file"
                                                    accept="image/png, image/jpeg, image/webp"
                                                    class="file-input file-input-bordered w-full"
                                                >
                                                <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                                                    @if(data_get($item, 'image_file') || data_get($item, 'image'))
                                                        <img src="{{ $this->displayMedia(data_get($item, 'image_file'), data_get($item, 'image')) }}" class="h-32 w-full rounded-lg object-cover" alt="Program image" />
                                                    @else
                                                        <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Add program" icon="o-plus" class="btn-primary" wire:click="addTrainingProgram('en')" />
                            </div>
                        </div>

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Statistics
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['en']['counter_stats'] as $index => $item)
                                    <div wire:key="en-stat-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Statistic #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeCounterStat('en', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <x-input label="Label" wire:model.live.debounce.300ms="data.en.counter_stats.{{ $index }}.label" />
                                            <x-input label="Value" type="number" min="0" wire:model.live.debounce.300ms="data.en.counter_stats.{{ $index }}.value" />
                                            <x-input label="Suffix" wire:model.live.debounce.300ms="data.en.counter_stats.{{ $index }}.suffix" />
                                            <x-input label="Icon" wire:model.live.debounce.300ms="data.en.counter_stats.{{ $index }}.icon" />
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Add statistic" icon="o-plus" class="btn-primary" wire:click="addCounterStat('en')" />
                            </div>
                        </div>

                        <div x-data="{ open: true }" class="border border-gray-200 rounded-lg bg-white shadow-sm overflow-hidden">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 border-b border-gray-100">
                                <button type="button" class="flex-1 text-left font-semibold text-sm text-gray-700 hover:text-primary transition" @click="open = !open">
                                    Testimonials
                                </button>
                                <x-icon name="o-chevron-down" class="w-5 h-5 cursor-pointer transition-transform" x-bind:class="open ? 'rotate-180' : ''" @click="open = !open"/>
                            </div>
                            <div x-show="open" x-collapse class="p-4 space-y-4">
                                @foreach($data['en']['testimonials'] as $index => $item)
                                    <div wire:key="en-testimonial-{{ $item['id'] ?? $index }}" class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 space-y-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="font-semibold text-gray-700">Testimonial #{{ $index + 1 }}</h4>
                                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeTestimonial('en', '{{ $item['id'] }}')" />
                                        </div>
                                        <div class="grid md:grid-cols-2 gap-4">
                                            <x-input label="Name" wire:model.live.debounce.300ms="data.en.testimonials.{{ $index }}.name" />
                                            <x-input label="Role" wire:model.live.debounce.300ms="data.en.testimonials.{{ $index }}.role" />
                                            <x-textarea label="Content" wire:model.live.debounce.300ms="data.en.testimonials.{{ $index }}.content" class="md:col-span-2" rows="4" />
                                            <div class="md:col-span-2 space-y-2">
                                                <label class="font-medium text-sm">Avatar</label>
                                                <input
                                                    wire:key="en-testimonial-avatar-{{ $item['id'] }}-{{ $loop->index }}"
                                                    type="file"
                                                    wire:model="data.en.testimonials.{{ $index }}.avatar_file"
                                                    accept="image/png, image/jpeg, image/webp"
                                                    class="file-input file-input-bordered w-full"
                                                >
                                                <div class="relative min-h-40 rounded-lg border border-dashed border-gray-300 bg-gray-50/70 overflow-hidden flex items-center justify-center">
                                                    @if(data_get($item, 'avatar_file') || data_get($item, 'avatar'))
                                                        <img src="{{ $this->displayMedia(data_get($item, 'avatar_file'), data_get($item, 'avatar')) }}" class="h-32 w-32 rounded-full object-cover" alt="Avatar" />
                                                    @else
                                                        <span class="text-sm text-gray-500">Chưa có ảnh</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <x-button label="Add testimonial" icon="o-plus" class="btn-primary" wire:click="addTestimonial('en')" />
                            </div>
                        </div>
                    </div>
                </x-tab>
            </x-tabs>
        </x-card>

        <x-card class="col-span-2 bg-white p-3!" title="Hành động" shadow separator progress-indicator="save">
            <div class="space-y-2">
                <x-button label="Lưu cấu hình" class="bg-primary text-white w-full" wire:click="save" wire:loading.attr="disabled" wire:target="save" spinner />
                <x-button label="Xem trang chủ" link="{{ route('client.home') }}" external class="bg-warning text-white w-full" />
                <x-button label="Banner" link="{{ route('admin.banner.index') }}" class="bg-slate-100 w-full" />
                <x-button label="Bài viết" link="{{ route('admin.post.index') }}" class="bg-slate-100 w-full" />
                <x-button label="Album ảnh" link="{{ route('admin.album.index') }}" class="bg-slate-100 w-full" />
                <x-button label="Đối tác" link="{{ route('admin.partner.index') }}" class="bg-slate-100 w-full" />
            </div>
        </x-card>
    </div>
</div>
