<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Course Syllabus') }} - {{  $subject->getTranslation('name', app()->getLocale(), false) ?: $subject->getTranslation('name', 'vi', false) ?: $subject->getTranslation('name', 'en', false) ?: 'N/A' }} - {{$subject->code}} | {{__('Faculty of Information Technology')}}</title>
    <link rel="icon" type="image/png" href="{{asset('assets/images/LogoKhoaCNTT.png')}}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Ẩn nút tăng giảm */
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 select-none" oncontextmenu="return false;">
<div class="mx-auto max-w-6xl p-4 md:p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href=" {{route('client.home')}}" class="flex items-center ms-5 gap-3" wire:navigate>
            <img src="{{asset('assets/images/FITA.png')}}" class="size-13 md:size-16 rounded-[50%] object-cover shadow-md" alt="Logo" />
            <div class="md:flex flex-col ms-2 wow fadeInDown">
                <h1 class="font-bold tracking-wider uppercase text-fita font-barlow md:text-[24px]/[26px]! text-[18px]/[24px]! ">{{__('Faculty of Information Technology')}}</h1>
                <h1 class="font-semibold tracking-wider uppercase md:text-[16px]/[20px] text-[14px]/[16px] text-black font-barlow"
                >{{__('Khoa Công nghệ Thông tin')}}</h1>
            </div>
        </a>
        <div>
            <h1 class="text-lg font-semibold">{{ __('Course Syllabus') }}: {{ $subject->code }} - {{  $subject->getTranslation('name', app()->getLocale(), false) ?: $subject->getTranslation('name', 'vi', false) ?: $subject->getTranslation('name', 'en', false) ?: 'N/A' }}</h1>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        @if($previewType === 'pdf')
            <div class="border-b bg-gray-50 px-4 py-2">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <button id="zoom-out" type="button" class="rounded border border-gray-300 bg-white px-3 py-1 hover:bg-gray-100">-</button>
                    <button id="zoom-reset" type="button" class="rounded border border-gray-300 bg-white px-3 py-1 hover:bg-gray-100">100%</button>
                    <button id="zoom-in" type="button" class="rounded border border-gray-300 bg-white px-3 py-1 hover:bg-gray-100">+</button>

                    <div class="mx-2 h-5 w-px bg-gray-300"></div>
                    <div>
                        <label for="page-input" class="text-gray-600">{{ __('Trang') }}</label>
                        <button id="prev-page" type="button" class="rounded border border-gray-300 bg-white px-1 py-1 hover:bg-gray-100 transition-colors" title="Trang trước"><x-icon class="w-5 h-5" name="o-chevron-up"></x-icon></button>
                        <button id="next-page" type="button" class="rounded border border-gray-300 bg-white px-1 py-1 hover:bg-gray-100 transition-colors" title="Trang sau"><x-icon class="w-5 h-5" name="o-chevron-down"></x-icon></button>
                        <input id="page-input" type="number" min="1" value="1" class="w-10 rounded border border-gray-300 px-2 py-1" />
                        <button id="go-page" type="button" class="rounded border border-gray-300 bg-white px-3 py-1 hover:bg-gray-100">{{ __('Đi đến') }}</button>
                        <span id="page-status" class="ml-1 text-gray-600">1 / 1</span>

                    </div>

                    <button id="retry-load" type="button" class="ml-auto rounded border border-red-300 bg-red-50 px-3 py-1 text-red-700 hover:bg-red-100 hidden">{{ __('Tải lại') }}</button>
                </div>
            </div>

            <div id="pdf-viewer" class="h-[68vh] w-full overflow-y-auto bg-gray-500 relative">
                <div id="pdf-loading" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-500/90 text-white z-10">
                    <span class="loading loading-spinner loading-lg mb-3"></span>
                    <p id="pdf-loading-text" class="text-sm font-medium">{{ __('Đang tải tài liệu, vui lòng đợi...') }}</p>
                </div>
                <div id="pdf-pages" class="mx-auto flex w-full max-w-5xl flex-col items-center py-6 gap-6"></div>
            </div>
        @elseif($previewType === 'office')
            <iframe src="{{ $officeEmbedUrl }}" title="Syllabus Preview" class="h-[68vh] w-full border-0" loading="lazy"></iframe>
            <div class="border-t px-4 py-3 text-sm text-gray-600">
                {{ __('If preview does not load, please contact the administrator.') }}
            </div>
        @else
            <div class="p-6 text-sm text-gray-700">
                <p>{{ __('This file type is not supported for inline preview in browser.') }}</p>
                <p class="mt-2">{{ __('Please contact the administrator for support.') }}</p>
            </div>
        @endif
    </div>
</div>
<livewire:footer-copyright/>

@if($previewType === 'pdf')
    <script type="module">
        import * as pdfjsLib from "{{ asset('assets/js/pdfjs/pdf.js') }}";
        document.addEventListener('DOMContentLoaded', function () {
            pdfjsLib.GlobalWorkerOptions.workerSrc = "{{ asset('assets/js/pdfjs/pdf.worker.js') }}";

            const url = '{{ $downloadUrl }}';
            const viewer = document.getElementById('pdf-viewer');
            const pagesRoot = document.getElementById('pdf-pages');
            const loading = document.getElementById('pdf-loading');
            const loadingText = document.getElementById('pdf-loading-text');

            const zoomOutBtn = document.getElementById('zoom-out');
            const zoomInBtn = document.getElementById('zoom-in');
            const zoomResetBtn = document.getElementById('zoom-reset');
            const pageInput = document.getElementById('page-input');
            const goPageBtn = document.getElementById('go-page');
            const pageStatus = document.getElementById('page-status');
            const retryBtn = document.getElementById('retry-load');
            const prevPageBtn = document.getElementById('prev-page');
            const nextPageBtn = document.getElementById('next-page');

            let pdfDoc = null;
            let scale = 1.2;
            const minScale = 0.7;
            const maxScale = 2.2;
            const pages = [];
            const rendering = new Set();
            const visiblePages = new Set();
            let pageObserver = null;

            const updateZoomLabel = function () {
                const percentage = Math.round(scale * 100);
                zoomResetBtn.textContent = percentage + '%';
            };

            const updatePageStatus = function (current, total) {
                pageStatus.textContent = current + ' / ' + total;
            };

            const setLoading = function (isLoading, message) {
                if (message) {
                    loadingText.textContent = message;
                }

                loading.style.display = isLoading ? 'flex' : 'none';
            };

            const buildPageSkeletons = function (count) {
                pagesRoot.innerHTML = '';
                pages.length = 0;

                for (let pageNum = 1; pageNum <= count; pageNum++) {
                    const holder = document.createElement('div');
                    holder.className = 'w-full flex justify-center';
                    holder.dataset.pageNumber = String(pageNum);

                    const canvas = document.createElement('canvas');
                    canvas.className = 'shadow-lg bg-white max-w-full h-auto';
                    canvas.dataset.pageNumber = String(pageNum);
                    holder.appendChild(canvas);

                    pagesRoot.appendChild(holder);
                    pages.push({ holder: holder, canvas: canvas, renderedScale: null });
                }

                pageInput.max = String(count);
                pageInput.value = '1';
                updatePageStatus(1, count);
            };

            const renderPage = async function (pageNum, force) {
                if (!pdfDoc || !pages[pageNum - 1]) {
                    return;
                }

                const key = pageNum + '@' + scale;
                if (rendering.has(key)) {
                    return;
                }

                const pageMeta = pages[pageNum - 1];
                if (!force && pageMeta.renderedScale === scale) {
                    return;
                }

                rendering.add(key);

                try {
                    const page = await pdfDoc.getPage(pageNum);
                    const viewport = page.getViewport({ scale: scale });
                    const pixelRatio = Math.max(window.devicePixelRatio || 1, 1);

                    pageMeta.canvas.width = Math.floor(viewport.width * pixelRatio);
                    pageMeta.canvas.height = Math.floor(viewport.height * pixelRatio);
                    pageMeta.canvas.style.width = Math.floor(viewport.width) + 'px';
                    pageMeta.canvas.style.height = Math.floor(viewport.height) + 'px';

                    const context = pageMeta.canvas.getContext('2d');
                    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

                    await page.render({
                        canvasContext: context,
                        viewport: viewport
                    }).promise;

                    pageMeta.renderedScale = scale;
                } catch (error) {
                    console.error('Render page failed:', error);
                } finally {
                    rendering.delete(key);
                }
            };

            const renderVisiblePages = function () {
                visiblePages.forEach(function (pageNum) {
                    renderPage(pageNum, false);
                });
            };

            const setupObserver = function () {
                if (pageObserver) {
                    pageObserver.disconnect();
                }

                pageObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        const pageNum = Number(entry.target.dataset.pageNumber || 0);
                        if (!pageNum) {
                            return;
                        }

                        if (entry.isIntersecting) {
                            visiblePages.add(pageNum);
                            renderPage(pageNum, false);
                            updatePageStatus(pageNum, pdfDoc.numPages);
                        } else {
                            visiblePages.delete(pageNum);
                        }
                    });
                }, {
                    root: viewer,
                    rootMargin: '300px 0px',
                    threshold: 0.01
                });

                pages.forEach(function (pageMeta) {
                    pageObserver.observe(pageMeta.holder);
                });
            };

            const reloadAtCurrentScale = function () {
                pages.forEach(function (pageMeta) {
                    pageMeta.renderedScale = null;
                });

                renderVisiblePages();
            };

            const clampScale = function (next) {
                return Math.min(maxScale, Math.max(minScale, next));
            };

            const goToPage = function () {
                if (!pdfDoc) {
                    return;
                }

                const pageNum = Math.min(pdfDoc.numPages, Math.max(1, Number(pageInput.value || 1)));
                pageInput.value = String(pageNum);

                const pageMeta = pages[pageNum - 1];
                if (!pageMeta) {
                    return;
                }

                pageMeta.holder.scrollIntoView({ behavior: 'smooth', block: 'start' });
                renderPage(pageNum, false);
                updatePageStatus(pageNum, pdfDoc.numPages);
            };

            const loadDocument = function () {
                retryBtn.classList.add('hidden');
                setLoading(true, '{{ __('Đang tải tài liệu, vui lòng đợi...') }}');

                pdfjsLib.getDocument({
                    url: url,
                    withCredentials: false,
                    disableAutoFetch: false,
                    disableStream: false
                }).promise.then(function (pdf) {
                    pdfDoc = pdf;
                    buildPageSkeletons(pdfDoc.numPages);
                    setupObserver();
                    updateZoomLabel();
                    setLoading(false);

                    // Render a few first pages immediately for faster first impression.
                    renderPage(1, true);
                    renderPage(2, false);
                    renderPage(3, false);
                }).catch(function (error) {
                    console.error('Lỗi khi tải PDF:', error);
                    setLoading(true, '{{ __('Không thể tải tài liệu. Vui lòng thử lại sau.') }}');
                    retryBtn.classList.remove('hidden');
                });
            };

            zoomOutBtn.addEventListener('click', function () {
                const nextScale = clampScale(scale - 0.1);
                if (nextScale === scale) {
                    return;
                }

                scale = nextScale;
                updateZoomLabel();
                reloadAtCurrentScale();
            });

            zoomInBtn.addEventListener('click', function () {
                const nextScale = clampScale(scale + 0.1);
                if (nextScale === scale) {
                    return;
                }

                scale = nextScale;
                updateZoomLabel();
                reloadAtCurrentScale();
            });

            zoomResetBtn.addEventListener('click', function () {
                if (scale === 1) {
                    return;
                }

                scale = 1;
                updateZoomLabel();
                reloadAtCurrentScale();
            });
            if (prevPageBtn) {
                prevPageBtn.addEventListener('click', () => {
                    let currentPage = parseInt(pageInput.value) || 1;
                    if (currentPage > 1) {
                        pageInput.value = currentPage - 1;
                        goToPage(); // Tái sử dụng hàm cuộn trang đã viết
                    }
                });
            }

            if (nextPageBtn) {
                nextPageBtn.addEventListener('click', () => {
                    if (!pdfDoc) return;
                    let currentPage = parseInt(pageInput.value) || 1;
                    if (currentPage < pdfDoc.numPages) {
                        pageInput.value = currentPage + 1;
                        goToPage();
                    }
                });
            }
            goPageBtn.addEventListener('click', goToPage);
            pageInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    goToPage();
                }
            });

            retryBtn.addEventListener('click', function () {
                loadDocument();
            });

            updateZoomLabel();
            loadDocument();
        });
    </script>
@endif

<script>
    (function () {
        const blockedComboKeys = new Set(['p', 's']);
        const listenerOptions = { capture: true, passive: false };
        const viewer = document.getElementById('pdf-viewer');

        const stopEvent = function (event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
        };

        // Keep deterrence scoped to the document viewer area to avoid harming page UX.
        if (viewer) {
            ['contextmenu', 'dragstart'].forEach(function (eventName) {
                viewer.addEventListener(eventName, stopEvent, listenerOptions);
            });
        }

        document.addEventListener('keydown', function (event) {
            const key = String(event.key || '').toLowerCase();
            const code = String(event.code || '');
            const isCtrlOrMeta = event.ctrlKey || event.metaKey;

            // if (key === 'f12' || key === 'printscreen' || code === 'PrintScreen') {
            //     stopEvent(event);
            //     return;
            // }

            if (isCtrlOrMeta && blockedComboKeys.has(key)) {
                stopEvent(event);
                return;
            }

            if (isCtrlOrMeta && event.shiftKey && (key === 'i' || key === 'j' || key === 'c')) {
                stopEvent(event);
            }
        }, listenerOptions);

        document.addEventListener('keyup', function (event) {
            const key = String(event.key || '').toLowerCase();
            const code = String(event.code || '');

            if (key !== 'printscreen' && code !== 'PrintScreen') {
                return;
            }

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText('').catch(function () {});
            }
        }, listenerOptions);

        window.addEventListener('beforeprint', stopEvent, listenerOptions);
    }());
</script>
</body>
</html>
