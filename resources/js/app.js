import './bootstrap';
import Sortable from 'sortablejs';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import PhotoSwipe from 'photoswipe';
import 'photoswipe/style.css';

import Chart from 'chart.js/auto';

window.Sortable = Sortable;
window.PhotoSwipeLightbox = PhotoSwipeLightbox;
window.PhotoSwipe = PhotoSwipe;
window.Chart = Chart;

window.imageLibraryGallery = function () {
    return {
        lightbox: null,
        actionOverlay: null,
        lightboxKey: 'image-library-gallery',

        escapeHtml(value) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            };

            return String(value ?? '').replace(/[&<>"']/g, (char) => map[char] ?? char);
        },

        getActiveImageMeta(pswp) {
            const element = pswp?.currSlide?.data?.element;

            return {
                id: Number(element?.dataset?.imageId || 0) || null,
                src: pswp?.currSlide?.data?.src || element?.href || '',
                caption: element?.dataset?.imageCaption || '',
            };
        },

        createActionOverlay(pswp) {
            this.removeActionOverlay();

            const image = this.getActiveImageMeta(pswp);
            const overlay = document.createElement('div');

            overlay.className = 'pswp-admin-overlay';
            overlay.innerHTML = `
                <div class='pointer-events-auto flex flex-col items-center gap-2'>
                    ${image.caption ? `
                        <div class='max-w-[80vw] rounded-xl bg-black/65 px-4 py-2 text-center text-sm font-medium text-white shadow-2xl backdrop-blur'>
                            ${this.escapeHtml(image.caption)}
                        </div>
                    ` : ''}

                    <div class='flex items-center gap-2 rounded-full bg-black/65 px-3 py-2 text-white shadow-2xl backdrop-blur'>
                        <button type='button' data-action='caption' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Sửa chú thích' aria-label='Sửa chú thích'>
                            ✎
                        </button>

                        <button type='button' data-action='download' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Tải ảnh xuống' aria-label='Tải ảnh xuống'>
                            ⬇
                        </button>

                        <button type='button' data-action='move' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20' title='Thêm vào album' aria-label='Thêm vào album'>
                            ＋
                        </button>

                        <button type='button' data-action='delete' class='inline-flex h-10 w-10 items-center justify-center rounded-full bg-error/80 transition hover:bg-error' title='Xóa ảnh' aria-label='Xóa ảnh'>
                            🗑
                        </button>
                    </div>
                </div>
            `;

            overlay.style.position = 'absolute';
            overlay.style.left = '50%';
            overlay.style.bottom = '24px';
            overlay.style.transform = 'translateX(-50%)';
            overlay.style.zIndex = '60';

            overlay.querySelector('[data-action="download"]')?.addEventListener('click', () => {
                const image = this.getActiveImageMeta(pswp);

                if (!image.src) {
                    return;
                }

                const link = document.createElement('a');
                link.href = image.src;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.download = '';
                document.body.appendChild(link);
                link.click();
                link.remove();
            });

            overlay.querySelector('[data-action="caption"]')?.addEventListener('click', () => {
                const image = this.getActiveImageMeta(pswp);

                if (!image.id) {
                    return;
                }

                this.$wire.openEditCaption(image.id);
                pswp.close();
            });

            overlay.querySelector('[data-action="move"]')?.addEventListener('click', () => {
                const image = this.getActiveImageMeta(pswp);

                if (!image.id) {
                    return;
                }

                this.$wire.openMoveImage(image.id);
                pswp.close();
            });

            overlay.querySelector('[data-action="delete"]')?.addEventListener('click', () => {
                const image = this.getActiveImageMeta(pswp);

                if (!image.id) {
                    return;
                }

                this.$wire.deleteImage(image.id);
                pswp.close();
            });

            pswp.element?.appendChild(overlay);
            this.actionOverlay = overlay;
        },

        removeActionOverlay() {
            this.actionOverlay?.remove();
            this.actionOverlay = null;
        },

        destroyLightbox() {
            this.removeActionOverlay();

            if (this.lightbox) {
                this.lightbox.destroy();
                this.lightbox = null;
            }

            if (window.__adminPhotoSwipeInstances?.[this.lightboxKey]) {
                window.__adminPhotoSwipeInstances[this.lightboxKey].destroy();
                delete window.__adminPhotoSwipeInstances[this.lightboxKey];
            }
        },

        init() {
            this.$nextTick(() => {
                if (!window.PhotoSwipeLightbox || !window.PhotoSwipe) {
                    console.error('PhotoSwipe chưa được load.');
                    return;
                }

                window.__adminPhotoSwipeInstances ??= {};

                if (window.__adminPhotoSwipeInstances[this.lightboxKey]) {
                    window.__adminPhotoSwipeInstances[this.lightboxKey].destroy();
                    delete window.__adminPhotoSwipeInstances[this.lightboxKey];
                }

                this.lightbox = new window.PhotoSwipeLightbox({
                    gallery: '#my-gallery',
                    children: 'a.pswp-item',
                    showHideAnimationType: 'none',
                    pswpModule: window.PhotoSwipe,
                });

                this.lightbox.on('openingAnimationEnd', () => {
                    this.createActionOverlay(this.lightbox.pswp);
                });

                this.lightbox.on('change', () => {
                    this.removeActionOverlay();
                    this.createActionOverlay(this.lightbox.pswp);
                });

                this.lightbox.on('close', () => {
                    this.removeActionOverlay();
                });

                this.lightbox.init();
                window.__adminPhotoSwipeInstances[this.lightboxKey] = this.lightbox;
            });
        },

        destroy() {
            this.destroyLightbox();
        },
    };
};
