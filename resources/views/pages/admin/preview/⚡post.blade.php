<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Post;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

new
#[Layout('layouts.client')]
class extends Component {
    public int    $id;
    public bool   $isDraft = false;
    public string $locale  = 'vi';

    // Data hiển thị (từ cache hoặc DB)
    public string  $title        = '';
    public string  $content      = '';
    public string  $excerpt      = '';
    public string  $slug         = '';
    public string  $status       = 'draft';
    public ?string $thumbnail    = null;
    public ?string $categoryName = null;
    public ?string $authorName   = null;
    public ?string $publishedAt  = null;
    public int     $views        = 0;

    // Cache key
    private function cacheKey(): string
    {
        return 'post_preview_' . $this->id . '_' . auth()->id();
    }

    public function mount(int $id): void
    {
        $this->id      = $id;
        $this->isDraft = request()->boolean('draft');
        $this->locale  = app()->getLocale();

        $this->loadData();
    }

    private function loadData(): void
    {
        $locale = $this->locale;

        if ($this->isDraft && Cache::has($this->cacheKey())) {
            // ---- Đọc từ cache ---- (không fallback, EN rỗng thì hiển thị rỗng)
            $data = Cache::get($this->cacheKey());

            $this->title       = $data['title'][$locale]   ?? '';
            $this->content     = $data['content'][$locale] ?? '';
            $this->excerpt     = $data['excerpt'][$locale] ?? '';
            $this->slug        = $data['slug']             ?? '';
            $this->status      = $data['status']           ?? 'draft';
            $this->thumbnail   = $data['thumbnail']        ?? null;
            $this->publishedAt = $data['published_at']     ?? null;
            $this->views       = 0;

            if (!empty($data['category_id'])) {
                $cat = Category::find($data['category_id']);
                $this->categoryName = $cat?->getTranslatedName();
            }
            if (!empty($data['user_id'])) {
                $user = User::find($data['user_id']);
                $this->authorName = $user?->name;
            }
        } else {
            // ---- Đọc từ DB ---- (không fallback, EN rỗng thì hiển thị rỗng)
            $post = Post::with(['category', 'user'])->findOrFail($this->id);

            $this->title       = $post->getTranslation('title',   $locale, false) ?? '';
            $this->content     = $post->getTranslation('content', $locale, false) ?? '';
            $this->excerpt     = $post->getTranslation('excerpt', $locale, false) ?? '';
            $this->slug        = $post->slug        ?? '';
            $this->status      = $post->status;
            $this->thumbnail   = $post->thumbnail;
            $this->views       = $post->views;
            $this->publishedAt = $post->published_at?->format('d/m/Y');
            $this->categoryName = $post->category?->getTranslatedName();
            $this->authorName   = $post->user?->name;
        }
    }

    public function switchLocale(string $locale): void
    {
        $this->locale = $locale;
        app()->setLocale($locale);
        $this->loadData();
    }
};
?>

<div>
    <x-slot:title>{{ $title }}</x-slot:title>

    {{-- ===== Thanh preview bar ===== --}}
    <div class="fixed top-0 left-0 right-0 z-[9999] bg-gray-900 text-white text-sm flex items-center justify-between px-4 py-2 shadow-lg print:hidden">
        <div class="flex items-center gap-3">
            <x-icon name="o-eye" class="w-4 h-4 text-yellow-400"/>
            <span class="font-medium text-yellow-400">Chế độ xem trước</span>
            @if($isDraft)
                <span class="bg-yellow-500/20 text-yellow-300 text-xs px-2 py-0.5 rounded">Chưa lưu (cache)</span>
            @endif
            <span class="text-gray-400 hidden lg:block">—</span>
            <span class="text-gray-300 truncate max-w-xs hidden lg:block">{{ $title }}</span>
        </div>

        <div class="flex items-center gap-2">
            {{-- Chuyển ngôn ngữ --}}
            <button wire:click="switchLocale('vi')"
                    class="px-2 py-0.5 rounded text-xs {{ $locale === 'vi' ? 'bg-primary text-white' : 'bg-gray-700 hover:bg-gray-600' }}">
                🇻🇳 VI
            </button>
            <button wire:click="switchLocale('en')"
                    class="px-2 py-0.5 rounded text-xs {{ $locale === 'en' ? 'bg-primary text-white' : 'bg-gray-700 hover:bg-gray-600' }}">
                🇺🇸 EN
            </button>

            <span class="text-gray-600">|</span>

            {{-- Trạng thái --}}
            @php
                $statusMap = [
                    'draft'     => ['label' => 'Nháp',      'class' => 'text-yellow-400'],
                    'published' => ['label' => 'Đã đăng',   'class' => 'text-green-400'],
                    'archived'  => ['label' => 'Lưu trữ',   'class' => 'text-gray-400'],
                ];
                $s = $statusMap[$status] ?? $statusMap['draft'];
            @endphp
            <span class="text-xs {{ $s['class'] }}">● {{ $s['label'] }}</span>

            <span class="text-gray-600">|</span>

            <a href="{{ route('admin.post.edit', $id) }}"
               class="flex items-center gap-1 px-3 py-1 bg-primary rounded text-xs hover:bg-primary/80 transition-all">
                <x-icon name="o-pencil" class="w-3 h-3"/>
                Sửa bài
            </a>
            <a href="{{ route('admin.post.index') }}"
               class="flex items-center gap-1 px-3 py-1 bg-gray-700 rounded text-xs hover:bg-gray-600 transition-all">
                <x-icon name="o-arrow-left" class="w-3 h-3"/>
                Danh sách
            </a>
        </div>
    </div>

    {{-- Đẩy nội dung xuống tránh bị che --}}
    <div class="h-10"></div>

    {{-- ===== Nội dung bài viết ===== --}}
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <article class="bg-white rounded-lg shadow-lg overflow-hidden">
                    @if($thumbnail)
                        <div class="aspect-video bg-gray-200 overflow-hidden">
                            <img
                                src="{{ Storage::url($thumbnail) }}"
                                alt="{{ $title }}"
                                class="w-full h-full object-cover"
                            />
                        </div>
                    @endif

                    <div class="p-4 lg:p-6">
                        @if($categoryName)
                            <span class="inline-block bg-fita text-white text-sm px-3 py-1 rounded mb-4">
                                {{ $categoryName }}
                            </span>
                        @endif

                        <h1 class="text-3xl lg:text-4xl font-bold mb-4">
                            {{ $title }}
                        </h1>

                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 pb-6 mb-6 border-b">
                            @if($authorName)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user" class="w-4 h-4" />
                                    <span>{{ $authorName }}</span>
                                </div>
                            @endif

                            @if($publishedAt)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-calendar" class="w-4 h-4" />
                                    <span>{{ $publishedAt }}</span>
                                </div>
                            @endif

                            @if(!$isDraft)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-eye" class="w-4 h-4" />
                                    <span>{{ number_format($views) }} {{ __('views') }}</span>
                                </div>
                            @endif
                        </div>

                        @if($excerpt)
                            <div class="bg-gray-50 border-l-4 border-fita p-4 mb-6 italic text-gray-700">
                                {{ $excerpt }}
                            </div>
                        @endif

                        <div class="max-w-none">
                            {!! $content !!}
                        </div>
                    </div>
                </article>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                    <h3 class="font-bold text-lg mb-3">{{ __('Preview info') }}</h3>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p>
                            <span class="font-semibold">{{ __('Status') }}:</span>
                            {{ $status }}
                        </p>
                        @if($isDraft)
                            <p class="text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-1">
                                {{ __('This is a draft preview from cache.') }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4">
                    <a
                        href="{{ route('admin.post.index') }}"
                        class="btn btn-block bg-fita hover:bg-fita2 text-white border-0"
                    >
                        <x-icon name="o-arrow-left" class="w-4 h-4" />
                        {{ __('Back to Posts') }}
                    </a>
                    <a
                        href="{{ route('admin.post.edit', $id) }}"
                        class="btn btn-block btn-outline border-fita text-fita hover:bg-fita hover:text-white mt-2"
                    >
                        <x-icon name="o-pencil-square" class="w-4 h-4" />
                        {{ __('Edit Post') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

