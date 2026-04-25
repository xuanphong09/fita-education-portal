<?php

use App\Models\Album;
use App\Models\AlbumImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.client')]
class extends Component {
    use WithPagination;

    // Chỉ hiển thị cố định số lượng ảnh trên trang (hoặc bạn có thể cho user chọn)
    public int $imagePerPage = 25;

    // ID của album đang được chọn để lọc (null = xem tất cả)
    public ?int $selectedAlbumId = null;

    public function getAlbumOptionsProperty(): array
    {
        return Album::query()
            ->orderBy('order')
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getAllImagesProperty()
    {
        return AlbumImage::query()
            ->when($this->selectedAlbumId, function ($query) {
                // Chỉ lấy ảnh thuộc Album được chọn
                $query->whereHas('albums', function ($q) {
                    $q->where('albums.id', $this->selectedAlbumId);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->imagePerPage);
    }

    // Hàm đổi bộ lọc Album
    public function setAlbum(?int $albumId): void
    {
        $this->selectedAlbumId = $albumId;
        $this->resetPage(); // Reset về trang 1 khi đổi bộ lọc
    }
};
?>
<div class="container mx-auto px-4 py-8">
    <x-slot:title>
        {{__('Photo library')}}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span class="whitespace-nowrap font-semibold text-slate-700">{{__('Photo library')}}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{__('Photo library')}}
    </x-slot:titleBreadcrumb>

    {{-- BỘ LỌC ALBUM (Dạng Tabs/Pills) --}}
    @if(!empty($this->albumOptions))
        <div class="flex flex-wrap gap-2 mb-8">
            <x-button
                label="Tất cả"
                wire:click="setAlbum(null)"
                class="btn-sm {{ is_null($selectedAlbumId) ? 'btn-primary text-white' : 'btn-ghost border border-gray-300' }}"
                spinner="setAlbum"
            />

            @foreach($this->albumOptions as $album)
                <x-button
                    label="{{ $album['name'] }}"
                    wire:click="setAlbum({{ $album['id'] }})"
                    class="btn-sm {{ $selectedAlbumId === $album['id'] ? 'btn-primary text-white' : 'btn-ghost border border-gray-300' }}"
                    spinner="setAlbum({{ $album['id'] }})"
                />
            @endforeach
        </div>
    @endif

    {{-- LƯỚI HÌNH ẢNH --}}
    <div
        id="public-gallery"
        class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 2xl:grid-cols-6 gap-4 lg:gap-6"
        x-data="{
            lightbox: null,
            init() {
                if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') return;

                this.lightbox = new PhotoSwipeLightbox({
                    gallery: '#public-gallery',
                    children: 'a.pswp-item',
                    showHideAnimationType: 'zoom', // Đổi sang zoom cho mượt mắt người dùng
                    pswpModule: PhotoSwipe,
                    // Tùy chọn: bật nút download mặc định của PhotoSwipe
                    zoom: true,
                    arrowKeys: true,
                });

                this.lightbox.init();
            }
        }"
    >
        @forelse($this->allImages as $image)
            <div class="relative overflow-hidden rounded-xl bg-gray-100 shadow-sm aspect-video sm:aspect-square" wire:key="gallery-image-{{ $image->id }}">
                <a
                    href="{{ Storage::url($image->image_path) }}"
                    data-pswp-width="1200"
                    data-pswp-height="800"
                    class="pswp-item block w-full h-full cursor-zoom-in group/img relative"
                >
                    <img
                        src="{{ Storage::url($image->image_path) }}"
                        class="w-full h-full object-cover transition-transform duration-700 group-hover/img:scale-110"
                        onload="this.parentNode.setAttribute('data-pswp-width', this.naturalWidth); this.parentNode.setAttribute('data-pswp-height', this.naturalHeight)"
                        loading="lazy"
                        alt="{{ $image->caption ?: 'Hình ảnh Khoa CNTT' }}"
                    />

                    {{-- Overlay mờ và Icon Zoom khi Hover --}}
                    <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/30 transition-colors duration-300 flex items-center justify-center">
                        <x-icon name="o-magnifying-glass-plus" class="w-8 h-8 text-white opacity-0 group-hover/img:opacity-100 transition-opacity duration-300" />
                    </div>
                </a>
            </div>
        @empty
            <div class="col-span-full py-16 flex flex-col items-center justify-center text-gray-400 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                <x-icon name="o-photo" class="w-16 h-16 mb-3 text-gray-300" />
                <p>Hiện tại chưa có hình ảnh nào trong thư mục này.</p>
            </div>
        @endforelse
    </div>

    {{-- PHÂN TRANG --}}
    @if($this->allImages->hasPages())
        <div class="mt-8 flex justify-center">
            {{ $this->allImages->links() }}
        </div>
    @endif
</div>
