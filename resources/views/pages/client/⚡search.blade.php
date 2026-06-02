<?php

use App\Models\Post;
use App\Models\Lecturer;
use App\Services\PostSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.client')]
class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'tab')]
    public string $tab = 'all';

    public int $postsPerPage = 5;
    public int $lecturersPerPage = 10;

    protected function keyword(): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->search) ?? '');
    }

    protected function isValidSearch(): bool
    {
        return mb_strlen($this->keyword()) >= 2;
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

    private function normalizeText(?string $text): string
    {
        return Str::lower(trim(Str::ascii((string) $text)));
    }

    private function localizeAcademicTitle(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.academic_title.$key");

        return $translated !== "lecturer.academic_title.$key"
            ? $translated
            : (string) $value;
    }

    private function localizeDegree(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.degree.$key");

        return $translated !== "lecturer.degree.$key"
            ? $translated
            : (string) $value;
    }

    private function positionTextsForSearch(Lecturer $lecturer): array
    {
        $positions = $lecturer->positions;

        if (is_array($positions)) {
            return array_values(array_filter([
                $positions['vi'] ?? null,
                $positions['en'] ?? null,
            ]));
        }

        if (is_string($positions) && trim($positions) !== '') {
            return [$positions];
        }

        return [];
    }

    protected function postQuery()
    {
        $locale = app()->getLocale();
        $isEn = $locale === 'en';

        $search = $this->keyword();
        $terms = PostSearchService::parseTerms($search);

        $query = Post::query()
            ->with([
                'categories' => fn ($q) => $q->where('is_active', true),
                'user',
                'defaultImage',
            ])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        PostSearchService::applyLocaleFilter($query, $isEn);
        PostSearchService::applyTerms($query, $terms, $isEn);

        return $query
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at');
    }

    protected function lecturerItems()
    {
        $keyword = $this->keyword();
        $locale = app()->getLocale() === 'en' ? 'en' : 'vi';

        $positionExpr = "LOWER(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(positions, '$.{$locale}')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(positions, '$.vi')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(positions, '$.en')), ''), ''))";

        $query = Lecturer::query()
            ->with(['user', 'department'])
            ->whereNotNull('slug')
            ->whereHas('user', fn ($query) => $query
                ->where('user_type', 'lecturer')
                ->where('is_active', true)
                ->whereNotNull('name')
                ->whereRaw("TRIM(name) <> ''")
            )
            ->orderByRaw("CASE
                WHEN {$positionExpr} LIKE '%pho truong khoa%' OR {$positionExpr} LIKE '%phó trưởng khoa%' OR {$positionExpr} LIKE '%vice dean%' OR {$positionExpr} LIKE '%deputy dean%' OR {$positionExpr} LIKE '%associate dean%' THEN 2
                WHEN {$positionExpr} LIKE '%truong khoa%' OR {$positionExpr} LIKE '%trưởng khoa%' OR {$positionExpr} LIKE '%dean%' THEN 1
                WHEN {$positionExpr} LIKE '%truong bo mon%' OR {$positionExpr} LIKE '%trưởng bộ môn%' OR {$positionExpr} LIKE '%head of department%' THEN 3
                WHEN {$positionExpr} LIKE '%pho truong bo mon%' OR {$positionExpr} LIKE '%phó trưởng bộ môn%' OR {$positionExpr} LIKE '%deputy head%' OR {$positionExpr} LIKE '%vice head%' THEN 4
                WHEN {$positionExpr} LIKE '%giang vien%' OR {$positionExpr} LIKE '%giảng viên%' OR {$positionExpr} LIKE '%lecturer%' THEN 5
                ELSE 9
            END ASC")
            ->orderByRaw("{$positionExpr} ASC");

        $items = $query->get()->filter(function (Lecturer $lecturer) {
            $name = trim((string) ($lecturer->user?->name ?? ''));

            return $name !== '';
        })->values();

        $normalizedKeyword = $this->normalizeText($keyword);

        return $items
            ->filter(function (Lecturer $lecturer) use ($keyword, $normalizedKeyword) {
                $positionTexts = $this->positionTextsForSearch($lecturer);

                $haystack = implode(' ', [
                    (string) ($lecturer->user?->name ?? ''),
                    (string) ($lecturer->user?->email ?? ''),
                    ...$positionTexts,
                ]);

                $rawMatch = mb_stripos($haystack, $keyword) !== false;
                $normalizedMatch = str_contains($this->normalizeText($haystack), $normalizedKeyword);

                return $rawMatch || $normalizedMatch;
            })
            ->sortByDesc(fn (Lecturer $lecturer) => $this->lecturerSearchScore($lecturer, $keyword))
            ->values();
    }

    public function with(): array
    {
        if (!$this->isValidSearch()) {
            return [
                'posts' => collect(),
                'lecturers' => collect(),
                'postsTotal' => 0,
                'lecturersTotal' => 0,
            ];
        }

        $postItems = $this->postQuery()
            ->take(100)
            ->get()
            ->sortByDesc(fn (Post $post) => $this->postSearchScore($post, $this->keyword()))
            ->values();

        $postsPage = $this->getPage('postsPage');
        $postsTotal = $postItems->count();

        $posts = new LengthAwarePaginator(
            $postItems->forPage($postsPage, $this->postsPerPage)->values(),
            $postsTotal,
            $this->postsPerPage,
            $postsPage,
            [
                'path' => request()->url(),
                'pageName' => 'postsPage',
            ]
        );

        $posts->appends(request()->query());

        $lecturerItems = $this->lecturerItems();

        $lecturersPage = $this->getPage('lecturersPage');
        $lecturersTotal = $lecturerItems->count();

        $lecturers = new LengthAwarePaginator(
            $lecturerItems->forPage($lecturersPage, $this->lecturersPerPage)->values(),
            $lecturersTotal,
            $this->lecturersPerPage,
            $lecturersPage,
            [
                'path' => request()->url(),
                'pageName' => 'lecturersPage',
            ]
        );

        $lecturers->appends(request()->query());

        return [
            'posts' => $posts,
            'lecturers' => $lecturers,
            'postsTotal' => $postsTotal,
            'lecturersTotal' => $lecturersTotal,
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage('postsPage');
        $this->resetPage('lecturersPage');
        $this->tab = 'all';
    }

    private function lecturerSearchScore(Lecturer $lecturer, string $keyword): int
    {
        $keyword = trim($keyword);
        $normalizedKeyword = $this->normalizeText($keyword);

        $name = (string) ($lecturer->user?->name ?? '');
        $email = (string) ($lecturer->user?->email ?? '');
        $positions = implode(' ', $this->positionTextsForSearch($lecturer));

        $score = 0;

        if ($keyword !== '' && mb_stripos($name, $keyword) !== false) {
            $score += 1000;
        }

        if ($keyword !== '' && mb_stripos($positions, $keyword) !== false) {
            $score += 500;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($name), $normalizedKeyword)) {
            $score += 300;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($positions), $normalizedKeyword)) {
            $score += 150;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($email), $normalizedKeyword)) {
            $score += 50;
        }

        return $score;
    }
    private function postSearchScore(Post $post, string $keyword): int
    {
        $keyword = trim($keyword);
        $normalizedKeyword = $this->normalizeText($keyword);

        $title = (string) $post->getTranslation('title', app()->getLocale(), false);

        $excerpt = method_exists($post, 'getExcerptOrAuto')
            ? (string) $post->getExcerptOrAuto(app()->getLocale(), 300)
            : '';

        $score = 0;

        if ($keyword !== '' && mb_stripos($title, $keyword) !== false) {
            $score += 1000;
        }

        if ($keyword !== '' && mb_stripos($excerpt, $keyword) !== false) {
            $score += 500;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($title), $normalizedKeyword)) {
            $score += 300;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($excerpt), $normalizedKeyword)) {
            $score += 100;
        }

        if ($post->is_featured) {
            $score += 20;
        }

        return $score;
    }
    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'posts', 'lecturers'], true)) {
            $tab = 'all';
        }

        $this->tab = $tab;

        $this->resetPage('postsPage');
        $this->resetPage('lecturersPage');
    }
};
?>

<div class="container mx-auto px-4 py-8">
    <x-slot:title>
        {{ __('Search') }}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span class="whitespace-nowrap font-semibold text-slate-700">
            {{ __('Search') }}
        </span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{ __('Search') }}
    </x-slot:titleBreadcrumb>

    <section class="rounded-xl bg-white border border-gray-200 p-4 lg:p-6 lg:py-4 shadow-sm mb-4">
        <div>
            <x-input
                icon="o-magnifying-glass"
                placeholder="{{ __('Enter search keywords...') }}"
                wire:model.live.debounce.500ms="search"
                class="w-full"
                clearable
                label="{{ __('Search') }}"
            />
        </div>

        @if($search)
            <p class="mt-3 text-gray-600">
                {{ __('Search results for') }}:
                <strong>"{{ $search }}"</strong>
                @if($this->isValidSearch())
                    <span>
                        ({{ $postsTotal + $lecturersTotal }} {{ __('results') }})
                    </span>
                @endif
            </p>
        @endif
    </section>
    @php
        $showTabs = $this->isValidSearch() && $postsTotal > 0 && $lecturersTotal > 0;

        $activeTab = in_array($tab, ['all', 'posts', 'lecturers'], true)
            ? $tab
            : 'all';
    @endphp
    @if($showTabs)
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="setTab('all')"
                @class([
                    'px-4 py-2 rounded-lg text-sm font-semibold border transition',
                    'bg-fita text-white border-fita' => $activeTab === 'all',
                    'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' => $activeTab !== 'all',
                ])
            >
                Tất cả
                <span class="ml-1 text-xs opacity-80">
                {{ $postsTotal + $lecturersTotal }}
            </span>
            </button>

            <button
                type="button"
                wire:click="setTab('posts')"
                @class([
                    'px-4 py-2 rounded-lg text-sm font-semibold border transition',
                    'bg-fita text-white border-fita' => $activeTab === 'posts',
                    'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' => $activeTab !== 'posts',
                ])
            >
                Bài viết
                <span class="ml-1 text-xs opacity-80">
                {{ $postsTotal }}
            </span>
            </button>

            <button
                type="button"
                wire:click="setTab('lecturers')"
                @class([
                    'px-4 py-2 rounded-lg text-sm font-semibold border transition',
                    'bg-fita text-white border-fita' => $activeTab === 'lecturers',
                    'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' => $activeTab !== 'lecturers',
                ])
            >
                Giảng viên
                <span class="ml-1 text-xs opacity-80">
                {{ $lecturersTotal }}
            </span>
            </button>
        </div>
    @endif
    <div class="relative">
        <div
            wire:loading.delay.short
            wire:target="search,gotoPage,nextPage,previousPage,setPage, postsPage,lecturersPage, setTab"
            class="absolute inset-0 z-30 bg-white/65 backdrop-blur-[2px] rounded-xl transition-all duration-300"
        >
            <div class="sticky top-[50vh] w-full flex flex-col items-center gap-2 mt-10">
                <x-loading class="text-primary loading-lg" />
                <span class="text-md font-medium text-gray-500">
                    {{ __('Loading data...') }}
                </span>
            </div>
        </div>

        @if(!$this->isValidSearch())
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <x-icon name="o-magnifying-glass" class="w-16 h-16 mx-auto text-gray-300 mb-4" />
                <p class="text-gray-500 text-lg">
                    {{__('Please enter at least 2 characters to search')}}.
                </p>
            </div>
        @elseif($postsTotal === 0 && $lecturersTotal === 0)
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <x-icon name="o-document-magnifying-glass" class="w-16 h-16 mx-auto text-gray-300 mb-4" />
                <p class="text-gray-500 text-lg">
                    {{__('No matching results found')}}.
                </p>
            </div>
        @else

            {{-- BÀI VIẾT --}}
            @if($postsTotal > 0 && (!$showTabs || in_array($activeTab, ['all', 'posts'], true)))
                <section class="mb-12">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">
                                {{__('Posts')}}
                            </h2>
                            <p class="text-gray-500 text-sm mt-1">
                                {{ $postsTotal }} {{__('relevant article results')}}
                            </p>
                        </div>

                        @if($postsTotal > 0)
                            <a href="{{ route('client.posts.index', ['tim-kiem' => $search]) }}"
                               wire:navigate
                               class="text-sm font-semibold text-fita hover:underline">
                                {{__('View in posts page')}}
                            </a>
                        @endif
                    </div>

                    <div class="bg-white rounded-2xl shadow-md divide-y">
                        @foreach($posts as $post)
                            <a href="{{ $post->client_url }}"
                               wire:navigate
                               class="group block p-4 sm:p-5 hover:bg-slate-50 transition-colors first:rounded-t-2xl last:rounded-b-2xl">
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <div class="w-full sm:w-44 lg:h-28 h-50 bg-gray-200 rounded-lg overflow-hidden shrink-0 relative">
                                        @if($post->thumbnail)
                                            <img
                                                src="{{ Storage::url($post->thumbnail) }}"
                                                class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                                                alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        @elseif($post->post_default_image_id && $post->defaultImage?->image_path)
                                            <img
                                                src="{{ Storage::url($post->defaultImage?->image_path) }}"
                                                class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                                                alt="No image"
                                                loading="lazy"
                                                decoding="async"
                                            >

                                            @if($post->defaultImage?->show_title)
                                                <div class="absolute inset-0 flex items-center justify-center p-5" style="container-type: inline-size;">
                                                    <p class="line-clamp-4 font-bold"
                                                       :style="{
                                                            color: '{{ $post->defaultImage?->text_color ?? '#ffffff' }}',
                                                            fontSize: 'clamp(8px, calc({{ $post->defaultImage?->text_size ?? 18 }} / 1200 * 100cqw), 60px)',
                                                            lineHeight: 1.1,
                                                            textAlign: '{{ $post->defaultImage?->text_alignment ?? 'center' }}',
                                                       }"
                                                       x-text="'{{ $post->getTranslation('title', app()->getLocale()) }}'"
                                                    ></p>
                                                </div>
                                            @endif
                                        @else
                                            <img
                                                src="{{ asset('assets/images/post-6.jpg') }}"
                                                class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                                                alt="No image"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        @endif

                                        @if($post->is_featured)
                                            <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-red-500 pe-2 ps-1 py-0.5 text-[10px] font-bold text-white shadow-md rounded-br-xl">
                                                {{ __('Featured News') }}
                                            </div>
                                        @elseif($this->isNewPost($post))
                                            <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-[#F6A309] pe-2 ps-1 py-0.5 text-[10px] font-bold text-white shadow-md rounded-br-xl">
                                                {{ __('New') }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 text-md text-gray-500 mb-2">
                                            @if($post->show_category && $post->categories->isNotEmpty())
                                                @foreach($post->categories as $postCategory)
                                                    @if($postCategory->getTranslation('name', app()->getLocale(), false))
                                                        <span class="inline-block bg-fita text-white px-2 py-1 rounded">
                                                            {{ $postCategory->getTranslation('name', app()->getLocale()) }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            @endif

                                            @if($post->show_author && $post->user)
                                                <span class="inline-flex items-center gap-1">
                                                    <x-icon name="o-user" class="w-4.5 h-4.5" />
                                                    {{ $post->user->name }}
                                                </span>
                                            @endif

                                            @if($post->show_published_at)
                                                <span>{{ $post->published_at->format('d/m/Y') }}</span>
                                            @endif

                                            @if($post->show_views)
                                                <span class="inline-flex items-center gap-1">
                                                    <x-icon name="o-eye" class="w-4.5 h-4.5" />
                                                    {{ number_format($post->views) }}
                                                </span>
                                            @endif
                                        </div>

                                        <h3 class="font-bold text-lg mb-2 line-clamp-2 group-hover:text-fita transition-colors">
                                            {{ $post->getTranslation('title', app()->getLocale()) }}
                                        </h3>

                                        <p class="text-md text-gray-600 line-clamp-2">
                                            {{ $post->getExcerptOrAuto(app()->getLocale(), 150) }}
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $posts->links() }}
                    </div>

                </section>
            @endif
            {{-- GIẢNG VIÊN --}}
            @if($lecturersTotal > 0 && (!$showTabs || in_array($activeTab, ['all', 'lecturers'], true)))
                <section>
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">
                                {{__('Lecturers')}}
                            </h2>
                            <p class="text-gray-500 text-sm mt-1">
                                {{ $lecturersTotal }} {{__('suitable faculty results')}}
                            </p>
                        </div>

                        @if($lecturersTotal > 0)
                            <a href="{{ route('client.lecturers.index', ['tim-kiem' => $search]) }}"
                               wire:navigate
                               class="text-md font-semibold text-fita hover:underline">
                                {{__('View in lecturers page')}}
                            </a>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6">
                        @foreach($lecturers as $lecturer)
                            @php
                                $profileUrl = route('client.lecturers.profile', ['slug' => $lecturer->slug]);
                                $avatar = $lecturer->user?->avatar
                                    ? asset($lecturer->user->avatar)
                                    : asset('/assets/images/default-user-image.png');

                                $academicTitleLabel = $this->localizeAcademicTitle($lecturer->academic_title);
                                $degreeLabel = $this->localizeDegree($lecturer->degree);
                                $positionLabel = $lecturer->positionForLocale(app()->getLocale());
                            @endphp

                            <article class="bg-white rounded-b-md border border-gray-200 overflow-hidden shadow-sm hover:shadow-md hover:scale-105 hover:[&_a_h2]:text-fita transition">
                                <a href="{{ $profileUrl }}" wire:navigate>
                                    <img
                                        src="{{ $avatar }}"
                                        alt="{{ $lecturer->user?->name }}"
                                        class="h-120 lg:h-64 w-full object-cover"
                                    />
                                </a>

                                <div class="py-4 px-2">
                                    <a href="{{ $profileUrl }}" wire:navigate class="block text-center">
                                        <h2 class="text-md uppercase font-semibold text-gray-900 hover:text-fita transition line-clamp-2">
                                            @if($academicTitleLabel)
                                                {{ $academicTitleLabel }}
                                                @if(app()->getLocale() === 'vi')
                                                    ,
                                                @endif
                                            @endif

                                            @if($degreeLabel)
                                                {{ $degreeLabel }}
                                            @endif

                                            {{ $lecturer->user?->name }}
                                        </h2>

                                        @if($positionLabel)
                                            <p class="font-medium text-gray-800">
                                                {{ $positionLabel }}
                                            </p>
                                        @endif
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $lecturers->links() }}
                    </div>
                </section>
            @endif
        @endif
    </div>
</div>
