<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Post;
use App\Models\Category;
use App\Models\User;
use App\Models\PostDefaultImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Carbon\Carbon;

new
#[Layout('layouts.client')]
class extends Component {
    public ?int $id = null; // Bổ sung biến ID để nhận biết đang sửa hay tạo mới
    public string $locale = 'vi';
    public bool $hasCache = false;

    public Post $post;
    public Collection $relatedPosts;
    public Collection $recentPosts;

    // Tự động điều chỉnh chìa khóa Cache dựa vào việc có ID hay không
    private function cacheKey(): string
    {
        return $this->id ? 'post_preview_' . $this->id . '_' . auth()->id() : 'post_preview_new_' . auth()->id();
    }

    // Bổ sung tham số $id = null để bắt ID từ URL (route)
    public function mount($id = null): void
    {
        $this->id = $id;
        $this->locale = app()->getLocale();
        $this->relatedPosts = collect();
        $this->recentPosts = collect();
        $this->loadData();
    }

    private function loadData(): void
    {
        if (!Cache::has($this->cacheKey())) {
            $this->hasCache = false;
            return;
        }

        $this->hasCache = true;
        $data = Cache::get($this->cacheKey());

        // 1. TẠO MODEL GIẢ TỪ CACHE
        $this->post = new Post();
        $this->post->id = $this->id ?: 9999999; // Dùng ID thật nếu có, ngược lại dùng ảo
        $this->post->slug = $data['slug'] ?? 'preview-slug';
        $this->post->status = $data['status'] ?? 'draft';
        $this->post->is_featured = $data['is_featured'] ?? false;
        $this->post->published_at = !empty($data['published_at']) ? Carbon::parse($data['published_at']) : now();
        $this->post->views = 0;
        $this->post->thumbnail = $data['thumbnail'] ?? null;

        $this->post->preview_thumbnail_url = $data['thumbnail_url'] ?? null;

        // Các cờ (flags) ẩn hiện metadata
        $this->post->post_default_image_id = $data['post_default_image_id'] ?? null;
        $this->post->show_author = $data['show_author'] ?? true;
        $this->post->show_published_at = $data['show_published_at'] ?? true;
        $this->post->show_views = $data['show_views'] ?? true;
        $this->post->show_category = $data['show_category'] ?? true;
        $this->post->show_related_posts = $data['show_related_posts'] ?? true;

        // Đổ ngôn ngữ
        $this->post->setTranslation('title', 'vi', $data['title']['vi'] ?? '');
        $this->post->setTranslation('title', 'en', $data['title']['en'] ?? '');
        $this->post->setTranslation('content', 'vi', $data['content']['vi'] ?? '');
        $this->post->setTranslation('content', 'en', $data['content']['en'] ?? '');
        $this->post->setTranslation('excerpt', 'vi', $data['excerpt']['vi'] ?? '');
        $this->post->setTranslation('excerpt', 'en', $data['excerpt']['en'] ?? '');

        // 2. GÁN QUAN HỆ (RELATIONS) GIẢ
        if (!empty($data['user_id'])) {
            $this->post->setRelation('user', User::find($data['user_id']));
        }

        $categories = collect();
        if (!empty($data['category_ids'])) {
            $categories = Category::whereIn('id', $data['category_ids'])->get();
            $this->post->setRelation('categories', $categories);
        }

        if ($this->post->post_default_image_id) {
            $this->post->setRelation('defaultImage', PostDefaultImage::find($this->post->post_default_image_id));
        }

        // 3. LẤY BÀI VIẾT LIÊN QUAN & MỚI NHẤT (Lấy từ DB để test Sidebar)
        if ($this->post->show_related_posts && $categories->isNotEmpty()) {
            $this->relatedPosts = Post::where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categories->pluck('id')))
                ->latest('published_at')
                ->take(3)
                ->get();
        }

        $this->recentPosts = Post::where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->take(5)
            ->get();
    }

    public function switchLocale(string $locale): void
    {
        $this->locale = $locale;
        app()->setLocale($locale);
        $this->loadData();
    }

    // Hàm check bài mới để dùng trong sidebar
    public function isNewPost(Post $post): bool
    {
        if (!$post->published_at) return false;

        $publishedAt = $post->published_at instanceof \Illuminate\Support\Carbon
            ? $post->published_at
            : \Illuminate\Support\Carbon::parse($post->published_at);

        $now = now();
        $threshold = $now->copy()->subDays(3);

        return $publishedAt->greaterThanOrEqualTo($threshold) && $publishedAt->lessThanOrEqualTo($now);
    }
};
?>

<div>
    {{-- Màn hình báo lỗi khi hết Cache --}}
    @if(!$hasCache)
        <div class="flex flex-col items-center justify-center py-32 text-gray-400 min-h-screen bg-gray-50">
            <x-icon name="o-exclamation-triangle" class="w-12 h-12 mb-3 text-warning"/>
            <p class="text-lg font-medium text-gray-700">Không có dữ liệu xem trước</p>
            <p class="text-sm mt-1">Dữ liệu Cache đã hết hạn hoặc bạn chưa bấm "Xem trước" từ trang soạn thảo.</p>
            <button onclick="window.close()" class="mt-4 px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary/80 transition">
                Đóng trang xem trước
            </button>
        </div>
    @else
        <x-slot:title>Xem trước: {{ $post->getTranslation('title', $locale) ?: 'Chưa có tiêu đề' }}</x-slot:title>

        {{-- ===== THANH CÔNG CỤ PREVIEW (FIXED TOP) ===== --}}
        <div class="fixed top-0 left-0 right-0 z-[9999] bg-gray-900 text-white text-sm flex items-center justify-between px-4 py-2 shadow-lg print:hidden">
            <div class="flex items-center gap-3">
                <x-icon name="o-eye" class="w-4 h-4 text-yellow-400"/>
                <span class="font-medium text-yellow-400">Chế độ xem trước</span>
                <span class="text-gray-400 hidden lg:block">—</span>
                <span class="text-gray-300 truncate max-w-xs hidden lg:block">{{ $post->getTranslation('title', $locale) ?: 'Đang chờ tiêu đề...' }}</span>
            </div>

            <div class="flex items-center gap-2">
                {{-- Nút chuyển ngôn ngữ --}}
                <button wire:click="switchLocale('vi')" class="px-2 py-0.5 rounded text-xs {{ $locale === 'vi' ? 'bg-primary text-white' : 'bg-gray-700 hover:bg-gray-600' }}">
                    🇻🇳 VI
                </button>
                <button wire:click="switchLocale('en')" class="px-2 py-0.5 rounded text-xs {{ $locale === 'en' ? 'bg-primary text-white' : 'bg-gray-700 hover:bg-gray-600' }}">
                    🇺🇸 EN
                </button>

                <span class="text-gray-600">|</span>

                {{-- Trạng thái --}}
                @php
                    $statusMap = [
                        'draft'          => ['label' => 'Nháp',      'class' => 'text-yellow-400'],
                        'pending_review' => ['label' => 'Chờ duyệt', 'class' => 'text-orange-400'],
                        'published'      => ['label' => 'Đã đăng',   'class' => 'text-green-400'],
                        'archived'       => ['label' => 'Lưu trữ',   'class' => 'text-gray-400'],
                    ];
                    $s = $statusMap[$post->status] ?? $statusMap['draft'];
                @endphp
                <span class="text-xs {{ $s['class'] }}">● {{ $s['label'] }}</span>

                <span class="text-gray-600">|</span>

                <button onclick="window.close()" class="flex items-center gap-1 px-3 py-1 bg-primary rounded text-xs hover:bg-primary/80 transition-all">
                    <x-icon name="o-x-mark" class="w-3 h-3"/>
                    Đóng tab
                </button>
            </div>
        </div>

        {{-- Bù khoảng trống cho thanh top bar --}}
        <div class="h-10"></div>

        <div class="container mx-auto px-4 py-8">
            {{-- Breadcrumb giống hệt trang thật --}}
            <x-slot:breadcrumb>
                @if($post->relationLoaded('categories') && $post->categories->isNotEmpty())
                    <a href="#" class="whitespace-nowrap font-semibold text-slate-700 pointer-events-none">{{$post->categories->first()->getTranslation('name', $locale)}}</a>
                @else
                    <a href="#" class="whitespace-nowrap font-semibold text-slate-700 pointer-events-none">{{__('Post list')}}</a>
                @endif
                <span><x-icon name="s-chevron-right" class="w-4 h-4" /></span>
                <span class="line-clamp-1 max-w-200">{{ $post->getTranslation('title', $locale) ?: 'Đang chờ tiêu đề...' }}</span>
            </x-slot:breadcrumb>

            <x-slot:titleBreadcrumb>
                <span class="uppercase">
                    @if($post->relationLoaded('categories') && $post->categories->isNotEmpty())
                        {{$post->categories->first()->getTranslation('name', $locale)}}
                    @else
                        {{__('Posts')}}
                    @endif
                </span>
            </x-slot:titleBreadcrumb>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Main Content --}}
                <div class="lg:col-span-2">
                    <article class="bg-white rounded-lg shadow-lg overflow-hidden">

                        <div class="aspect-auto bg-gray-200 overflow-hidden relative min-h-40">
                            @if(!empty($post->preview_thumbnail_url))
                                <img src="{{ $post->preview_thumbnail_url }}" class="w-full h-full object-cover object-top" alt="Thumbnail">
                            @elseif($post->thumbnail)
                                <img src="{{ Storage::url($post->thumbnail) }}" class="w-full h-full object-cover object-top" alt="Thumbnail">
                            @elseif($post->post_default_image_id && $post->relationLoaded('defaultImage') && $post->defaultImage)
                                <img src="{{ Storage::url($post->defaultImage->image_path) }}" class="w-full h-full object-cover object-top" alt="Template">
                                @if($post->defaultImage->show_title)
                                    <div class="absolute inset-0 flex items-center justify-center p-20 lg:p-30" style="container-type: inline-size; transform: translateY(calc( {{$post->defaultImage->text_y_offset}} / 1200 * 100cqw))">
                                        <p class="line-clamp-4 font-bold select-none"
                                           :style="{
                                                color: '{{ $post->defaultImage->text_color ?? '#ffffff' }}',
                                                fontSize: 'clamp(8px, calc({{ $post->defaultImage->text_size ?? 18 }} / 450 * 100cqw), 60px)',
                                                lineHeight: 1.1,
                                                textAlign: '{{$post->defaultImage->text_alignment ?? 'center'}}',
                                                padding: '5px'
                                            }"
                                        >{{ $post->getTranslation('title', $locale) }}</p>
                                    </div>
                                @endif
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-gray-500 font-medium">Chưa có ảnh đại diện</div>
                            @endif

                            @if($post->is_featured)
                                <div class="absolute top-0 left-0 z-10 flex items-center gap-1 bg-red-500 pe-2 ps-1 py-0.5 text-[16px] font-bold text-white shadow-md rounded-br-xl">
                                    {{ __('Featured News') }}
                                </div>
                            @endif
                        </div>

                        <div class="p-4 lg:p-6">
                            {{-- Category Badge --}}
                            @if($post->show_category && $post->relationLoaded('categories') && $post->categories->isNotEmpty())
                                <div class="flex flex-wrap gap-2 mb-4">
                                    @foreach($post->categories as $postCategory)
                                        @if($postCategory->getTranslation('name', $locale, false))
                                            <span class="inline-block bg-fita text-white text-sm px-3 py-1 rounded">
                                                {{ $postCategory->getTranslation('name', $locale) }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- Title --}}
                            <h1 class="text-3xl lg:text-4xl font-bold mb-4">
                                {{ $post->getTranslation('title', $locale) ?: '— Đang chờ nhập tiêu đề —' }}
                            </h1>

                            {{-- Meta Info --}}
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 pb-6 mb-6 border-b">
                                @if($post->show_author && $post->relationLoaded('user') && $post->user)
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-user" class="w-4 h-4" />
                                        <span>{{ $post->user->name }}</span>
                                    </div>
                                @endif
                                @if($post->show_published_at && $post->published_at)
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-calendar" class="w-4 h-4" />
                                        <span>{{ $post->published_at->isoFormat($locale === 'vi' ? 'DD [tháng] MM YYYY' : 'DD MMMM YYYY') }}</span>
                                    </div>
                                @endif
                                @if($post->show_views)
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-eye" class="w-4 h-4" />
                                        <span>{{ number_format($post->views) }} {{ __('views') }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Excerpt --}}
                            @if($post->getTranslation('excerpt', $locale, false))
                                <div class="bg-gray-50 border-l-4 border-fita p-4 mb-6 italic text-gray-700">
                                    {{ $post->getTranslation('excerpt', $locale) }}
                                </div>
                            @endif

                            {{-- Content --}}
                            <div class="tinymce-content max-w-none min-h-32">
                                {!! $post->getTranslation('content', $locale) ?: '<p class="text-gray-400 italic">Nội dung bài viết sẽ hiển thị ở đây...</p>' !!}
                            </div>
                        </div>
                    </article>

                    {{-- Related Posts (Bị làm mờ) --}}
                    @if($post->show_related_posts && $relatedPosts->isNotEmpty())
                        <div class="mt-8 opacity-60">
                            <h2 class="text-2xl font-bold mb-4">{{ __('Related Posts') }}</h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                @foreach($relatedPosts as $related)
                                    <div class="bg-white rounded-lg shadow-md overflow-hidden pointer-events-none">
                                        <div class="aspect-video bg-gray-200 overflow-hidden relative">
                                            @if($related->thumbnail)
                                                <img src="{{ Storage::url($related->thumbnail) }}" class="w-full h-full object-cover object-top">
                                            @elseif($related->post_default_image_id && $related->defaultImage)
                                                <img src="{{ Storage::url($related->defaultImage->image_path) }}" class="w-full h-full object-cover object-top">
                                                @if($related->defaultImage->show_title)
                                                    <div class="absolute inset-0 flex items-center justify-center p-10" style="container-type: inline-size;">
                                                        <p class="line-clamp-4 font-bold"
                                                           :style="{
                                                                color: '{{ $related->defaultImage->text_color ?? '#ffffff' }}',
                                                                fontSize: 'clamp(8px, calc({{ $related->defaultImage->text_size ?? 18 }} / 1200 * 100cqw), 60px)',
                                                                lineHeight: 1.1,
                                                                textAlign: '{{$related->defaultImage->text_alignment ?? 'center'}}'
                                                            }"
                                                        >{{ $related->getTranslation('title', $locale) }}</p>
                                                    </div>
                                                @endif
                                            @else
                                                <img src="{{ asset('assets/images/post-6.jpg') }}" class="w-full h-full object-cover object-top">
                                            @endif
                                        </div>
                                        <div class="py-3 px-2 lg:h-24">
                                            <h3 class="font-bold text-md line-clamp-2">{{ $related->getTranslation('title', $locale) }}</h3>
                                            <p class="text-sm text-gray-500 mt-2">{{ $related->published_at->format('d/m/Y') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Sidebar (Bị làm mờ) --}}
                <div class="lg:col-span-1 opacity-60">
                    @if($recentPosts->isNotEmpty())
                        <div class="bg-white rounded-lg shadow-md p-4 mb-6 pointer-events-none">
                            <h3 class="font-bold text-xl mb-4">{{ __('Recent Posts') }}</h3>
                            <div class="space-y-4">
                                @foreach($recentPosts as $recent)
                                    <div class="flex gap-3">
                                        <div class="w-28 h-18 shrink-0 bg-gray-200 rounded overflow-hidden relative">
                                            @if($recent->thumbnail)
                                                <img src="{{ Storage::url($recent->thumbnail) }}" class="w-full h-full object-cover object-top">
                                            @elseif($recent->post_default_image_id && $recent->defaultImage)
                                                <img src="{{ Storage::url($recent->defaultImage->image_path) }}" class="w-full h-full object-cover object-top">
                                            @else
                                                <img src="{{ asset('assets/images/post-6.jpg') }}" class="w-full h-full object-cover object-top">
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-md line-clamp-2">{{ $recent->getTranslation('title', $locale) }}</h4>
                                            <p class="text-sm text-gray-500 mt-1">{{ $recent->published_at->format('d/m/Y') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
