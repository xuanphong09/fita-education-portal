<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;
new
#[Layout('layouts.client')]
class extends Component {
    use Toast;

    public Post $post;
    public Collection $relatedPosts;
    public Collection $recentPosts;

    protected function publishedPostsQuery()
    {
        return Post::with(['category', 'user'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
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

    public function mount(string $slug)
    {
        $locale = app()->getLocale();

        // Keep list props as Collection to avoid Blade isNotEmpty() errors.
        $this->relatedPosts = collect();
        $this->recentPosts = collect();

        // Tìm bài viết theo slug (canonical hoặc từ slug_translations)
        $this->post = $this->publishedPostsQuery()
            ->where(function($q) use ($slug, $locale) {
                $q->where('slug', $slug)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(slug_translations, '$.{$locale}')) = ?", [$slug]);
            })
            ->firstOrFail();

        if (! $this->isVisibleInLocale($this->post, $locale)) {
            return redirect()->route('client.posts.index');
        }

        // Tăng lượt xem
        $this->post->incrementView();

        // Bài viết liên quan (cùng danh mục)
        if ($this->post->category_id) {
            $this->relatedPosts = $this->publishedPostsQuery()
                ->where('category_id', $this->post->category_id)
                ->where('id', '!=', $this->post->id)
                ->orderBy('published_at', 'desc')
                ->limit($locale === 'en' ? 10 : 3)
                ->get()
                ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
                ->take(3)
                ->values();
        }

        // Bài viết mới nhất
        $this->recentPosts = $this->publishedPostsQuery()
            ->where('id', '!=', $this->post->id)
            ->orderBy('published_at', 'desc')
            ->limit($locale === 'en' ? 15 : 5)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(5)
            ->values();
    }

    public function getSeoMetaProperty(): array
    {
        $locale = app()->getLocale();
        $title = $this->post->getTranslation('seo_title', $locale, false)
              ?: $this->post->getTranslation('title', $locale);
        $description = $this->post->getTranslation('seo_description', $locale, false)
                    ?: $this->post->getExcerptOrAuto($locale, 160);

        return [
            'title' => $title,
            'description' => $description,
            'image' => $this->post->thumbnail ? Storage::url($this->post->thumbnail) : null,
            'type' => 'article',
            'published_time' => $this->post->published_at?->toIso8601String(),
        ];
    }
};
?>

<div class="container mx-auto px-4 py-8">
    {{-- SEO Meta --}}
    <x-slot:seo>
        @php
            $seo = $this->getSeoMetaProperty();
        @endphp
        <x-seo
            :title="$seo['title']"
            :description="$seo['description']"
            :image="$seo['image']"
            :type="$seo['type']"
        >
            @if($seo['published_time'])
                <meta property="article:published_time" content="{{ $seo['published_time'] }}">
            @endif
            @if($post->category)
                <meta property="article:section" content="{{ $post->category->getTranslation('name', app()->getLocale()) }}">
            @endif
            @if($post->user)
                <meta property="article:author" content="{{ $post->user->name }}">
            @endif
        </x-seo>
    </x-slot:seo>

    <x-slot:breadcrumb>
        <a href="{{route('client.posts.index')}}" wire:navigate class="whitespace-nowrap font-semibold text-slate-700 hover:text-fita">{{__('Posts')}}</a>
        <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>
        <span class="line-clamp-1">{{ $post->getTranslation('title', app()->getLocale()) }}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{__('Posts')}}
    </x-slot:titleBreadcrumb>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Main Content --}}
        <div class="lg:col-span-2">
            <article class="bg-white rounded-lg shadow-lg overflow-hidden">
                {{-- Featured Image --}}
                @if($post->thumbnail)
                    <div class="aspect-video bg-gray-200 overflow-hidden">
                        <img
                            src="{{ Storage::url($post->thumbnail) }}"
                            alt="{{ $post->getTranslation('title', app()->getLocale()) }}"
                            class="w-full h-full object-cover"
                        />
                    </div>
                @endif

                <div class="p-4 lg:p-6">
                    {{-- Category Badge --}}
                    @if($post->category && $post->category->getTranslation('name', app()->getLocale(), false))
                        <a
                            href="{{ route('client.posts.index', ['danh-muc' => $post->category->slug]) }}"
                            wire:navigate
                            class="inline-block bg-fita text-white text-sm px-3 py-1 rounded mb-4 hover:bg-fita2 transition-colors"
                        >
                            {{ $post->category->getTranslation('name', app()->getLocale()) }}
                        </a>
                    @endif

                    {{-- Title --}}
                    <h1 class="text-3xl lg:text-4xl font-bold mb-4">
                        {{ $post->getTranslation('title', app()->getLocale()) }}
                    </h1>

                    {{-- Meta Info --}}
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 pb-6 mb-6 border-b">
                        @if($post->user)
                            <div class="flex items-center gap-2">
                                <x-icon name="o-user" class="w-4 h-4" />
                                <span>{{ $post->user->name }}</span>
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            <x-icon name="o-calendar" class="w-4 h-4" />
                            <span>{{ $post->published_at->isoFormat(app()->getLocale() === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-eye" class="w-4 h-4" />
                            <span>{{ number_format($post->views) }} {{ __('views') }}</span>
                        </div>
                    </div>

                    {{-- Excerpt --}}
                    @if($post->getTranslation('excerpt', app()->getLocale(), false))
                        <div class="bg-gray-50 border-l-4 border-fita p-4 mb-6 italic text-gray-700">
                            {{ $post->getTranslation('excerpt', app()->getLocale()) }}
                        </div>
                    @endif

                    {{-- Content --}}
                    <div class="max-w-none">
                        {!! $post->getTranslation('content', app()->getLocale()) !!}
                    </div>

                    {{-- Share Buttons --}}
                    <div class="mt-8 pt-6 border-t">
                        <h3 class="font-bold mb-3">{{ __('Share this post') }}</h3>
                        <div class="flex gap-2">
                            <a
                                href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
                                target="_blank"
                                class="btn btn-sm bg-blue-600 hover:bg-blue-700 text-white border-0"
                            >
                                <x-icon name="o-share" class="w-4 h-4" />
                                Facebook
                            </a>
                            <a
                                href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($post->getTranslation('title', app()->getLocale())) }}"
                                target="_blank"
                                class="btn btn-sm bg-sky-500 hover:bg-sky-600 text-white border-0"
                            >
                                <x-icon name="o-share" class="w-4 h-4" />
                                Twitter
                            </a>
                            <button
                                x-on:click="
                                    navigator.clipboard.writeText(@js(url()->current()))
                                        .then(() => $wire.success(@js(__('Link copied!'))))
                                        .catch(() => $wire.error(@js(__('Cannot copy link'))))
                                "
                                class="btn btn-sm bg-gray-600 hover:bg-gray-700 text-white border-0"
                            >
                                <x-icon name="o-link" class="w-4 h-4" />
                                {{ __('Copy Link') }}
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            {{-- Related Posts --}}
            @if($relatedPosts->isNotEmpty())
                <div class="mt-8">
                    <h2 class="text-2xl font-bold mb-4">{{ __('Related Posts') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach($relatedPosts as $related)
                            <a href="{{ route('client.posts.show', $related->slug) }}" wire:navigate class="group">
                                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                                    <div class="aspect-video bg-gray-200 overflow-hidden">
                                        @if($related->thumbnail)
                                            <img
                                                src="{{ Storage::url($related->thumbnail) }}"
                                                alt="{{ $related->getTranslation('title', app()->getLocale()) }}"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-fita to-fita2">
                                                <x-icon name="o-photo" class="w-12 h-12 text-white opacity-50" />
                                            </div>
                                        @endif
                                    </div>
                                    <div class="py-3 px-2 lg:h-24">
                                        <h3 class="font-bold text-md line-clamp-2 group-hover:text-fita transition-colors">
                                            {{ $related->getTranslation('title', app()->getLocale()) }}
                                        </h3>
                                        <p class="text-sm text-gray-500 mt-2">
                                            {{ $related->published_at->format('d/m/Y') }}
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="lg:col-span-1">
            {{-- Recent Posts --}}
            @if($recentPosts->isNotEmpty())
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <h3 class="font-bold text-xl mb-4">{{ __('Recent Posts') }}</h3>
                    <div class="space-y-4">
                        @foreach($recentPosts as $recent)
                            <a href="{{ route('client.posts.show', $recent->slug) }}" wire:navigate class="group flex gap-3">
                                <div class="w-20 h-20 shrink-0 bg-gray-200 rounded overflow-hidden">
                                    @if($recent->thumbnail)
                                        <img
                                            src="{{ Storage::url($recent->thumbnail) }}"
                                            alt="{{ $recent->getTranslation('title', app()->getLocale()) }}"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-linear-to-br from-fita to-fita2">
                                            <x-icon name="o-photo" class="w-6 h-6 text-white opacity-50" />
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-md line-clamp-2 group-hover:text-fita transition-colors">
                                        {{ $recent->getTranslation('title', app()->getLocale()) }}
                                    </h4>
                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ $recent->published_at->format('d/m/Y') }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Back to List --}}
            <div class="bg-white rounded-lg shadow-md p-4">
                <a
                    href="{{ route('client.posts.index') }}"
                    wire:navigate
                    class="btn btn-block bg-fita hover:bg-fita2 text-white border-0"
                >
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    {{ __('Back to Posts') }}
                </a>
                @if($post->category)
                    <a
                        href="{{ route('client.posts.index', ['danh-muc' => $post->category->slug]) }}"
                        wire:navigate
                        class="btn btn-block btn-outline border-fita text-fita hover:bg-fita hover:text-white mt-2"
                    >
                        {{ __('More from') }} {{ $post->category->getTranslation('name', app()->getLocale()) }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

