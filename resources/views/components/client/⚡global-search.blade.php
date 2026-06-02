<?php

use App\Models\User;
use App\Models\Post;
use App\Services\PostSearchService;
use Livewire\Component;
use Illuminate\Support\Str;

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
        $isEn = app()->getLocale() === 'en';
        $search = $this->keyword();
        $terms = PostSearchService::parseTerms($search);

        $postQuery = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        PostSearchService::applyLocaleFilter($postQuery, $isEn);
        PostSearchService::applyTerms($postQuery, $terms, $isEn);

        $this->results['posts'] = $this->applyPostRelevanceOrder($postQuery)
            ->take(5)
            ->get();

        $this->results['users'] = User::search($search)
            ->query(fn ($query) => $query
                ->with('lecturer')
                ->where('user_type', 'lecturer')
                ->whereHas('lecturer', function ($q) {
                    $q->whereNotNull('slug');
                })
            )
            ->take(30)
            ->get()
            ->sortByDesc(fn ($user) => $this->userSearchScore($user, $search))
            ->take(5)
            ->values();
    }

    public function searchAction()
    {
        $keyword = $this->keyword();

        if ($keyword !== '') {
            return $this->redirect(
                route('client.search', ['q' => $keyword]),
                navigate: true
            );
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->results = ['posts' => [], 'users' => []];
    }

    private function keyword(): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->search) ?? '');
    }

    private function normalizeText(?string $text): string
    {
        return Str::lower(trim(Str::ascii(strip_tags((string) $text))));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Sắp xếp bài viết ngay trong SQL để dropdown nhanh hơn.
     *
     * Không dùng get()->sortByDesc() cho bài viết nữa.
     */
    private function applyPostRelevanceOrder($query)
    {
        $keyword = $this->keyword();

        if ($keyword === '') {
            return $query
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id');
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'vi';

        $keywordLower = Str::lower($keyword);
        $keywordAscii = Str::lower(Str::ascii($keyword));
        $keywordSlug = Str::slug($keyword);

        $exactKeyword = '%' . $this->escapeLike($keywordLower) . '%';
        $asciiKeyword = '%' . $this->escapeLike($keywordAscii) . '%';
        $slugKeyword = '%' . $this->escapeLike($keywordSlug) . '%';

        $titleExpr = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(title, '$.{$locale}')), ''))";
        $excerptExpr = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(excerpt, '$.{$locale}')), ''))";
        $contentExpr = "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(content, '$.{$locale}')), ''))";

        $titleSearchColumn = $locale === 'en' ? 'title_en_search' : 'title_vi_search';
        $excerptSearchColumn = $locale === 'en' ? 'excerpt_en_search' : 'excerpt_vi_search';
        $contentSearchColumn = $locale === 'en' ? 'content_en_search' : 'content_vi_search';
        $slugSearchColumn = $locale === 'en' ? 'slug_en_search' : 'slug_vi_search';

        if (PostSearchService::hasSearchColumns()) {
            return $query
                ->orderByRaw("
                    (
                        CASE
                            WHEN {$titleExpr} COLLATE utf8mb4_bin LIKE ? ESCAPE '\\\\' THEN 1000
                            WHEN {$excerptExpr} COLLATE utf8mb4_bin LIKE ? ESCAPE '\\\\' THEN 500
                            WHEN {$contentExpr} COLLATE utf8mb4_bin LIKE ? ESCAPE '\\\\' THEN 250

                            WHEN {$titleSearchColumn} LIKE ? ESCAPE '\\\\' THEN 200
                            WHEN {$excerptSearchColumn} LIKE ? ESCAPE '\\\\' THEN 120
                            WHEN {$contentSearchColumn} LIKE ? ESCAPE '\\\\' THEN 80

                            WHEN {$slugSearchColumn} LIKE ? ESCAPE '\\\\' THEN 70
                            WHEN slug LIKE ? ESCAPE '\\\\' THEN 60

                            ELSE 0
                        END
                    ) DESC
                ", [
                    $exactKeyword,
                    $exactKeyword,
                    $exactKeyword,

                    $asciiKeyword,
                    $asciiKeyword,
                    $asciiKeyword,

                    $slugKeyword,
                    $slugKeyword,
                ])
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id');
        }

        return $query
            ->orderByRaw("
                (
                    CASE
                        WHEN {$titleExpr} COLLATE utf8mb4_bin LIKE ? ESCAPE '\\\\' THEN 1000
                        WHEN {$excerptExpr} COLLATE utf8mb4_bin LIKE ? ESCAPE '\\\\' THEN 500
                        WHEN {$contentExpr} COLLATE utf8mb4_bin LIKE ? ESCAPE '\\\\' THEN 250
                        WHEN slug LIKE ? ESCAPE '\\\\' THEN 60
                        ELSE 0
                    END
                ) DESC
            ", [
                $exactKeyword,
                $exactKeyword,
                $exactKeyword,
                $slugKeyword,
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private function userSearchScore(User $user, string $keyword): int
    {
        $keyword = trim($keyword);
        $normalizedKeyword = $this->normalizeText($keyword);

        $name = (string) ($user->name ?? '');
        $email = (string) ($user->email ?? '');
        $position = (string) ($user->lecturer?->positionForLocale(app()->getLocale()) ?? '');

        $score = 0;

        if ($keyword !== '' && mb_stripos($name, $keyword) !== false) {
            $score += 1000;
        }

        if ($keyword !== '' && mb_stripos($position, $keyword) !== false) {
            $score += 500;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($name), $normalizedKeyword)) {
            $score += 300;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($position), $normalizedKeyword)) {
            $score += 150;
        }

        if ($normalizedKeyword !== '' && str_contains($this->normalizeText($email), $normalizedKeyword)) {
            $score += 50;
        }

        return $score;
    }
};
?>
{{--1--}}
<div class="relative z-50" x-data="{ open: false }" @click.outside="open = false">
    <div class="group relative">
        <button type="button" @click="open = !open" class="btn-ghost bg-transparent border-transparent shadow-none btn-sm">
            <x-icon name="o-magnifying-glass" class="w-6 h-6 font-bold @if($this->mode==='light') text-black @else text-white @endif"/>
        </button>

        <div :class="{ 'visible! opacity-100! translate-y-0!': open }" class="
            fixed left-0 right-0 mx-auto top-8 w-[95vw] z-50
            lg:absolute lg:-right-10 lg:top-full lg:mx-0 lg:left-auto lg:w-80 lg:mt-2
            bg-white shadow-2xl border border-gray-100 p-2 rounded-none
            invisible opacity-0 translate-y-2 transition-all duration-300 ease-out
            group-hover:visible group-hover:opacity-100 group-hover:translate-y-0
            focus-within:visible focus-within:opacity-100 focus-within:translate-y-0 text-black
        ">

            <form wire:submit.prevent="searchAction" class="relative">
                <x-input
                    placeholder="{{ __('Enter search keywords...') }}"
                    class="focus:outline-none focus:border-fita"
                    wire:model.live.debounce.300ms="search"
                    @focus="open = true"
                    @input="open = true"
                >
                    <x-slot:append>
                        <x-button link="{{ route('client.search', ['q' => $search]) }}" icon="o-magnifying-glass" class="join-item btn-primary bg-fita"/>
                    </x-slot:append>
                </x-input>
            </form>

            @if(mb_strlen(trim($search)) >= 2)
                <div x-show="open" class="mt-2 border-t border-gray-100 pt-2">

                    <div wire:loading class="p-3 text-center text-xs text-gray-500 w-full">
                        <span class="loading loading-spinner loading-xs"></span> Đang tìm...
                    </div>

                    <div wire:loading.remove>
                        @if(count($results['users']) === 0 && count($results['posts']) === 0)
                            <div class="p-3 text-center text-xs text-gray-500">
                                {{__('No results found')}}.
                            </div>
                        @else
                            <div class="max-h-64 overflow-y-auto custom-scrollbar">

                                {{-- Tin tức --}}
                                @if(count($results['posts']) > 0)
                                    <div class="px-2 py-1 text-[10px] font-bold text-gray-400 uppercase">
                                        Tin tức
                                    </div>

                                    @foreach($results['posts'] as $post)
                                        <a href="{{ $post->client_url }}"
                                           class="block px-2 py-2 hover:bg-blue-50 rounded-lg transition"
                                           wire:navigate>
                                            <div class="text-sm font-medium text-gray-700 truncate flex items-center">
                                                @if($post->is_featured)
                                                    <span class="inline-flex items-center rounded text-red-500 px-1.5 py-0.5 text-[10px] font-semibold">
                                                        <x-icon name="s-star"></x-icon>
                                                    </span>
                                                @endif

                                                <span>
                                                    {{ $post->getTranslation('title', app()->getLocale()) }}
                                                </span>
                                            </div>
                                        </a>
                                    @endforeach
                                @endif

                                {{-- Giảng viên --}}
                                @if(count($results['users']) > 0)
                                    <div class="px-2 py-1 mt-2 text-[10px] font-bold text-gray-400 uppercase">
                                        Giảng viên
                                    </div>

                                    @foreach($results['users'] as $user)
                                        @continue(!$user->lecturer?->slug)

                                        <a href="{{ route('client.lecturers.profile', ['slug' => $user->lecturer->slug]) }}"
                                           class="flex items-center gap-2 px-2 py-2 hover:bg-blue-50 rounded-lg transition"
                                           wire:navigate>
                                            <div class="avatar placeholder">
                                                <div class="bg-blue-100 text-blue-600 rounded-full w-6 h-6 text-[10px] flex justify-center items-center font-bold">
                                                    {{ mb_substr($user->name ?? 'G', 0, 1) }}
                                                </div>
                                            </div>

                                            <div class="text-sm text-gray-700 truncate">
                                                {{ $user->name }}
                                            </div>
                                        </a>
                                    @endforeach
                                @endif
                            </div>

                            <a href="{{ route('client.search', ['q' => $search]) }}"
                               class="block mt-2 text-center py-2 text-xs font-bold text-[#005aab] bg-blue-50 rounded-lg hover:bg-blue-100 transition"
                               wire:navigate>
                                {{ __('View all') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
