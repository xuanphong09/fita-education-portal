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
    <div class="w-[90%] lg:w-[900px] mx-auto py-8">

        {{-- Tiêu đề --}}
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 leading-tight mb-4">
            {{ $title }}
        </h1>

        {{-- Meta --}}
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 mb-6 pb-4 border-b border-gray-200">
            @if($categoryName)
                <span class="bg-fita/10 text-fita px-2 py-0.5 rounded text-xs font-medium">
                    {{ $categoryName }}
                </span>
            @endif
            @if($authorName)
                <span class="flex items-center gap-1">
                    <x-icon name="o-user" class="w-4 h-4"/>
                    {{ $authorName }}
                </span>
            @endif
            @if($publishedAt)
                <span class="flex items-center gap-1">
                    <x-icon name="o-calendar" class="w-4 h-4"/>
                    {{ $publishedAt }}
                </span>
            @endif
            @if(!$isDraft)
                <span class="flex items-center gap-1">
                    <x-icon name="o-eye" class="w-4 h-4"/>
                    {{ number_format($views) }} lượt xem
                </span>
            @endif
        </div>

        {{-- Thumbnail --}}
        @if($thumbnail)
            <div class="mb-6">
                <img src="{{ Storage::url($thumbnail) }}"
                     alt="{{ $title }}"
                     class="w-full max-h-[480px] object-cover rounded-lg shadow-sm"/>
            </div>
        @endif

        {{-- Excerpt --}}
        @if($excerpt)
            <div class="bg-gray-50 border-l-4 border-fita rounded-r-lg p-4 mb-6 text-gray-700 italic text-base">
                {{ $excerpt }}
            </div>
        @endif

        {{-- Nội dung chính --}}
        <div class="prose prose-lg max-w-none
                    prose-headings:text-gray-900 prose-headings:font-bold
                    prose-p:text-gray-700 prose-p:leading-relaxed
                    prose-a:text-fita prose-a:no-underline hover:prose-a:underline
                    prose-img:rounded-lg prose-img:shadow-sm prose-img:max-w-full
                    prose-table:border prose-table:border-gray-200
                    prose-th:bg-gray-100 prose-th:p-2
                    prose-td:p-2 prose-td:border prose-td:border-gray-200
                    prose-code:bg-gray-100 prose-code:px-1 prose-code:rounded
                    prose-blockquote:border-fita prose-blockquote:bg-gray-50 prose-blockquote:rounded-r">
            {!! $content !!}
        </div>

        {{-- Danh mục --}}
        @if($categoryName)
            <div class="mt-8 pt-6 border-t border-gray-200">
                <span class="text-sm text-gray-500">Danh mục:</span>
                <span class="ml-2 bg-fita/10 text-fita px-3 py-1 rounded-full text-sm font-medium">
                    {{ $categoryName }}
                </span>
            </div>
        @endif

    </div>
</div>

