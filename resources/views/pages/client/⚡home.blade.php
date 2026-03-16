<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {

    public $tabSelected='tab-outstanding-post';
   public  $slides = [
       [
           'image' => '/assets/images/coming-soon.png',
           'title' => '9 Tháng 2',
           'description' => 'Chương trình đào tạo của Khoa Công nghệ thông tin',
           'url' => 'https://fita.vnua.edu.vn/',
           'urlText' => 'Xem thêm',
           'lazy' => true,
           'position' => 'bottom center',
       ],
       [
           'image' => '/assets/images/empty.png',
           'title' => 'Full stack developers',
           'description' => 'Where burnout is just a fancy term for Tuesday.',
           'position' => 'center center',
       ],
       [
           'image' => '/assets/images/empty-calendar.png',
           'url' => '/docs/installation',
           'urlText' => 'Let`s go!',
           'position' => 'center center',

       ],
       [
           'image' => '/assets/images/logoST.jpg',
           'url' => '/docs/installation',
           'title' => 'Full stack developers',
           'position' => 'top left',
       ],
   ];
    public  $slidePosts = [
        [
            'image' => '/assets/images/img1.jpg',
            'day' => '9',
            'month' => 'Tháng 2',
        ],
        [
            'image' => '/assets/images/img2.jpg',
            'day' => '13',
            'month' => 'Tháng 3',
        ],
    ];


   public $images = [
       '/assets/images/coming-soon.png',
       '/assets/images/empty-calendar.png',
       '/assets/images/logoST.jpg',
       '/assets/images/coming-soon.png',
       '/assets/images/empty-calendar.png',
       '/assets/images/logoST.jpg',
       '/assets/images/coming-soon.png',
       '/assets/images/empty-calendar.png',
       '/assets/images/logoST.jpg',
   ];
};
?>

<div class="">
    {{--  start - title  --}}
    <x-slot:title>
        {{ __('Home page') }}
    </x-slot:title>
    {{--  end - title  --}}
    <x-carousel :slides="$slides"  interval="5000" class="custom-carousel h-125 w-full">
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
            <!-- Description -->
            <h5 class="w-[60%] text-[16px] lg:text-[22px] font-bold text-white">{{ data_get($slide, 'description') }}</h5>

            <!-- Title -->
            <h1 class="w-[60%] text-2xl lg:text-[64px]/[68px] font-bold text-white">{{ data_get($slide, 'title') }}</h1>

            <!-- Button-->
            @if(data_get($slide, 'urlText'))
                <x-button link="{{ data_get($slide, 'url') }}" icon-right="o-arrow-right" class="btn btn-lg max-w-40 bg-fita text-white border-transparent shadow-none hover:bg-fita2 my-3 hover:scale-105">{{ data_get($slide, 'urlText') }}</x-button>
            @endif
        </div>
        @endscope
    </x-carousel>

    <div>
        <h1 class="uppercase lg:text-[36px] text-[32px] text-fita font-medium font-barlow flex justify-center gap-1 items-center mt-10 lg:mt-15 mb-4">
            Tin tức và sự kiện
        </h1>
        <div class="flex h-140 w-[90%] lg:w-330 mx-auto gap-10">
            <div class="w-[50%] hidden lg:block">
                <x-carousel :slides="$slidePosts " autoplay withoutArrows="false" interval="7000" without-indicators class="custom-carousel h-140 rounded-none">
                    @scope('content', $slide)
                    <div>
                        <div
                            @class([
                                "absolute inset-0 z-[1] flex flex-col justify-start items-end text-center",
                            ])
                        >
                            <div class="bg-slate-900/45">
                                <h3 class="font-bold text-white flex flex-col justify-center items-center py-2 px-3">
                                    <span class="text-[40px]"> {{ data_get($slide, 'day') }}</span>
                                    <span class="text-[24px]">{{ data_get($slide, 'month') }}</span>
                                </h3>
                            </div>
                        </div>
                    </div>
                    @endscope
                </x-carousel>
            </div>
            <div class="w-full lg:w-[50%]">
                <x-tabs
                    wire:model="tabSelected"
                    active-class="text-fita! border-b-4 border-fita font-semibold"
                    label-class="font-semibold text-[20px] text-gray-700 px-4 pb-1 whitespace-nowrap font-barlow"
                    label-div-class="border-b-[length:var(--border)] border-b-base-content/10 flex overflow-x-auto"
                >
                    <x-tab name="tab-outstanding-post" label="Tin nổi bật" icon="">
                        <div class="flex flex-col gap-8">
                            <div class="flex gap-5">
                                <div class="h-25 w-33">
                                    <img src="{{asset('assets/images/img1.jpg')}}" class="w-full h-full object-fill" alt="">
                                </div>
                                <div class="flex-1 font-barlow">
                                    <a href="###" class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2">[Kết nối doanh nghiệp] Sinh viên K70 Khoa CNTT tham quan trải nghiệm thực tế tại Công ty TNHH điện Stanley</a>
                                    <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">(FITA) – Sáng ngày 12/12/2025, Khoa Công nghệ thông tin đã tổ chức thành công chuyến tham quan trải nghiệm thực tế (Company Tour) dành cho sinh viên Khóa 70 tại Công ty TNHH Điện Stanley Việt Nam (Gia Lâm, Hà Nội).</p>
                                    <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">Ngày 15/01/2025</p>
                                </div>
                            </div>
                            <div class="flex gap-5">
                                <div class="h-25 w-33">
                                    <img src="{{asset('assets/images/img1.jpg')}}" class="w-full h-full object-fill" alt="">
                                </div>
                                <div class="flex-1 font-barlow">
                                    <a href="###" class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2">[Kết nối doanh nghiệp] Sinh viên K70 Khoa CNTT tham quan trải nghiệm thực tế tại Công ty TNHH điện Stanley</a>
                                    <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">(FITA) – Sáng ngày 12/12/2025, Khoa Công nghệ thông tin đã tổ chức thành công chuyến tham quan trải nghiệm thực tế (Company Tour) dành cho sinh viên Khóa 70 tại Công ty TNHH Điện Stanley Việt Nam (Gia Lâm, Hà Nội).</p>
                                    <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">Ngày 15/01/2025</p>
                                </div>
                            </div>
                            <div class="flex gap-5">
                                <div class="h-25 w-33">
                                    <img src="{{asset('assets/images/img1.jpg')}}" class="w-full h-full object-fill" alt="">
                                </div>
                                <div class="flex-1 font-barlow">
                                    <a href="###" class="text-[18px]/[20px] lg:text-[20px]/[22px] font-semibold text-fita line-clamp-3 lg:line-clamp-2">[Kết nối doanh nghiệp] Sinh viên K70 Khoa CNTT tham quan trải nghiệm thực tế tại Công ty TNHH điện Stanley</a>
                                    <p class="mt-2 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal line-clamp-2">(FITA) – Sáng ngày 12/12/2025, Khoa Công nghệ thông tin đã tổ chức thành công chuyến tham quan trải nghiệm thực tế (Company Tour) dành cho sinh viên Khóa 70 tại Công ty TNHH Điện Stanley Việt Nam (Gia Lâm, Hà Nội).</p>
                                    <p class="mt-3 text-[16px]/[18px] lg:text-[18px]/[20px] font-normal text-gray-500">Ngày 15/01/2025</p>
                                </div>
                            </div>
                        </div>
                    </x-tab>
                    <x-tab name="tab-new-post" label="Tin mới">
                        <div class="flex flex-col gap-8">
                            <div class="flex gap-5">
                                <div class="h-25 w-33">
                                    <img src="{{asset('assets/images/img2.jpg')}}" class="w-full h-full object-fill" alt="">
                                </div>
                                <div class="flex-1 font-barlow">
                                    <a href="###" class="text-[20px]/[22px] font-semibold text-fita line-clamp-2">[Kết nối doanh nghiệp] Sinh viên K70 Khoa CNTT tham quan trải nghiệm thực tế tại Công ty TNHH điện Stanley</a>
                                    <p class="mt-2 text-[18px]/[20px] font-normal line-clamp-2">(FITA) – Sáng ngày 12/12/2025, Khoa Công nghệ thông tin đã tổ chức thành công chuyến tham quan trải nghiệm thực tế (Company Tour) dành cho sinh viên Khóa 70 tại Công ty TNHH Điện Stanley Việt Nam (Gia Lâm, Hà Nội).</p>
                                    <p class="mt-3 text-[18px]/[20px] font-normal text-gray-500">Ngày 15/01/2025</p>
                                </div>
                            </div>
                            <div class="flex gap-5">
                                <div class="h-25 w-33">
                                    <img src="{{asset('assets/images/img2.jpg')}}" class="w-full h-full object-fill" alt="">
                                </div>
                                <div class="flex-1 font-barlow">
                                    <a href="###" class="text-[20px]/[22px] font-semibold text-fita line-clamp-2">[Kết nối doanh nghiệp] Sinh viên K70 Khoa CNTT tham quan trải nghiệm thực tế tại Công ty TNHH điện Stanley</a>
                                    <p class="mt-2 text-[18px]/[20px] font-normal line-clamp-2">(FITA) – Sáng ngày 12/12/2025, Khoa Công nghệ thông tin đã tổ chức thành công chuyến tham quan trải nghiệm thực tế (Company Tour) dành cho sinh viên Khóa 70 tại Công ty TNHH Điện Stanley Việt Nam (Gia Lâm, Hà Nội).</p>
                                    <p class="mt-3 text-[18px]/[20px] font-normal text-gray-500">Ngày 15/01/2025</p>
                                </div>
                            </div>
                            <div class="flex gap-5">
                                <div class="h-25 w-33">
                                    <img src="{{asset('assets/images/img2.jpg')}}" class="w-full h-full object-fill" alt="">
                                </div>
                                <div class="flex-1 font-barlow">
                                    <a href="###" class="text-[20px]/[22px] font-semibold text-fita line-clamp-2">[Kết nối doanh nghiệp] Sinh viên K70 Khoa CNTT tham quan trải nghiệm thực tế tại Công ty TNHH điện Stanley</a>
                                    <p class="mt-2 text-[18px]/[20px] font-normal line-clamp-2">(FITA) – Sáng ngày 12/12/2025, Khoa Công nghệ thông tin đã tổ chức thành công chuyến tham quan trải nghiệm thực tế (Company Tour) dành cho sinh viên Khóa 70 tại Công ty TNHH Điện Stanley Việt Nam (Gia Lâm, Hà Nội).</p>
                                    <p class="mt-3 text-[18px]/[20px] font-normal text-gray-500">Ngày 15/01/2025</p>
                                </div>
                            </div>
                        </div>
                    </x-tab>
                </x-tabs>
                <x-button label="Xem thêm" icon-right="o-arrow-right" class="bg-fita text-white font-semibold text-[16px] w-full py-5! hover:opacity-90 hover:scale-105"> </x-button>
            </div>
        </div>
    </div>

    <div>
        <h1 class="mt-15 uppercase lg:text-[36px] text-[32px] text-fita font-medium font-barlow flex justify-center gap-1 items-center lg:mt-15 mb-4"><svg fill="#0071BD" width="38px" height="38px" viewBox="0 -32 576 576" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M480 416v16c0 26.51-21.49 48-48 48H48c-26.51 0-48-21.49-48-48V176c0-26.51 21.49-48 48-48h16v48H54a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6v-10h48zm42-336H150a6 6 0 0 0-6 6v244a6 6 0 0 0 6 6h372a6 6 0 0 0 6-6V86a6 6 0 0 0-6-6zm6-48c26.51 0 48 21.49 48 48v256c0 26.51-21.49 48-48 48H144c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h384zM264 144c0 22.091-17.909 40-40 40s-40-17.909-40-40 17.909-40 40-40 40 17.909 40 40zm-72 96l39.515-39.515c4.686-4.686 12.284-4.686 16.971 0L288 240l103.515-103.515c4.686-4.686 12.284-4.686 16.971 0L480 208v80H192v-48z"></path></g></svg> Thư viện ảnh</h1>
        <livewire:client.image-gallery :images="$images" class="h-40 rounded-box" />
    </div>
    <div>
        <h1 class="uppercase lg:text-[36px] text-[32px] text-fita font-medium font-barlow flex justify-center gap-1 items-center mt-10 lg:mt-15 mb-4">
            Danh sách đối tác
        </h1>
        <livewire:client.list-of-partners/>

    </div>
</div>
