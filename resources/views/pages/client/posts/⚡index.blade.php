<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Post;
use App\Models\Category;
use App\Services\PostSearchService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new
#[Layout('layouts.client')]
class extends Component {
    use WithPagination;

    #[Url(as: 'danh-muc')]
    public ?string $categorySlug = null;

    #[Url(as: 'tim-kiem')]
    public string $search = '';

    protected function resolveCurrentCategory(?string $categorySlug): ?Category
    {
        if (!$categorySlug) {
            return null;
        }

        $locale = app()->getLocale();
        $isEn   = $locale === 'en';

        return Category::query()
            ->where('is_active', true)
            ->when(
                $isEn,
                fn ($query) => $query->whereRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))), ''), NULL) IS NOT NULL")
            )
            ->where(function ($query) use ($categorySlug, $locale) {
                $query->where('slug', $categorySlug)
                    ->orWhereRaw(
                        "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(slug_translations, '$." . $locale . "')), '') = ?",
                        [$categorySlug]
                    );
            })
            ->first();
    }

    public function mount()
    {
        // Support legacy links (?q= / ?query=) and normalize to this page's search state.
        if ($this->search === '') {
            $legacySearch = trim((string) (request()->query('q') ?? request()->query('query') ?? ''));
            if ($legacySearch !== '') {
                $this->search = $legacySearch;
            }
        }

        if ($this->categorySlug && !$this->resolveCurrentCategory($this->categorySlug)) {
            $params = [];

            if ($this->search !== '') {
                $params['tim-kiem'] = $this->search;
            }

            return redirect()->route('client.posts.index', $params);
        }
    }

    public function with(): array
    {
        $locale = app()->getLocale();
        $isEn   = $locale === 'en';

        // Published rule reused by list/featured/count queries.
        $applyPublished = function ($query) {
            $query->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        };

        // EN locale: hide categories without EN name.
        $applyCategoryLocale = function ($query) use ($isEn) {
            if ($isEn) {
                $query->whereRaw("COALESCE(NULLIF(TRIM(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))), ''), NULL) IS NOT NULL");
            }
        };

        $search = trim(preg_replace('/\s+/u', ' ', $this->search) ?? '');
        $terms  = PostSearchService::parseTerms($search);

        // Resolve selected category by canonical slug or translated slug of current locale.
        $currentCategory = $this->resolveCurrentCategory($this->categorySlug);

        $selectedCategoryId = $currentCategory?->id;

        $postQuery = Post::query()
            ->with(['category', 'user'])
            ->tap($applyPublished)
            ->when($selectedCategoryId, fn ($q) => $q->where('category_id', $selectedCategoryId));

        // Hide posts without EN content when locale is EN (uses virtual cols if available).
        PostSearchService::applyLocaleFilter($postQuery, $isEn);

        // Apply search terms: FULLTEXT → virtual-col LIKE → JSON fallback.
        PostSearchService::applyTerms($postQuery, $terms, $isEn);

        $featuredPosts = (clone $postQuery)
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->take(5)
            ->get();

        $featuredIds = $featuredPosts->pluck('id');

        $listPosts = (clone $postQuery)
            ->when($featuredIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $featuredIds))
            ->orderByDesc('published_at')
            ->paginate(6);

        $searchResultsTotal = $featuredPosts->count() + $listPosts->total();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->tap($applyCategoryLocale)
            ->with(['children' => function ($query) use ($applyCategoryLocale) {
                $query->where('is_active', true)
                    ->tap($applyCategoryLocale)
                    ->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        $visibleCategoryIds = $categories
            ->flatMap(fn ($category) => [$category->id, ...$category->children->pluck('id')->all()])
            ->unique()
            ->values();

        $postCounts = Post::query()
            ->selectRaw('category_id, COUNT(*) as total')
            ->tap($applyPublished)
            ->tap(fn ($q) => PostSearchService::applyLocaleFilter($q, $isEn))
            ->when($visibleCategoryIds->isNotEmpty(), fn ($q) => $q->whereIn('category_id', $visibleCategoryIds))
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = $categories->filter(function ($category) use ($postCounts) {
            if (($postCounts[$category->id] ?? 0) > 0) {
                return true;
            }

            foreach ($category->children as $child) {
                if (($postCounts[$child->id] ?? 0) > 0) {
                    return true;
                }
            }

            return false;
        })->values();

        foreach ($categories as $category) {
            $category->setRelation(
                'children',
                $category->children
                    ->filter(fn ($child) => ($postCounts[$child->id] ?? 0) > 0)
                    ->values()
            );
        }

        return [
            'featuredPosts' => $featuredPosts,
            'listPosts' => $listPosts,
            'categories' => $categories,
            'currentCategory' => $currentCategory,
            'postCounts' => $postCounts,
            'searchResultsTotal' => $searchResultsTotal,
        ];
    }

    public function filterByCategory(?string $categorySlug)
    {
        $this->categorySlug = $categorySlug;
        $this->search = '';  // Clear search when switching category
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->categorySlug = null;
        $this->search = '';
        $this->resetPage();
    }
};
?>

<div class="container mx-auto px-4 py-8">
    <x-slot:title>
        {{ $currentCategory ? $currentCategory->getTranslation('name', app()->getLocale()) : __('Posts') }}
    </x-slot:title>

    {{-- Breadcrumb --}}
    <x-slot:breadcrumb>
        <span>{{__('Posts')}}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{__('Posts')}}
    </x-slot:titleBreadcrumb>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        {{-- Main Content --}}
        <div class="order-2 lg:order-0 lg:col-span-3 relative">
            <div
                wire:loading.delay.short
{{--                wire:target="search,filterByCategory,resetFilters,gotoPage,nextPage,previousPage,setPage"--}}
                class="absolute inset-0 z-30 bg-white/65 backdrop-blur-[2px] rounded-xl transition-all duration-300"
            >
                <div class="sticky top-[50vh] w-full flex flex-col items-center gap-2 mt-10">
                    <x-loading class="text-primary loading-lg" />
                    <span class="text-md font-medium text-gray-500">{{__('Loading...')}}</span>
                </div>
            </div>

            <div
                wire:loading.class="opacity-60"
                wire:loading.class.remove="opacity-100"
                wire:target="search,filterByCategory,resetFilters,gotoPage,nextPage,previousPage,setPage"
                class="transition-opacity duration-150"
            >
            @if($currentCategory)
                <div class="mb-6">
                    <h1 class="text-3xl font-bold mb-2">{{ $currentCategory->getTranslation('name', app()->getLocale()) }}</h1>
                    @if($currentCategory->description)
                        <p class="text-gray-600">{{ $currentCategory->getTranslation('description', app()->getLocale()) }}</p>
                    @endif
                </div>
            @else
                <h1 class="text-3xl font-bold mb-6">{{ __('All Posts') }}</h1>
            @endif

            @if($search)
                <div class="mb-4">
                    <p class="text-gray-600">
                        {{ __('Search results for') }}: <strong>"{{ $search }}"</strong>
                        <span class="text-md">({{ $searchResultsTotal }} {{ __('results') }})</span>
                    </p>
                </div>
            @endif

            {{-- Posts List - Magazine layout --}}
            @if($listPosts->isEmpty() && $featuredPosts->isEmpty())
                <div class="bg-white rounded-lg shadow-md p-12 text-center">
                    <x-icon name="o-document-text" class="w-16 h-16 mx-auto text-gray-300 mb-4" />
                    <p class="text-gray-500 text-lg">{{ __('No posts found') }}</p>
                </div>
            @else
                @if($featuredPosts->isNotEmpty())
                    @php
                        $featuredSlides = [];
                        foreach ($featuredPosts as $post) {
                            $featuredSlides[] = [
                                'url' => route('client.posts.show', $post->slug),
                                'title' => $post->getTranslation('title', app()->getLocale()),
                                'excerpt' => $post->getExcerptOrAuto(app()->getLocale(), 220),
                                'category' => optional($post->category)->getTranslation('name', app()->getLocale()),
                                'author' => optional($post->user)->name,
                                'date' => optional($post->published_at)->format('d/m/Y'),
                                'views' => number_format($post->views),
                                'thumbnail' => $post->thumbnail ? Storage::url($post->thumbnail) : null,
                            ];
                        }
                    @endphp

                    <div
                        wire:key="featured-slider-{{ $search }}-{{ $categorySlug }}"
                        class="mb-6 relative"
                        x-data="{
                            slides: @js($featuredSlides),
                            index: 0,
                            hovered: false,
                            timer: null,
                            init() {
                                this.startAuto();
                            },
                            startAuto() {
                                this.stopAuto();
                                this.timer = setInterval(() => {
                                    if (!this.hovered && this.slides.length > 1) {
                                        this.index = (this.index + 1) % this.slides.length;
                                    }
                                }, 5000);
                            },
                            stopAuto() {
                                if (this.timer) {
                                    clearInterval(this.timer);
                                    this.timer = null;
                                }
                            },
                            next() {
                                if (this.index < this.slides.length - 1) this.index++;
                            },
                            prev() {
                                if (this.index > 0) this.index--;
                            },
                            go(i) { this.index = i; },
                            get canPrev() { return this.index > 0; },
                            get canNext() { return this.index < this.slides.length - 1; },
                            get current() { return this.slides[this.index] || null; }
                        }"
                        @mouseenter="hovered = true"
                        @mouseleave="hovered = false"
                    >
                        <a :href="current?.url" class="group block" wire:navigate>
                            <div class="bg-white rounded-2xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 lg:grid lg:grid-cols-2 lg:h-70">
                                <div class="aspect-video lg:aspect-auto bg-gray-200 overflow-hidden relative">
                                    <div class="absolute top-3 left-3 z-10 inline-flex items-center gap-1 bg-warning text-white px-2.5 py-1 rounded-full text-xs font-semibold shadow">
                                        <x-icon name="s-star" class="w-3.5 h-3.5" />
                                        {{ __('Featured News') }}
                                    </div>
                                    <template x-if="current && current.thumbnail">
                                        <img
                                            :src="current.thumbnail"
                                            alt="Featured post"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        />
                                    </template>
                                    <template x-if="!current || !current.thumbnail">
                                        <div class="w-full h-full flex items-center justify-center bg-linear-to-br from-fita to-fita2">
                                            <x-icon name="o-photo" class="w-16 h-16 text-white opacity-50" />
                                        </div>
                                    </template>
                                </div>

                                <div class="p-6 flex flex-col justify-between min-h-72">
                                    <div class="mb-3 flex items-center gap-2 text-md text-gray-500 flex-wrap">
                                        <template x-if="current && current.category">
                                            <span class="inline-block bg-fita text-white px-2 py-1 rounded" x-text="current.category"></span>
                                        </template>
                                        <template x-if="current && current.author">
                                            <span class="inline-flex items-center gap-1">
                                                <x-icon name="o-user" class="w-3.5 h-3.5" />
                                                <span x-text="current.author"></span>
                                            </span>
                                        </template>
                                        <span x-text="current?.date"></span>
                                    </div>

                                    <h2 class="text-2xl font-bold mb-3 group-hover:text-fita transition-colors line-clamp-2" x-text="current?.title"></h2>
                                    <p class="text-gray-600 mb-4 line-clamp-3" x-text="current?.excerpt"></p>

                                    <div class="mt-auto flex items-center justify-between text-md text-gray-500 pt-4 border-t">
                                        <span class="inline-flex items-center gap-1">
                                            <x-icon name="o-eye" class="w-4 h-4" />
                                            <span x-text="(current && current.views ? current.views : 0) + ' {{ __('views') }}'"></span>
                                        </span>
                                        <span class="font-semibold text-fita">{{ __('Read more') }}
                                            <x-icon name="s-arrow-right"></x-icon>
                                        </span>
                                    </div>
                                </div>

                            </div>
                        </a>
                        <div
                            class="absolute inset-y-0 left-0 right-0 z-20 pointer-events-none"
                            x-show="hovered && slides.length > 1"
                            x-cloak
                        >
                            <template x-if="canPrev">
                                <div class="absolute left-1 top-2/5 -translate-y-1/2 pointer-events-auto">
                                    <x-button
                                        icon="s-chevron-left"
                                        class="btn-sm btn-circle bg-fita text-white"
                                        @click.stop.prevent="prev()"
                                    />
                                </div>
                            </template>

                            <template x-if="canNext">
                                <div class="absolute right-1 top-2/5 -translate-y-1/2 pointer-events-auto">
                                    <x-button
                                        icon="s-chevron-right"
                                        class="btn-sm btn-circle bg-fita text-white"
                                        @click.stop.prevent="next()"
                                    />
                                </div>
                            </template>
                        </div>

                        <div x-show="slides.length > 1" class="flex items-center justify-center mt-3">
                            <div class="flex gap-1">
                                <template x-for="(slide, i) in slides" :key="i">
                                    <button
                                        type="button"
                                        @click="go(i)"
                                        class="w-2.5 h-2.5 rounded-full transition"
                                        :class="i === index ? 'bg-fita scale-115' : 'bg-gray-300'"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>
                @endif

                @if($listPosts->isNotEmpty())
                <div class="bg-white rounded-2xl shadow-md divide-y">
                    @foreach($listPosts as $post)
                        <a href="{{ route('client.posts.show', $post->slug) }}" wire:navigate class="group block p-4 sm:p-5 hover:bg-slate-50 transition-colors">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="w-full sm:w-44 lg:h-28 h-50 bg-gray-200 rounded-lg overflow-hidden shrink-0">
                                    @if($post->thumbnail)
                                        <img
                                            src="{{ Storage::url($post->thumbnail) }}"
                                            alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-linear-to-br from-fita to-fita2">
                                            <x-icon name="o-photo" class="w-10 h-10 text-white opacity-50" />
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-md text-gray-500 mb-2">
                                        @if($post->is_featured)
                                            <span class="inline-flex items-center gap-1 bg-warning/20 text-warning px-2 py-1 rounded font-semibold ring-1 ring-warning/30">
                                                <x-icon name="s-star" class="w-3 h-3" />
                                                Nổi bật
                                            </span>
                                        @endif
                                        @if($post->category && $post->category->getTranslation('name', app()->getLocale()))
                                            <span class="inline-block bg-fita text-white px-2 py-1 rounded">
                                                {{ $post->category->getTranslation('name', app()->getLocale()) }}
                                            </span>
                                        @endif
                                        @if($post->user)
                                            <span class="inline-flex items-center gap-1">
                                                <x-icon name="o-user" class="w-4.5 h-4.5" />
                                                {{ $post->user->name }}
                                            </span>
                                        @endif
                                        <span>{{ $post->published_at->format('d/m/Y') }}</span>
                                        <span class="inline-flex items-center gap-1">
                                            <x-icon name="o-eye" class="w-4.5 h-4.5" />
                                            {{ number_format($post->views) }}
                                        </span>
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

                {{-- Pagination --}}
                <div class="mt-8">
                    {{ $listPosts->links() }}
                </div>
                @endif
            @endif
        </div>
        </div>

        {{-- Sidebar --}}
        <div class="order-1 lg:order-0 lg:col-span-1 mt-15">
            {{-- Search Box --}}
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-bold text-lg mb-4">{{ __('Search') }}</h3>
                <x-input
                    wire:model.live.debounce.500ms="search"
{{--                    wire:loading.attr="disabled"--}}
{{--                    wire:target="search"--}}
                    placeholder="{{ __('Search posts...') }}"
                    icon="o-magnifying-glass"
                    clearable
                />
            </div>

            {{-- Categories --}}
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-bold text-lg mb-4">{{ __('Categories') }}</h3>
                <ul class="space-y-2">
                    <li>
                        <button
                            wire:click="filterByCategory(null)"
                            wire:loading.attr="disabled"
                            wire:target="filterByCategory,resetFilters"
                            @class([
                                'w-full text-left px-3 py-2 rounded transition-colors',
                                'bg-fita text-white' => !$categorySlug,
                                'hover:bg-gray-100' => $categorySlug,
                            ])
                        >
                            {{ __('All Posts') }}
                        </button>
                    </li>
                    @foreach($categories as $category)
                        <li>
                            <button
                                wire:click="filterByCategory('{{ $category->slug }}')"
                                wire:loading.attr="disabled"
                                wire:target="filterByCategory,resetFilters"
                                @class([
                                    'w-full text-left px-3 py-2 rounded transition-colors',
                                    'bg-fita text-white' => $categorySlug === $category->slug,
                                    'hover:bg-gray-100' => $categorySlug !== $category->slug,
                                ])
                            >
                                {{ $category->getTranslation('name', app()->getLocale()) }}
                                <span class="text-sm opacity-75">({{ $postCounts[$category->id] ?? 0 }})</span>
                            </button>

                            @if($category->children->isNotEmpty())
                                <ul class="ml-4 mt-2 space-y-1">
                                    @foreach($category->children as $child)
                                        <li>
                                            <button
                                                wire:click="filterByCategory('{{ $child->slug }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="filterByCategory,resetFilters"
                                                @class([
                                                    'w-full text-left px-3 py-2 rounded text-md transition-colors',
                                                    'bg-fita2 text-white' => $categorySlug === $child->slug,
                                                    'hover:bg-gray-100' => $categorySlug !== $child->slug,
                                                ])
                                            >
                                                {{ $child->getTranslation('name', app()->getLocale()) }}
                                                <span class="text-xs opacity-75">({{ $postCounts[$child->id] ?? 0 }})</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if($categorySlug || $search)
                    <button
                        wire:click="resetFilters"
                        wire:loading.attr="disabled"
                        wire:target="resetFilters,filterByCategory,search"
                        class="w-full mt-4 px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded transition-colors text-md"
                    >
                        {{ __('Reset Filters') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>


