@component('layouts.client')
    @slot('seo')
        <x-seo
            title="503 - Hệ thống đang bảo trì"
            description="Trang bạn đang truy cập không tồn tại hoặc đã được di chuyển."
            image="assets/images/FITA.png"
            type="website"
        />
    @endslot

    <section class="relative overflow-hidden bg-slate-50">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-emerald-200/50 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-sky-200/50 blur-3xl"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <img
                    src="{{ asset('assets/images/errors/503.svg') }}"
                    alt="503 - Hệ thống đang bảo trì"
                    class="mx-auto w-full max-w-[760px]"
                >
                <div class="flex flex-col items-center justify-center gap-3 sm:flex-row mb-10">
                    <a href="{{ url('/') }}" wire:navigate
                       class="inline-flex items-center justify-center rounded-md bg-fita px-6 py-3 text-md font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-fita2">
                        Về trang chủ
                    </a>

                    <button type="button"
                            onclick="history.back()"  wire:navigate
                            class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-white px-6 py-3 text-md font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-gray-200!">
                        Quay lại trang trước
                    </button>
                </div>
            </div>
        </div>
    </section>
@endcomponent
