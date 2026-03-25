<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

new
#[Layout('layouts.client')]
class extends Component {

    public $slides = [
        [
            'image' => '/assets/images/banner-1.jpg',
            'position' => 'bottom center',
        ],
        [
            'image' => '/assets/images/banner-2.jpg',
            'position' => 'center center',
        ],
        [
            'image' => '/assets/images/banner-3.jpg',
            'position' => 'bottom right',
        ],
    ];

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

    public function with(): array
    {
        $locale = app()->getLocale();

        $baseQuery = Post::query()
            ->with(['category', 'user'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');

        $featuredPosts = (clone $baseQuery)
            ->where('is_featured', true)
            ->limit($locale === 'en' ? 12 : 3)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(3)
            ->values();

        $latestPosts = (clone $baseQuery)
            ->when($featuredPosts->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $featuredPosts->pluck('id')))
            ->limit($locale === 'en' ? 20 : 6)
            ->get()
            ->filter(fn (Post $post) => $this->isVisibleInLocale($post, $locale))
            ->take(6)
            ->values();

        return [
            'featuredPosts' => $featuredPosts,
            'latestPosts' => $latestPosts,
        ];
    }
};
?>

<div class="bg-gray-50">
    {{-- ===== HERO BANNER ===== --}}
    <x-carousel :slides="$slides" interval="5000" class="custom-carousel h-screen w-full">
        @scope('content', $slide)
            <div class="absolute inset-0 z-[1] flex flex-col justify-center items-center gap-6 px-4 bg-gradient-to-b from-slate-900/50 to-slate-900/30">
                <div class="text-center space-y-4 max-w-2xl">
                    <h1 class="text-4xl lg:text-6xl font-bold text-white leading-tight">
                        {{ __('School of Information Technology') }}
                    </h1>
                    <p class="text-lg lg:text-xl text-gray-200">
                        {{ __('Vietnam National University of Agriculture') }}
                    </p>
                </div>
                <a href="{{ route('client.posts.index') }}" class="btn btn-lg bg-fita hover:bg-fita2 text-white border-0 mt-4">
                    {{ __('Discover More') }}
                    <x-icon name="o-arrow-right" class="w-5 h-5" />
                </a>
            </div>
        @endscope
    </x-carousel>

    {{-- ===== STATISTICS SECTION ===== --}}
    <section class="py-16 lg:py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center p-6 rounded-lg bg-gradient-to-br from-fita/10 to-transparent">
                    <div class="text-4xl lg:text-5xl font-bold text-fita mb-2">2000+</div>
                    <p class="text-gray-600 font-semibold">{{ __('Students') }}</p>
                </div>
                <div class="text-center p-6 rounded-lg bg-gradient-to-br from-blue-500/10 to-transparent">
                    <div class="text-4xl lg:text-5xl font-bold text-blue-600 mb-2">50+</div>
                    <p class="text-gray-600 font-semibold">{{ __('Faculty Members') }}</p>
                </div>
                <div class="text-center p-6 rounded-lg bg-gradient-to-br from-green-500/10 to-transparent">
                    <div class="text-4xl lg:text-5xl font-bold text-green-600 mb-2">30+</div>
                    <p class="text-gray-600 font-semibold">{{ __('Programs') }}</p>
                </div>
                <div class="text-center p-6 rounded-lg bg-gradient-to-br from-purple-500/10 to-transparent">
                    <div class="text-4xl lg:text-5xl font-bold text-purple-600 mb-2">100%</div>
                    <p class="text-gray-600 font-semibold">{{ __('Employment Rate') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== ABOUT SECTION ===== --}}
    <section class="py-16 lg:py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <h2 class="text-3xl lg:text-4xl font-bold text-gray-900">
                        {{ __('About School of Information Technology') }}
                    </h2>
                    <p class="text-gray-600 leading-relaxed text-lg">
                        {{ __('The School of Information Technology is a leading institution in Vietnam, committed to providing high-quality education and training in computer science, software engineering, and information systems.') }}
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <x-icon name="o-check-circle" class="w-6 h-6 text-fita flex-shrink-0 mt-0.5" />
                            <span class="text-gray-700">{{ __('Modern facilities and laboratories') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-icon name="o-check-circle" class="w-6 h-6 text-fita flex-shrink-0 mt-0.5" />
                            <span class="text-gray-700">{{ __('Experienced faculty and industry experts') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-icon name="o-check-circle" class="w-6 h-6 text-fita flex-shrink-0 mt-0.5" />
                            <span class="text-gray-700">{{ __('Strong industry connections') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <x-icon name="o-check-circle" class="w-6 h-6 text-fita flex-shrink-0 mt-0.5" />
                            <span class="text-gray-700">{{ __('International partnerships') }}</span>
                        </li>
                    </ul>
                    <a href="{{ route('client.information') }}" class="btn bg-fita hover:bg-fita2 text-white border-0 w-fit">
                        {{ __('Learn More') }}
                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                    </a>
                </div>
                <div class="rounded-lg overflow-hidden shadow-lg">
                    <img src="{{ asset('assets/images/logoST.jpg') }}" alt="About" class="w-full h-96 object-cover">
                </div>
            </div>
        </div>
    </section>

    {{-- ===== FEATURED NEWS SECTION ===== --}}
    @if($featuredPosts->isNotEmpty())
        <section class="py-16 lg:py-20 bg-white">
            <div class="container mx-auto px-4">
                <div class="text-center mb-12">
                    <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">
                        ⭐ {{ __('Featured News') }}
                    </h2>
                    <p class="text-gray-600 text-lg">{{ __('Latest highlights and updates from the school') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach($featuredPosts as $post)
                        <a href="{{ route('client.posts.show', $post->slug) }}" wire:navigate class="group">
                            <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden h-full flex flex-col">
                                {{-- Image --}}
                                <div class="relative h-48 bg-gray-200 overflow-hidden">
                                    @if($post->thumbnail)
                                        <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-fita to-fita2">
                                            <x-icon name="o-photo" class="w-16 h-16 text-white opacity-40" />
                                        </div>
                                    @endif
                                    <div class="absolute top-3 right-3 bg-fita text-white px-3 py-1 rounded-full text-sm font-semibold">
                                        {{ __('Featured') }}
                                    </div>
                                </div>

                                {{-- Content --}}
                                <div class="p-5 flex-1 flex flex-col">
                                    <h3 class="text-lg font-bold text-gray-900 line-clamp-2 group-hover:text-fita transition-colors">
                                        {{ $post->getTranslation('title', app()->getLocale()) }}
                                    </h3>

                                    <p class="text-gray-600 text-sm mt-3 line-clamp-3 flex-1">
                                        {{ $post->getExcerptOrAuto(app()->getLocale(), 120) }}
                                    </p>

                                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                                        <span class="text-xs text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD MMM YYYY' : 'DD MMM YYYY') }}
                                        </span>
                                        <span class="text-fita font-semibold text-sm flex items-center gap-1">
                                            {{ __('Read more') }}
                                            <x-icon name="o-arrow-right" class="w-4 h-4" />
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===== LATEST NEWS SECTION ===== --}}
    @if($latestPosts->isNotEmpty())
        <section class="py-16 lg:py-20 bg-gray-50">
            <div class="container mx-auto px-4">
                <div class="text-center mb-12">
                    <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">
                        📰 {{ __('Latest News') }}
                    </h2>
                    <p class="text-gray-600 text-lg">{{ __('Stay updated with the latest events and announcements') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($latestPosts as $post)
                        <a href="{{ route('client.posts.show', $post->slug) }}" wire:navigate class="group">
                            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden h-full flex flex-col">
                                {{-- Image --}}
                                <div class="relative h-40 bg-gray-200 overflow-hidden">
                                    @if($post->thumbnail)
                                        <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->getTranslation('title', app()->getLocale()) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center bg-gray-300">
                                            <x-icon name="o-photo" class="w-12 h-12 text-white opacity-40" />
                                        </div>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="p-4 flex-1 flex flex-col">
                                    @if($post->category)
                                        <span class="text-xs text-fita font-semibold mb-2">
                                            {{ $post->category->getTranslation('name', app()->getLocale()) }}
                                        </span>
                                    @endif

                                    <h3 class="text-base font-bold text-gray-900 line-clamp-2 group-hover:text-fita transition-colors">
                                        {{ $post->getTranslation('title', app()->getLocale()) }}
                                    </h3>

                                    <p class="text-gray-600 text-sm mt-2 line-clamp-2 flex-1">
                                        {{ $post->getExcerptOrAuto(app()->getLocale(), 100) }}
                                    </p>

                                    <div class="mt-3 pt-3 border-t border-gray-200 flex items-center justify-between">
                                        <span class="text-xs text-gray-500">
                                            {{ $post->published_at?->isoFormat(app()->getLocale() === 'vi' ? 'DD MMM' : 'DD MMM') }}
                                        </span>
                                        @if($post->user)
                                            <span class="text-xs text-gray-600">{{ $post->user->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="text-center mt-10">
                    <a href="{{ route('client.posts.index') }}" class="btn btn-lg bg-fita hover:bg-fita2 text-white border-0">
                        {{ __('View All News') }}
                        <x-icon name="o-arrow-right" class="w-5 h-5" />
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ===== PROGRAMS SECTION ===== --}}
    <section class="py-16 lg:py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">
                    🎓 {{ __('Our Programs') }}
                </h2>
                <p class="text-gray-600 text-lg">{{ __('Comprehensive education pathways for your future') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Program 1 --}}
                <div class="p-8 rounded-lg bg-gradient-to-br from-blue-50 to-white border border-blue-200 hover:shadow-lg transition-shadow">
                    <div class="w-16 h-16 bg-blue-600 rounded-lg flex items-center justify-center mb-4">
                        <x-icon name="o-code-bracket" class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('Software Engineering') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('Design, develop, and deploy world-class software solutions.') }}</p>
                    <a href="{{ route('client.posts.index') }}" class="text-fita font-semibold hover:text-fita2 transition-colors flex items-center gap-2">
                        {{ __('Learn more') }}
                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                    </a>
                </div>

                {{-- Program 2 --}}
                <div class="p-8 rounded-lg bg-gradient-to-br from-green-50 to-white border border-green-200 hover:shadow-lg transition-shadow">
                    <div class="w-16 h-16 bg-green-600 rounded-lg flex items-center justify-center mb-4">
                        <x-icon name="o-server-stack" class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('Information Systems') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('Build and manage integrated business information systems.') }}</p>
                    <a href="{{ route('client.posts.index') }}" class="text-fita font-semibold hover:text-fita2 transition-colors flex items-center gap-2">
                        {{ __('Learn more') }}
                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                    </a>
                </div>

                {{-- Program 3 --}}
                <div class="p-8 rounded-lg bg-gradient-to-br from-purple-50 to-white border border-purple-200 hover:shadow-lg transition-shadow">
                    <div class="w-16 h-16 bg-purple-600 rounded-lg flex items-center justify-center mb-4">
                        <x-icon name="o-rocket-launch" class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ __('Information Technology') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('Master cutting-edge technologies and digital innovation.') }}</p>
                    <a href="{{ route('client.posts.index') }}" class="text-fita font-semibold hover:text-fita2 transition-colors flex items-center gap-2">
                        {{ __('Learn more') }}
                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== CTA SECTION ===== --}}
    <section class="py-16 lg:py-20 bg-gradient-to-r from-fita to-fita2">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-4">
                {{ __('Join Our Community') }}
            </h2>
            <p class="text-lg text-gray-100 mb-8 max-w-2xl mx-auto">
                {{ __('Become part of a leading institution committed to excellence in technology education and innovation.') }}
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('client.information') }}" class="btn btn-lg bg-white text-fita hover:bg-gray-100 border-0">
                    {{ __('Explore Programs') }}
                    <x-icon name="o-arrow-right" class="w-5 h-5" />
                </a>
                <a href="{{ route('client.posts.index') }}" class="btn btn-lg btn-outline text-white border-white hover:bg-white/10">
                    {{ __('News & Events') }}
                    <x-icon name="o-newspaper" class="w-5 h-5" />
                </a>
            </div>
        </div>
    </section>

    {{-- ===== PARTNERS SECTION ===== --}}
    <section class="py-16 lg:py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl lg:text-4xl font-bold text-gray-900 mb-3">
                    🤝 {{ __('Our Partners') }}
                </h2>
                <p class="text-gray-600 text-lg">{{ __('Collaborating with leading organizations') }}</p>
            </div>
            <livewire:client.list-of-partners />
        </div>
    </section>

    {{-- ===== CONTACT SECTION ===== --}}
    <section class="py-16 lg:py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-fita/10 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <x-icon name="o-map-pin" class="w-8 h-8 text-fita" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Location') }}</h3>
                    <p class="text-gray-600">Trầu Quỳ, Gia Lâm, Hà Nội</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-fita/10 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <x-icon name="o-envelope" class="w-8 h-8 text-fita" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Email') }}</h3>
                    <p class="text-gray-600">cntt@vnua.edu.vn</p>
                </div>
                <div class="text-center p-6">
                    <div class="w-16 h-16 bg-fita/10 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <x-icon name="o-phone" class="w-8 h-8 text-fita" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">{{ __('Phone') }}</h3>
                    <p class="text-gray-600">(+84) 24 3868 3234</p>
                </div>
            </div>
        </div>
    </section>
</div>

