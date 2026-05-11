<?php

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Album;
use App\Models\AlbumImage;
use App\Models\Banner;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

new
#[Layout('layouts.client')]
class extends Component {

    public $tabSelected = 'tab-feature-post';
    public array $slides = [];
    public $slidePosts = [
//        [
//            'image' => '/assets/images/img1.jpg',
//            'day' => '9',
//            'month' => 'Tháng 2',
//        ],
//        [
//            'image' => '/assets/images/img2.jpg',
//            'day' => '13',
//            'month' => 'Tháng 3',
//        ],
    ];

    protected function defaultHomeConfig(string $locale): array
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
                    ['id' => 'quick-link-1', 'app' => 'ST-CARE', 'desc' => 'Q&A & student support', 'link' => 'https://st-dse.vnua.edu.vn:6896', 'color' => '#0961AA', 'img' => 'assets/images/question-and-answer.png'],
                    ['id' => 'quick-link-2', 'app' => 'CONSULTING', 'desc' => 'Choose a specialization', 'link' => 'https://st-dse.vnua.edu.vn:6879', 'color' => '#F6A309', 'img' => 'assets/images/health.png'],
                    ['id' => 'quick-link-3', 'app' => 'REGISTER', 'desc' => 'Internship & thesis', 'link' => 'https://st-dse.vnua.edu.vn:6875', 'color' => '#066140', 'img' => 'assets/images/register.png'],
                    ['id' => 'quick-link-4', 'app' => 'MANAGE', 'desc' => 'Lab activities', 'link' => 'https://st-dse.vnua.edu.vn:6888', 'color' => '#4E3636', 'img' => 'assets/images/calendar1.png'],
                ],
                'training_programs' => [
                    ['id' => 'program-1', 'title' => 'Information Technology', 'description' => 'The Information Technology program trains IT graduates with strong political awareness, professional ethics, responsibility, and good health; advanced knowledge and practical skills; creativity, self-learning, and research capacity; and a spirit of entrepreneurship and international integration.', 'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/cong-nghe-thong-tin', 'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=cong-nghe-thong-tin', 'image' => 'assets/images/nganh-cntt.jpg'],
                    ['id' => 'program-2', 'title' => 'Computer Networks and Data Communications', 'description' => 'This program trains graduates with strong political awareness, good health, solid knowledge, and practical skills in computing and IT; capable of self-learning and research to meet the needs of organizations and companies in the computing and IT sector.', 'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-mang-may-tinh-va-truyen-thong-du-lieu', 'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=mang-may-tinh-truyen-thong-du-lieu', 'image' => 'assets/images/nganh-mmt.jpg'],
                    ['id' => 'program-3', 'title' => 'Data Science and Artificial Intelligence', 'description' => 'The Data Science and Artificial Intelligence program trains graduates with strong political awareness, professional ethics, responsibility, and good health; advanced knowledge and practical skills; creativity, self-learning, and research capacity; and a spirit of entrepreneurship and international integration.', 'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'image' => 'assets/images/nganh-khdlttnt.jpg'],
                ],
                'counter_stats' => [
                    ['id' => 'stat-1', 'label' => 'Years of Training Experience', 'value' => 20, 'suffix' => '+', 'icon' => 'o-calendar-date-range'],
                    ['id' => 'stat-2', 'label' => 'Students currently enrolled', 'value' => 3500, 'suffix' => '+', 'icon' => 'o-user-group'],
                    ['id' => 'stat-3', 'label' => 'Graduated students', 'value' => 12000, 'suffix' => '+', 'icon' => 'o-academic-cap'],
                    ['id' => 'stat-4', 'label' => 'Graduates find jobs.', 'value' => 96, 'suffix' => '%', 'icon' => 'o-briefcase'],
                ],
                'testimonials' => [
                    ['id' => 'testimonial-1', 'name' => 'Mr. Le Doan Phuoc', 'role' => 'Director, Mai A Technology Co., Ltd.', 'content' => 'The IT faculty patiently guides students and helps them gain confidence to improve. Thanks to this foundation, when joining MaiA Tech, students show a proactive attitude, strong will, and adapt very quickly to real projects.', 'avatar' => 'assets/images/ldphuoc.jpg'],
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
                ['id' => 'quick-link-1', 'app' => 'ST-CARE', 'desc' => 'Hỏi đáp & Hỗ trợ sinh viên', 'link' => 'https://st-dse.vnua.edu.vn:6896', 'color' => '#0961AA', 'img' => 'assets/images/question-and-answer.png'],
                ['id' => 'quick-link-2', 'app' => 'TƯ VẤN', 'desc' => 'Chọn hướng chuyên sâu', 'link' => 'https://st-dse.vnua.edu.vn:6879', 'color' => '#F6A309', 'img' => 'assets/images/health.png'],
                ['id' => 'quick-link-3', 'app' => 'ĐĂNG KÝ', 'desc' => 'Thực tập nghề nghiệp & KLTN', 'link' => 'https://st-dse.vnua.edu.vn:6875', 'color' => '#066140', 'img' => 'assets/images/register.png'],
                ['id' => 'quick-link-4', 'app' => 'QUẢN LÝ', 'desc' => 'Hoạt động phòng lab', 'link' => 'https://st-dse.vnua.edu.vn:6888', 'color' => '#4E3636', 'img' => 'assets/images/calendar1.png'],
            ],
            'training_programs' => [
                ['id' => 'program-1', 'title' => 'Công nghệ thông tin', 'description' => 'Chương trình đào tạo ngành Công nghệ thông tin (CNTT) nhằm đào tạo ra cử nhân CNTT có phẩm chất chính trị vững vàng, có đạo đức nghề nghiệp, có trách nhiệm cao và sức khỏe tốt; có kiến thức chuyên sâu và thành thạo kỹ năng nghề nghiệp; có năng lực sáng tạo, tự học, tự nghiên cứu nhằm không ngừng nâng cao trình độ; có tinh thần lập nghiệp, hội nhập quốc tế; đóng góp nguồn nhân lực chất lượng cao trong lĩnh vực CNTT và lĩnh vực nông nghiệp hiện đại.', 'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/cong-nghe-thong-tin', 'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=cong-nghe-thong-tin', 'image' => 'assets/images/nganh-cntt.jpg'],
                ['id' => 'program-2', 'title' => 'Mạng máy tính và TTDL', 'description' => 'Chương trình đào tạo ngành mạng máy tính và truyền thông dữ liệu (MMT&TTDL) nhằm đào tạo cử nhân có phẩm chất chính trị vững vàng, có sức khỏe tốt; có kiến thức và kỹ năng vững vàng về lĩnh vực máy tính và công nghệ thông tin (CNTT); có khả năng tự học, tự nghiên cứu nhằm đáp ứng được yêu cầu công việc tại các cơ quan, các công ty liên quan đến lĩnh vực máy tính và CNTT.', 'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-mang-may-tinh-va-truyen-thong-du-lieu', 'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=mang-may-tinh-truyen-thong-du-lieu', 'image' => 'assets/images/nganh-mmt.jpg'],
                ['id' => 'program-3', 'title' => 'Khoa học dữ liệu và TTNT', 'description' => 'Chương trình đào tạo ngành Khoa học dữ liệu và Trí tuệ nhân tạo (KHDL&TTNT) nhằm đào tạo ra cử nhân có phẩm chất chính trị vững vàng, có đạo đức nghề nghiệp, có trách nhiệm cao và sức khỏe tốt; có kiến thức chuyên sâu và thành thạo kỹ năng nghề nghiệp; có năng lực sáng tạo, tự học, tự nghiên cứu nhằm không ngừng nâng cao trình độ; có tinh thần lập nghiệp, hội nhập quốc tế; đóng góp nguồn nhân lực chất lượng cao trong lĩnh vực KHDL&TTNT và lĩnh vực nông nghiệp hiện đại.', 'detail_url' => 'https://st-dse.vnua.edu.vn:6889/dai-hoc/nganh-khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'roadmap_url' => 'https://st-dse.vnua.edu.vn:6889/chuong-trinh-dao-tao?khoa=6&nganh=khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'image' => 'assets/images/nganh-khdlttnt.jpg'],
            ],
            'counter_stats' => [
                ['id' => 'stat-1', 'label' => 'Số năm kinh nghiệm đào tạo', 'value' => 20, 'suffix' => '+', 'icon' => 'o-calendar-date-range'],
                ['id' => 'stat-2', 'label' => 'Sinh viên đang theo học', 'value' => 3500, 'suffix' => '+', 'icon' => 'o-user-group'],
                ['id' => 'stat-3', 'label' => 'Sinh viên đã tốt nghiệp', 'value' => 12000, 'suffix' => '+', 'icon' => 'o-academic-cap'],
                ['id' => 'stat-4', 'label' => 'Sinh viên có việc làm.', 'value' => 96, 'suffix' => '%', 'icon' => 'o-briefcase'],
            ],
            'testimonials' => [
                ['id' => 'testimonial-1', 'name' => 'Ông Lê Doãn Phước', 'role' => 'Giám đốc Công ty TNHH Công nghệ Mai A', 'content' => 'Các thầy cô Khoa CNTT rất kiên trì dìu dắt sinh viên, giúp các em có niềm tin để tiến bộ. Nhờ nền tảng và sự rèn giũa này, khi gia nhập MaiA Tech, các em đều thể hiện thái độ làm việc cầu thị, ý chí vươn lên và thích ứng rất nhanh với dự án thực tế.', 'avatar' => 'assets/images/ldphuoc.jpg'],
            ],
        ];
    }

    protected function normalizeHomeConfig(array $config, string $locale): array
    {
        $defaults = $this->defaultHomeConfig($locale);

        foreach (['quick_links', 'training_programs', 'counter_stats', 'testimonials'] as $section) {
            if (!array_key_exists($section, $config) || !is_array($config[$section])) {
                $config[$section] = $defaults[$section];
                continue;
            }

            $config[$section] = array_values(array_filter($config[$section], 'is_array'));
        }

        $config['section_titles'] = array_merge($defaults['section_titles'], is_array($config['section_titles'] ?? null) ? $config['section_titles'] : []);

        return array_merge($defaults, $config);
    }

    protected function getHomeConfig(string $locale): array
    {
        $page = Page::where('slug', 'home3')->first();

        if (!$page) {
            return $this->defaultHomeConfig($locale);
        }

        $translations = $page->getTranslations('content_data');

        if (array_key_exists($locale, $translations)) {
            $config = $page->getTranslation('content_data', $locale, false);
            return is_array($config) ? $this->normalizeHomeConfig($config, $locale) : $this->defaultHomeConfig($locale);
        }

        if (array_key_exists('vi', $translations)) {
            $config = $page->getTranslation('content_data', 'vi', false);
            return is_array($config) ? $this->normalizeHomeConfig($config, $locale) : $this->defaultHomeConfig($locale);
        }

        return $this->defaultHomeConfig($locale);
    }

    protected function getPreviewHomeConfig(string $locale): ?array
    {
        if (!request()->boolean('preview_home3') || !auth()->check()) {
            return null;
        }

        $previewData = Cache::get('preview_home3_data_' . auth()->id());

        if (!is_array($previewData)) {
            return null;
        }

        if (array_key_exists($locale, $previewData) && is_array($previewData[$locale])) {
            return $this->normalizeHomeConfig($previewData[$locale], $locale);
        }

        if (array_key_exists('vi', $previewData) && is_array($previewData['vi'])) {
            return $this->normalizeHomeConfig($previewData['vi'], $locale);
        }

        return null;
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

        if (str_starts_with($path, 'assets/')) {
            return asset($path);
        }

        $path = ltrim($path, '/');
        $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return asset('storage/' . ltrim($path, '/'));
    }


    protected function hasMeaningfulTranslation(Post $post, string $field, string $locale): bool
    {
        $value = $post->getTranslation($field, $locale, false);

        if (!is_string($value)) {
            return false;
        }

        $plainText = trim(preg_replace(
            '/\x{00A0}/u',
            ' ',
            strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ) ?? '');

        return $plainText !== '';
    }

    protected function isVisibleInLocale(Post $post, string $locale): bool
    {
        if ($locale !== 'en') {
            return true;
        }

        return $this->hasMeaningfulTranslation($post, 'title', 'en')
            && $this->hasMeaningfulTranslation($post, 'content', 'en');
    }

    protected function isNewPost(Post $post): bool
    {
        if (!$post->published_at) {
            return false;
        }

        $publishedAt = $post->published_at instanceof \Illuminate\Support\Carbon
            ? $post->published_at
            : \Illuminate\Support\Carbon::parse($post->published_at);

        $now = now();
        $threshold = $now->copy()->subDays(3);

        return $publishedAt->greaterThanOrEqualTo($threshold)
            && $publishedAt->lessThanOrEqualTo($now);
    }

    public function with(): array
    {
        $locale = app()->getLocale();
        $homeConfig = $this->getPreviewHomeConfig($locale) ?? $this->getHomeConfig($locale);

        $fallbackSlides = [
            [
                'image' => '/assets/images/banner-1.jpg',
                'title' => '9 Tháng 2',
                'description' => 'Chương trình đào tạo của Khoa Công nghệ thông tin',
                'url' => 'https://fita.vnua.edu.vn/',
                'urlText' => 'Xem thêm',
                'position' => 'bottom center',
            ],
            [
                'image' => '/assets/images/banner-2.jpg',
                'position' => 'center center',
            ],
            [
                'image' => '/assets/images/banner-3.jpg',
                'url' => 'https://vnua.edu.vn/',
                'urlText' => 'Read more',
                'position' => 'bottom right',
            ],
        ];

        $dbSlides = Banner::query()
            ->active()
            ->orderBy('order')
            ->get()
            ->map(function (Banner $banner) use ($locale) {
                if (!$banner->image || !Storage::disk('public')->exists($banner->image)) {
                    return null;
                }

                return [
                    'image' => Storage::url($banner->image),
                    'title' => $banner->getTranslation('title', $locale, false)
                        ?: $banner->getTranslation('title', 'vi', false)
                            ?: $banner->getTranslation('title', 'en', false)
                                ?: '',
                    'description' => $banner->getTranslation('description', $locale, false)
                        ?: $banner->getTranslation('description', 'vi', false)
                            ?: $banner->getTranslation('description', 'en', false)
                                ?: '',
                    'url' => $banner->url,
                    'urlText' => $banner->getTranslation('url_text', $locale, false)
                        ?: $banner->getTranslation('url_text', 'vi', false)
                            ?: $banner->getTranslation('url_text', 'en', false)
                                ?: '',
                    'position' => $banner->position ?: 'bottom center',
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        $slides = count($dbSlides) > 0 ? $dbSlides : $fallbackSlides;
        $configBanner = Page::where('slug', 'banner')->first();
        $quickLinks = collect($homeConfig['quick_links'] ?? [])
            ->map(fn($item) => array_merge($item, [
                'img_url' => $this->resolveMediaUrl($item['img'] ?? '', asset('assets/images/question-and-answer.png')),
            ]))
            ->values()
            ->toArray();
        $trainingPrograms = collect($homeConfig['training_programs'] ?? [])
            ->map(fn($item) => array_merge($item, [
                'image_url' => $this->resolveMediaUrl($item['image'] ?? '', asset('assets/images/post-6.jpg')),
            ]))
            ->values()
            ->toArray();
        $counterStats = $homeConfig['counter_stats'] ?? [];
        $testimonials = collect($homeConfig['testimonials'] ?? [])
            ->map(fn($item) => array_merge($item, [
                'avatar_url' => $this->resolveMediaUrl($item['avatar'] ?? '', asset('assets/images/default-user-image.png')),
            ]))
            ->values()
            ->toArray();
        $sectionTitles = $homeConfig['section_titles'] ?? [];

        $baseQuery = Post::query()
            ->with([
                'categories' => fn($q) => $q->where('is_active', true),
                'user'
            ])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');

        $featuredPosts = (clone $baseQuery)
            ->where('is_featured', true)
            ->latest('published_at')
            ->limit($locale === 'en' ? 20 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $latestPosts = (clone $baseQuery)
            ->whereHas('categories', function ($query) {
                $query->where('categories.slug', 'tin-tuc')
                    ->orWhere('categories.slug', 'su-kien');
            })
            ->when($featuredPosts->isNotEmpty(), fn($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->latest('published_at')
            ->limit($locale === 'en' ? 24 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $notificationPosts = (clone $baseQuery)
            ->whereHas('categories', function ($query) {
                $query->where('categories.slug', 'thong-bao');
            })
            ->when($featuredPosts->isNotEmpty(), fn($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->latest('published_at')
            ->limit($locale === 'en' ? 24 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $featuredAlbum = Album::query()
            ->featuredForHome()
            ->orderByDesc('updated_at')
            ->first();

        $imagesQuery = AlbumImage::query()
            ->whereNull('album_images.deleted_at');

        if ($featuredAlbum) {
            $imagesQuery
                ->whereHas('albums', fn($query) => $query->where('albums.id', $featuredAlbum->id))
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id');
        } else {
            $imagesQuery
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id')
                ->limit(20);
        }

        $images = $imagesQuery
            ->get()
            ->filter(fn(AlbumImage $image) => filled($image->image_path) && Storage::disk('public')->exists($image->image_path))
            ->map(fn(AlbumImage $image) => [
                'url' => Storage::url($image->image_path),
                'alt' => $image->caption,
                'caption' => $image->caption,
            ])
            ->values()
            ->toArray();

        if ($featuredAlbum && count($images) === 0) {
            $images = AlbumImage::query()
                ->whereNull('album_images.deleted_at')
                ->orderByDesc('album_images.created_at')
                ->orderByDesc('album_images.id')
                ->limit(20)
                ->get()
                ->filter(fn(AlbumImage $image) => filled($image->image_path) && Storage::disk('public')->exists($image->image_path))
                ->map(fn(AlbumImage $image) => [
                    'url' => Storage::url($image->image_path),
                    'alt' => $image->caption,
                    'caption' => $image->caption,
                ])
                ->values()
                ->toArray();
        }

        return [
            'slides' => $slides,
            'quickLinks' => $quickLinks,
            'trainingPrograms' => $trainingPrograms,
            'counterStats' => $counterStats,
            'testimonials' => $testimonials,
            'sectionTitles' => $sectionTitles,
            'featuredPosts' => $featuredPosts,
            'latestPosts' => $latestPosts,
            'notificationPosts' => $notificationPosts,
            'images' => $images,
            'configBanner' => $configBanner,
        ];
    }
    public function mount(): void
    {
        $locale = app()->getLocale();

        // Kiểm tra nhanh xem có bài viết nổi bật nào hợp lệ không
        $hasFeatured = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('is_featured', true)
            ->latest('published_at')
            ->limit($locale === 'en' ? 20 : 4)
            ->get()
            ->filter(fn(Post $post) => $this->isVisibleInLocale($post, $locale))
            ->isNotEmpty();

        // Nếu không có bài nổi bật, đổi tab mặc định sang Tin mới
        if (!$hasFeatured) {
            $this->tabSelected = 'tab-new-post';
        }
    }
};
?>

<div class="">
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Home page') }}
    </x-slot:title>
    {{--  end - title  --}}
    {{--    <x-carousel :slides="$slides"  interval="5000" class="custom-carousel h-65 lg:h-100 2xl:h-150 w-full aspect-[16/9] md:aspect-[3/1] overflow-hidden--}}
    {{--            bg-cover bg-center bg-no-repeat">--}}
    {{--    @dd($configBanner->content_data['autoplay'])--}}
    <div
        x-data="{
            paused: false,
            autoplay: {{ !empty($configBanner->content_data['autoplay']) ? 'true' : 'false' }},
            interval: {{ (int) ($configBanner->content_data['interval'] ?? 5000) }},
            startCustomAutoplay() {
                if (this.autoplay) {
                    setInterval(() => {
                        // Nếu chuột không nằm trong vùng banner thì mới bấm nút Next
                        if (!this.paused) {
                            let nextBtn = this.$el.querySelector(`button[aria-label='Next image']`);
                            if (nextBtn) nextBtn.click();
                        }
                    }, this.interval);
                }
            }
        }"
        x-init="startCustomAutoplay()"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        class="relative w-full"
    >
        <x-carousel
            :slides="$slides"
    {{--        :autoplay="data_get($configBanner, 'content_data.autoplay', false)"--}}
    {{--        :interval="data_get($configBanner, 'content_data.interval', 5000)"--}}
            class="h-[40vw] md:h-65 lg:h-91 2xl:h-110 rounded-none w-full [&_img]:w-full [&_img]:h-full [&_img]:object-fill"
        >
            @scope('content', $slide)
            <div
                @class([
                    "absolute inset-0 z-[1] flex flex-col gap-2 px-20 py-12",
                     "bg-gradient-to-b justify-start text-left" => data_get($slide, 'position') === 'top left',
                     "bg-gradient-to-b justify-start items-center text-center" => data_get($slide, 'position') === 'top center',
                     "bg-gradient-to-b justify-start items-end text-right" => data_get($slide, 'position') === 'top right',

                     "bg-gradient-to-t justify-center items-center text-center" => data_get($slide, 'position') === 'center center',
                     "bg-gradient-to-t justify-center items-end text-right" => data_get($slide, 'position') === 'center right',
                     "bg-gradient-to-t justify-center text-left" => data_get($slide, 'position') === 'center left',

                     "bg-gradient-to-t justify-end text-left" => data_get($slide, 'position') === 'bottom left',
                     "bg-gradient-to-t justify-end items-center text-center" => data_get($slide, 'position') === 'bottom center',
                     "bg-gradient-to-t justify-end items-end text-right" => data_get($slide, 'position') === 'bottom right',

                     "from-slate-900/45" => data_get($slide, 'urlText') || data_get($slide, 'title') || data_get($slide, 'description')
                ])
            >

                <!-- Title 1 -->
                <h1 class="w-[60%] text-2xl lg:text-[64px]/[68px] font-bold text-white">{{ data_get($slide, 'title') }}</h1>
                <!-- Title 2 -->
                <h5 class="w-[60%] text-[16px] lg:text-[30px] font-bold text-white">{{ data_get($slide, 'description') }}</h5>


                <!-- Button-->
                @if(data_get($slide, 'urlText'))
                    <div class="hidden md:block">
                        <x-button link="{{ data_get($slide, 'url') }}" icon-right="o-arrow-right"
                                  class="btn btn-sm lg:btn-md max-w-40 bg-fita text-white border-transparent shadow-none hover:bg-fita2 my-3 hover:scale-105">{{ __(data_get($slide, 'urlText')) }}</x-button>
                    </div>
                @endif
            </div>
            @endscope
        </x-carousel>
    </div>

    <div class="my-0.125">
        <div class="h-1.25 bg-[#F6A309] w-full"></div>
        <div class="h-1.25 bg-[#066140] w-full"></div>
        <div class="h-1.25 bg-[#4E3636] w-full shadow-[0_0_6px_#4E3636]"></div>
    </div>

    @if(!empty($quickLinks))
        <div class="relative z-20 -mt-4 mb-6 font-sans">
            <div class="container mx-auto px-3 lg:px-4 max-w-5xl"
                 x-data="{
            menus: @js($quickLinks)
         }">

                <div class="bg-white/80 backdrop-blur-xl rounded-2xl border border-gray-100 p-2 lg:py-2 lg:px-3 flex flex-wrap lg:flex-nowrap gap-2 shadow-[0_15px_35px_rgba(0,0,0,0.22)]">

                    <template x-for="(item, index) in menus" :key="index">
                        <a :href="item.link"
                           target="_blank"
                           class="relative group flex-1 min-w-[45%] md:min-w-0 flex items-center justify-center lg:justify-start gap-3 lg:gap-4 p-2 rounded-xl hover:shadow-md hover:bg-blue-100/50 transition-all duration-300 cursor-pointer hover:-translate-y-1"
                        >
                            <div class="w-10 h-10 lg:w-12 lg:h-12 shrink-0 transition-transform duration-300 group-hover:scale-105 group-hover:-translate-y-1">
                                <img :src="item.img_url ?? item.img" alt="App Icon" class="w-full h-full object-contain drop-shadow-sm">
                            </div>

                            <div class="text-left flex-1 min-w-0">
                                <h2 class="text-[14px] lg:text-[17px] font-bold text-gray-800 tracking-wide truncate transition-colors duration-300"
                                    :style="`color: ${item.color};`"
                                    x-text="item.app">
                                </h2>
                            </div>

                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 px-3 py-1.5 bg-white backdrop-blur rounded-lg opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none whitespace-nowrap z-50 shadow-xl scale-95 group-hover:scale-100 origin-bottom invisible lg:visible">
                                <span class="text-[14px] font-semibold" x-text="item.desc"></span>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-[5px] border-transparent border-t-white"></div>
                            </div>
                        </a>
                    </template>

                </div>
            </div>
        </div>
    @endif

    <div >
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-6 lg:mt-4 mb-4">
            {{ $sectionTitles['news'] ?? __('News and events') }}
        </h1>
        <div class="relative flex flex-col lg:flex-row container px-4 lg:px-0 mx-auto gap-10">
            <div class="lg:w-[50%] w-full relative h-60 lg:h-140" wire:key="slider-{{ $tabSelected }}">
                @php
                    // Lấy tất cả các bài viết tương ứng với tab đang chọn
                    $currentTabPosts = match($tabSelected) {
                        'tab-feature-post' => $featuredPosts,
                        'tab-new-post' => $latestPosts,
                        default => $notificationPosts,
                    };

                    // Chuyển đổi collection thành mảng dữ liệu để truyền cho Javascript (Alpine)
                    $sliderData = $currentTabPosts->map(function($post) {
                        return [
                            'url' => $post->client_url,
                            'image' => $post->thumbnail
                                ? Storage::url($post->thumbnail)
                                : asset('assets/images/post-7.jpg'),
                            'is_featured' => $post->is_featured,
                            'is_new' => $this->isNewPost($post),
                            'is_notif' => $post->categories->contains(fn($cat) => $cat->slug === 'thong-bao'),
                            'day' => $post->published_at?->isoFormat('DD'),
                            'month' => app()->getLocale() === 'vi'
                                ? 'Tháng '.$post->published_at?->isoFormat('MM').'/'.$post->published_at?->isoFormat('YYYY')
                                : $post->published_at?->isoFormat('MMMM'),
                            'year' => $post->published_at?->isoFormat('YYYY'),
                            'title' => $post->getTranslation('title', app()->getLocale()),
//                            'excerpt' => $post->getExcerptOrAuto(app()->getLocale(), 170),
                        ];
                    })->values()->toArray();
                @endphp

                <div
                    x-data="{
            posts: @js($sliderData),
            currentIndex: 0,
            interval: null,
            init() {
                // Nếu có nhiều hơn 1 bài viết thì mới cho chạy auto
                if (this.posts.length > 1) {
                    this.start();
                }
            },
            start() {
                this.interval = setInterval(() => {
                    this.currentIndex = (this.currentIndex + 1) % this.posts.length;
                }, 7000); // Tự động chuyển bài mỗi 7 giây
            },
            pause() {
                clearInterval(this.interval);
            }
        }"
                    @mouseenter="pause"
                    @mouseleave="if(posts.length > 1) start()"
                    class="relative h-full w-full rounded-2xl overflow-hidden bg-slate-900 border border-base-300"
                >
                    <!-- Vòng lặp in ra các slide -->
                    <template x-for="(post, index) in posts" :key="index">
                        <a
                            x-show="currentIndex === index"
                            x-transition.opacity.duration.700ms
                            :href="post.url"
                            wire:navigate
                            class="absolute inset-0 group block h-full w-full"
                        >
                            <img
                                :src="post.image"
                                :alt="post.title"
                                loading="eager"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                onerror="this.onerror=null;this.src='{{ asset('assets/images/post-11.jpg') }}'"
                            >

                            <!-- Tag bài viết nổi bật -->
                            <template x-if="post.is_featured">
                                {{--                                <div class="absolute top-3 left-3 z-10 inline-flex items-center gap-1 rounded-full bg-[#F6A309] px-2.5 py-1 text-xs font-semibold text-white shadow">--}}
                                {{--                                    <x-icon name="s-star" class="w-3 h-3" />--}}
                                {{--                                    {{ __('Featured') }}--}}
                                {{--                                </div>--}}
                                <div
                                    class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-red-500 px-4 py-1 text-md font-bold text-white shadow-md rounded-br-2xl rounded-tl-xl"
                                >
                                    {{ __('Featured News') }}
                                </div>
                            </template>

                            <!-- Tag bài viết mới -->
                            @if($tabSelected === 'tab-new-post')
                                <template x-if="!post.is_featured && post.is_new">

                                    {{--                                <div class="absolute top-3 left-3 z-10 inline-flex items-center justify-center rounded-full bg-[#F6A309] px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white shadow-lg font-sans">--}}
                                    {{--                                    NEW--}}
                                    {{--                                </div>--}}
                                    {{--                                <div class="absolute top-0 left-0 z-10 inline-flex items-center justify-center rounded-full">--}}
                                    {{--                                    <img src="{{asset('assets/images/new2.png')}}" alt="" class="h-8 object-contain">--}}
                                    {{--                                </div>--}}
                                    <div
                                        class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-[#F6A309] px-4 py-1 text-md font-bold text-white shadow-md rounded-br-2xl rounded-tl-xl"
                                    >
                                        {{ __('New') }}
                                    </div>
                                </template>
                            @endif
                            @if($tabSelected === 'tab-notification-post')
                                <template x-if="!post.is_featured && post.is_notif">

                                    {{--                                <div class="absolute top-3 left-3 z-10 inline-flex items-center justify-center rounded-full bg-[#F6A309] px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white shadow-lg font-sans">--}}
                                    {{--                                    NEW--}}
                                    {{--                                </div>--}}
                                    {{--                                <div class="absolute top-0 left-0 z-10 inline-flex items-center justify-center rounded-full">--}}
                                    {{--                                    <img src="{{asset('assets/images/new2.png')}}" alt="" class="h-8 object-contain">--}}
                                    {{--                                </div>--}}
                                    <div
                                        class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-success px-4 py-1 text-md font-bold text-white shadow-md rounded-br-2xl rounded-tl-xl"
                                    >
                                        {{ __('Notification') }}
                                    </div>
                                </template>
                            @endif

                            <!-- Box Ngày tháng -->
                            <div class="absolute right-0 top-0 z-10 bg-black/45 px-3 py-2 text-center text-white backdrop-blur-sm">
                                <div class="text-[40px]/[34px] lg:text-[54px]/[44px] font-bold" x-text="post.day"></div>
                                <div class="text-[18px]/[30px] lg:text-[20px]/[24px] font-bold mt-0 lg:mt-3" x-text="post.month"></div>
                            </div>

                            <!-- Lớp gradient tạo độ tương phản cho text -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

                            <!-- Tiêu đề và trích dẫn -->
                            <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                                <h3 class="line-clamp-2 text-[18px]/[20px] lg:text-[20px]/[24px] font-bold" x-text="post.title"></h3>
                                {{--                                <p class="mt-3 line-clamp-2 text-[16px]/[18px] lg:text-[18px]/[22px] text-white/90" x-text="post.excerpt"></p>--}}
                            </div>
                        </a>
                    </template>

                    <!-- (Tùy chọn) Các dấu chấm điều hướng ở góc dưới bên phải -->
                    <div x-show="posts.length > 1" class="absolute bottom-4 right-4 z-20 flex gap-2">
                        <template x-for="(post, index) in posts" :key="'dot-'+index">
                            <button
                                @click="currentIndex = index"
                                class="w-2.5 h-2.5 rounded-full transition-all duration-300 shadow-sm"
                                :class="currentIndex === index ? 'bg-white w-6' : 'bg-white/50 hover:bg-white/80'"
                            ></button>
                        </template>
                    </div>

                    <!-- Màn hình dự phòng khi không có bài viết -->
                    <div x-show="posts.length === 0" class="flex h-full items-center justify-center bg-base-100 text-base-content/60">
                        {{ __('No posts available') }}
                    </div>
                </div>

                <!-- Loading state của Livewire -->
                <div wire:loading.flex wire:target="tabSelected"
                     class="absolute inset-0 z-30 items-center justify-center bg-white/60 backdrop-blur-[1px] rounded-2xl">
                    <x-loading class="text-primary loading-lg"/>
                </div>
            </div>

            <div class="w-full lg:w-[50%]">
                <x-tabs
                    wire:model.live="tabSelected"
                    active-class="text-fita! border-b-4 border-fita font-semibold"
                    label-class="font-semibold text-[20px] text-gray-700 px-4 pb-1 whitespace-nowrap font-barlow"
                    label-div-class="border-b-[length:var(--border)] border-b-base-content/10 flex overflow-x-auto"
                >
                    <x-tab name="tab-feature-post" icon="">
                        <x-slot:label>
                            <span class="inline-flex items-center h-6">{{ __('Featured News') }}</span>
                        </x-slot:label>
                        <div class="flex flex-col gap-4">
                            @forelse($featuredPosts as $post)
                                <div
                                    class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}"
                                                 class="w-full h-full object-cover" alt="No image" loading="lazy"
                                                 decoding="async">
                                        @endif
                                        @if($post->is_featured)
                                            {{--                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-warning px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">--}}
                                            {{--                                                <x-icon name="s-star" class="w-3 h-3" />--}}
                                            {{--                                                {{ __('Featured News') }}--}}
                                            {{--                                            </div>--}}
                                            <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-red-500 pe-2 ps-1 py-0.5 text-[9px] font-bold text-white shadow-md rounded-br-xl">
                                                {{ __('Featured News') }}
                                            </div>
                                        @endif
                                        @if($this->isNewPost($post) && !$post->is_featured)
                                            {{--                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">--}}
                                            {{--                                                <span class="h-1 w-1 rounded-full bg-white"></span>--}}
                                            {{--                                                {{ __('New') }}--}}
                                            {{--                                            </div>--}}
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate
                                           class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}
                                        </p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if($featuredPosts->isEmpty())
                                    <p class="text-gray-500">{{ __('No featured posts found.') }}</p>
                                @endif
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index') }}" label="{{__('Read more')}}"
                                  icon-right="o-arrow-right"
                                  class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-4">
                        </x-button>
                    </x-tab>
                    <x-tab name="tab-new-post">
                        <x-slot:label>
                            <span class="inline-flex items-center h-6">{{ __('Latest News') }}</span>
                        </x-slot:label>
                        <div class="flex flex-col gap-4">
                            @forelse($latestPosts as $post)
                                <div
                                    class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}"
                                                 class="w-full h-full object-cover" alt="No image" loading="lazy"
                                                 decoding="async">
                                        @endif

                                        @if($this->isNewPost($post) && !$post->is_featured)
                                            {{--                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">--}}
                                            {{--                                                <span class="h-1 w-1 rounded-full bg-white"></span>--}}
                                            {{--                                                {{ __('New') }}--}}
                                            {{--                                            </div>--}}
                                            <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-[#F6A309] pe-2 ps-1 py-0.5 text-[9px] font-bold text-white shadow-md rounded-br-xl">
                                                {{ __('New') }}
                                            </div>
                                            <div class="absolute top-1 left-1">
                                                <img src="{{asset('assets/images/new2.png')}}" alt="" class="h-4 object-contain">
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate
                                           class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}
                                        </p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if($latestPosts->isEmpty())
                                    <p class="text-gray-500">{{ __('No latest posts found.') }}</p>
                                @endif
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}"
                                  icon-right="o-arrow-right"
                                  class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-4">
                        </x-button>
                    </x-tab>
                    <x-tab name="tab-notification-post">
                        <x-slot:label>
                            <span class="inline-flex items-center h-6">{{ __('Notification') }}</span>
                        </x-slot:label>
                        <div class="flex flex-col gap-4">
                            @forelse($notificationPosts as $post)
                                <div
                                    class="flex gap-5 bg-white rounded-2xl p-3 lg:px-4 lg:py-3 border border-slate-300">
                                    <div class="h-25 w-33 shrink-0 bg-gray-100 overflow-hidden relative">
                                        @if($post->thumbnail)
                                            <img src="{{ Storage::url($post->thumbnail) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                 loading="lazy" decoding="async">
                                        @else
                                            <img src="{{ asset('assets/images/post-6.jpg') }}"
                                                 class="w-full h-full object-cover" alt="No image" loading="lazy"
                                                 decoding="async">
                                        @endif

                                        {{--                                        @if($this->isNewPost($post) && !$post->is_featured)--}}
                                        {{--                                            <div class="absolute top-1 left-1 inline-flex items-center gap-1 rounded-full bg-[#22c55e] px-1.5 py-0.5 text-[10px] font-semibold text-white shadow">--}}
                                        {{--                                                <span class="h-1 w-1 rounded-full bg-white"></span>--}}
                                        {{--                                                {{ __('New') }}--}}
                                        {{--                                            </div>--}}
                                        <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-success pe-2 ps-1 py-0.5 text-[9px] font-bold text-white shadow-md rounded-br-xl">
                                            {{ __('Notification') }}
                                        </div>
                                        {{--                                        @endif--}}
                                    </div>
                                    <div class="flex-1 font-barlow">
                                        <a href="{{ $post->client_url }}" wire:navigate
                                           class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2 hover:opacity-90">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </a>
                                        <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 160) }}
                                        </p>
                                        <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                @if($notificationPosts->isEmpty())
                                    <p class="text-gray-500">{{ __('No announcement posts found.') }}</p>
                                @endif
                            @endforelse
                        </div>
                        <x-button link="{{ route('client.posts.index',['danh-muc' => 'thong-bao']) }}" label="{{__('Read more')}}"
                                  icon-right="o-arrow-right"
                                  class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-[1.02] mt-4">
                        </x-button>
                    </x-tab>
                </x-tabs>
                {{--                <x-button link="{{ route('client.posts.index',['danh-muc' => 'tin-tuc']) }}" label="{{__('Read more')}}"--}}
                {{--                          icon-right="o-arrow-right"--}}
                {{--                          class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-105">--}}
                {{--                </x-button>--}}
            </div>
        </div>
    </div>

    @if(!empty($trainingPrograms))
        <div>
            <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-5">
                {{ $sectionTitles['training'] ?? __('Training programs') }}
            </h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 container mx-auto px-4 lg:px-0">
                @foreach($trainingPrograms as $program)
                    <div class="flex flex-col relative rounded-2xl overflow-hidden border border-slate-300 group hover:-translate-y-1.5 hover:shadow-lg transition-all duration-300"
                         x-data="{ revealed: false }"
                         x-intersect="
                            if (!revealed) {
                                revealed = true;
                                $el.classList.add('animate-fade-in-up');
                            }
                        "
                    >
                        <img src="{{ data_get($program, 'image_url') ?: asset('assets/images/post-6.jpg') }}" alt=""
                             class="w-full object-cover transition-transform duration-500 h-50"
                             loading="lazy" decoding="async">
                        <div class="flex flex-col justify-around flex-1">
                            <div class="px-6 py-4">
                                <a
                                    href="{{ data_get($program, 'detail_url') }}" wire:navigate
                                    class="why-title text-[18px] lg:text-[22px] font-bold text-slate-900 mb-2 transition-colors uppercase line-clamp-2 group-hover:text-fita">
                                    {{ data_get($program, 'title') }}
                                </a>
                                <p class="text-[14px] lg:text-[16px] text-slate-600 leading-relaxed line-clamp-4">
                                    {{ data_get($program, 'description') }}
                                </p>
                            </div>
                            <div class="px-6 pb-4 pt-2 flex gap-4 justify-around flex-wrap">
                                <x-button label="{{ app()->getLocale() === 'en' ? 'Detail program' : 'Chi tiết chương trình' }}"
                                          class="btn-outline text-fita font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                                          link="{{ data_get($program, 'detail_url') }}"
                                />
                                <x-button label="{{ app()->getLocale() === 'en' ? 'Roadmap' : 'Xem lộ trình' }}" icon="o-book-open"
                                          class="bg-fita text-white font-semibold text-[14px] py-3! hover:opacity-90 hover:scale-[1.02] rounded-4xl"
                                          link="{{ data_get($program, 'roadmap_url') }}"
                                />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($counterStats))
        <section class="mt-8 lg:mt-10 bg-slate-200/40 pt-15 ">
            <div class="mx-auto container px-4 lg:px-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-15">
                    @foreach($counterStats as $stat)
                        <div
                            data-reveal-item
                            class="relative pt-12 pb-6 px-4 text-center"
                            x-data="{
                        value: 0,
                        target: {{ (int) $stat['value'] }},
                        suffix: '{{ $stat['suffix'] }}',
                        started: false,
                        format(v) { return new Intl.NumberFormat('vi-VN').format(v); },
                        start() {
                            if (this.started) return;
                            this.started = true;
                            const duration = 1200;
                            const startTime = performance.now();
                            const tick = (now) => {
                                const progress = Math.min((now - startTime) / duration, 1);
                                this.value = Math.floor(this.target * progress);
                                if (progress < 1) requestAnimationFrame(tick);
                            };
                            requestAnimationFrame(tick);
                        }
                    }"
                            x-init="
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (!entry.isIntersecting) return;
                                start();
                                observer.disconnect();
                            });
                        }, { threshold: 0.4 });
                        observer.observe($el);
                    "
                        >
                            <div
                                class="absolute -top-10 left-1/2 -translate-x-1/2 h-20 w-20 rounded-full bg-[#DDE8F1] flex items-center justify-center">
                                <x-icon name="{{ $stat['icon'] }}" class="w-10 h-10 text-fita"/>
                            </div>

                            <p class="text-[30px] lg:text-[38px] leading-none font-bold text-fita mt-2">
                                <span x-text="format(value)"></span><span x-text="suffix"></span>
                            </p>
                            <p class="mt-3 text-[20px] lg:text-[18px] text-slate-700 leading-8">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10 mb-4">
            {{ $sectionTitles['partners'] ?? __('NETWORK OF BUSINESS PARTNERS') }}
        </h1>
        <livewire:client.list-of-partners/>
    </div>

    @if(!empty($testimonials))
        <section class="bg-blue-100/40 pb-10 pt-2 font-sans" x-data="{
        activeIndex: 0,
        slides: @js($testimonials),
        next() { this.activeIndex = this.activeIndex === this.slides.length - 1 ? 0 : this.activeIndex + 1 },
        prev() { this.activeIndex = this.activeIndex === 0 ? this.slides.length - 1 : this.activeIndex - 1 },
    }"
                 x-init="if (slides.length > 1) setInterval(() => next(), 7000)"
        >
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-6">
                    <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mt-8 lg:mt-10">
                        {{ $sectionTitles['testimonials'] ?? __('Perspectives from businesses and alumni') }}
                    </h1>
                </div>

                <div class="relative flex items-center justify-center">

                    <button @click="prev()" class="absolute left-0 md:-left-4 z-10 w-10 h-10 bg-white rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-fita transition">
                        <x-icon name="s-chevron-left"></x-icon>
                    </button>

                    <div class="bg-white rounded-[40px] shadow-sm p-8 md:p-12 max-w-4xl w-full mx-8 relative md:h-72.5 sm:h-100 h-114">
                        <template x-for="(slide, index) in slides" :key="slide.id ?? index">
                            <div x-show="activeIndex === index"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 transform translate-x-4"
                                 x-transition:enter-end="opacity-100 transform translate-x-0"
                                 class="flex flex-col md:flex-row items-center gap-8">

                                <div class="relative flex-shrink-0">
                                    <div class="w-32 h-32 md:w-40 md:h-40 rounded-full overflow-hidden border-4 border-gray-100 shadow-inner">
                                        <img :src="slide.avatar_url ?? slide.avatar ?? '{{ asset('assets/images/default-user-image.png') }}'" alt="Avatar" class="w-full h-full object-cover">
                                    </div>
                                </div>

                                <div class="flex-1 text-center md:text-left relative">
                                    <div class="hidden md:block absolute top-0 -right-2  text-6xl italic font-serif">
                                        <svg height="40px" width="40px" version="1.1" id="_x32_" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.00 512.00" xml:space="preserve" fill="#000000" stroke="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <style type="text/css"> .st0{fill:#0c83d8;} </style> <g> <path class="st0" d="M148.57,63.619H72.162C32.31,63.619,0,95.929,0,135.781v76.408c0,39.852,32.31,72.161,72.162,72.161h7.559 c6.338,0,12.275,3.128,15.87,8.362c3.579,5.234,4.365,11.898,2.074,17.811L54.568,422.208c-2.291,5.92-1.505,12.584,2.074,17.81 c3.595,5.234,9.532,8.362,15.87,8.362h50.738c7.157,0,13.73-3.981,17.041-10.318l61.257-117.03 c12.609-24.09,19.198-50.881,19.198-78.072v-107.18C220.748,95.929,188.422,63.619,148.57,63.619z"></path> <path class="st0" d="M439.84,63.619h-76.41c-39.852,0-72.16,32.31-72.16,72.162v76.408c0,39.852,32.309,72.161,72.16,72.161h7.543 c6.338,0,12.291,3.128,15.87,8.362c3.596,5.234,4.365,11.898,2.091,17.811l-43.113,111.686c-2.291,5.92-1.505,12.584,2.09,17.81 c3.579,5.234,9.516,8.362,15.871,8.362h50.722c7.157,0,13.73-3.981,17.058-10.318l61.24-117.03 C505.411,296.942,512,270.152,512,242.96v-107.18C512,95.929,479.691,63.619,439.84,63.619z"></path> </g> </g></svg>
                                    </div>

                                    <h4 class="text-xl font-bold text-black mb-1" x-text="slide.name"></h4>
                                    <p class="text-gray-600 italic mb-6 text-sm md:text-base" x-text="slide.role"></p>
                                    <p class="text-gray-700 leading-relaxed text-base md:text-lg md:line-clamp-4 line-clamp-6" x-text="slide.content"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button @click="next()" class="absolute right-0 md:-right-4 z-10 w-10 h-10 bg-white rounded-full shadow-md flex items-center justify-center text-gray-400 hover:text-fita transition">
                        <x-icon name="s-chevron-right"></x-icon>
                    </button>
                </div>

            </div>

            <div class="flex justify-center mt-8 gap-2">
                <template x-for="(slide, index) in slides" :key="slide.id ?? index">
                    <button @click="activeIndex = index"
                            class="h-1.5 transition-all duration-300 rounded-full"
                            :class="activeIndex === index ? 'w-8 bg-fita2' : 'w-8 bg-blue-300'"></button>
                </template>
            </div>
        </section>
    @endif

    <div>
        <h1 class="uppercase lg:text-[32px] text-[28px] text-fita font-bold font-barlow flex justify-center gap-1 items-center mb-2 mt-5">
            {{ $sectionTitles['gallery'] ?? __('Photo library') }}
        </h1>
        <livewire:client.image-gallery :images="$images" class="h-40 rounded-box"/>
    </div>

</div>
