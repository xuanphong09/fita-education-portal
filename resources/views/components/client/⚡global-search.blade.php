<?php

use App\Models\User;
use App\Models\Post;
use App\Services\PostSearchService;
use Livewire\Component;

new class extends Component {

    public string $search = '';
    public string $mode = '';
    public array $results = ['posts' => [], 'users' => []];

    public function updatedSearch(): void
    {
        if (mb_strlen(trim($this->search)) < 2) {
            $this->results = ['posts' => [], 'users' => []];
            return;
        }

        $this->performSearch();
    }

    public function performSearch(): void
    {
        $isEn  = app()->getLocale() === 'en';
        $search = trim(preg_replace('/\s+/u', ' ', $this->search) ?? '');
        $terms  = PostSearchService::parseTerms($search);

        $postQuery = Post::query()
            ->with('category')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        // Hide posts without EN content when locale is EN.
        PostSearchService::applyLocaleFilter($postQuery, $isEn);

        // Apply shared search terms (virtual cols → FULLTEXT → JSON fallback).
        PostSearchService::applyTerms($postQuery, $terms, $isEn);

        $this->results['posts'] = $postQuery
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->take(5)
            ->get();

        $this->results['users'] = User::search($search)
            ->take(5)
            ->get();
    }

    public function searchAction()
    {
        if (trim($this->search) !== '') {
            return $this->redirect(route('client.posts.index', ['tim-kiem' => trim($this->search)]), navigate: true);
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->results = ['posts' => [], 'users' => []];
    }
};
?>

<div class="relative z-50" x-data="{ open: false }" @click.outside="open = false">
    <div class="group relative">
        <button class="btn-ghost bg-transparent border-transparent shadow-none btn-sm">
            <x-icon name="o-magnifying-glass" class="w-6 h-6 font-bold @if($this->mode==='light') text-black @else text-white @endif"/>
        </button>
        <div class="
    {{-- 1. CẤU HÌNH CHO MOBILE (Mặc định) --}}
    fixed left-0 right-0 mx-auto top-8 w-[95vw] z-50

    {{-- 2. CẤU HÌNH CHO DESKTOP (Ghi đè lại về như cũ) --}}
    lg:absolute lg:-right-10 lg:top-full lg:mx-0 lg:left-auto lg:w-80 lg:mt-2

    {{-- 3. CÁC THUỘC TÍNH CHUNG (Màu sắc, Animation...) --}}
    bg-white shadow-2xl border border-gray-100 p-2 rounded-none
    invisible opacity-0 translate-y-2 transition-all duration-300 ease-out
    group-hover:visible group-hover:opacity-100 group-hover:translate-y-0
    focus-within:visible focus-within:opacity-100 focus-within:translate-y-0 text-black
">

            {{-- Form nhập liệu --}}
            <form wire:submit.prevent="searchAction" class="relative">
                <x-input placeholder="{{__('Enter search keywords...')}}"
                         class="focus:outline-none focus:border-fita"
                         wire:model.live.debounce.300ms="search"
                         @focus="open = true"
                         @input="open = true"
                >
                    <x-slot:append>
                        <x-button icon="o-magnifying-glass" class="join-item btn-primary bg-fita"/>
                    </x-slot:append>
                </x-input>
            </form>

            {{-- 3. KẾT QUẢ TÌM KIẾM (Nằm ngay bên dưới ô input) --}}
            @if(strlen($search) >= 2)
                <div x-show="open" class="mt-2 border-t border-gray-100 pt-2">

                    <div wire:loading class="p-3 text-center text-xs text-gray-500 w-full">
                        <span class="loading loading-spinner loading-xs"></span> Đang tìm...
                    </div>

                    <div wire:loading.remove>
                        @if(empty($results['users']) && empty($results['posts']))
                            <div class="p-3 text-center text-xs text-gray-500">Không tìm thấy kết quả.</div>
                        @else
                            <div class="max-h-64 overflow-y-auto custom-scrollbar">
                                {{-- Tin tức --}}
                                @if(count($results['posts']) > 0)
                                    <div class="px-2 py-1 text-[10px] font-bold text-gray-400 uppercase">Tin tức</div>
                                    @foreach($results['posts'] as $post)
                                        <a href="{{ route('client.posts.show', $post->slug) }}"
                                           class="block px-2 py-2 hover:bg-blue-50 rounded-lg transition" wire:navigate>
                                            <div class="text-sm font-medium text-gray-700 truncate flex items-center gap-2">
                                                @if($post->is_featured)
                                                    <span class="inline-flex items-center rounded bg-warning/20 text-warning px-1.5 py-0.5 text-[10px] font-semibold">Hot</span>
                                                @endif
                                                <span>{{ $post->getTranslation('title', app()->getLocale()) }}</span>
                                            </div>
                                        </a>
                                    @endforeach
                                @endif

                                {{-- Giảng viên --}}
                                @if(count($results['users']) > 0)
                                    <div class="px-2 py-1 mt-2 text-[10px] font-bold text-gray-400 uppercase">Giảng
                                        viên
                                    </div>
                                    @foreach($results['users'] as $user)
                                        <a href="/giang-vien/{{ $user->id }}"
                                           class="flex items-center gap-2 px-2 py-2 hover:bg-blue-50 rounded-lg transition"
                                           wire:navigate>
                                            <div class="avatar placeholder">
                                                <div
                                                    class="bg-blue-100 text-blue-600 rounded-full w-6 h-6 text-[10px] flex justify-center items-center font-bold">
                                                    {{ substr($user->name, 0, 1) }}
                                                </div>
                                            </div>
                                            <div class="text-sm text-gray-700">{{ $user->name }}</div>
                                        </a>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Nút Xem tất cả --}}
                            <a href="{{ route('client.posts.index', ['tim-kiem' => $search]) }}"
                               class="block mt-2 text-center py-2 text-xs font-bold text-[#005aab] bg-blue-50 rounded-lg hover:bg-blue-100 transition"
                               wire:navigate>
                                {{__('View all')}}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
