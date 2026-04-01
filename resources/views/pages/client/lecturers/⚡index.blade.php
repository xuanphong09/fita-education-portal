<?php

use App\Models\Department;
use App\Models\Lecturer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.client')]
class extends Component {
    use WithPagination;

    #[Url(as: 'tim-kiem')]
    public string $search = '';

    #[Url(as: 'bo-mon')]
    public ?string $department = null;

    public int $perPage = 10;

    private function normalizeText(?string $text): string
    {
        return Str::lower(trim(Str::ascii((string) $text)));
    }

    private function localizeAcademicTitle(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.academic_title.$key");

        return $translated !== "lecturer.academic_title.$key"
            ? $translated
            : (string) $value;
    }

    private function localizeDegree(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $key = Str::lower(trim((string) $value));
        $translated = trans("lecturer.degree.$key");

        return $translated !== "lecturer.degree.$key"
            ? $translated
            : (string) $value;
    }

    private function positionTextsForSearch(Lecturer $lecturer): array
    {
        $positions = $lecturer->positions;

        if (is_array($positions)) {
            return array_values(array_filter([
                $positions['vi'] ?? null,
                $positions['en'] ?? null,
            ]));
        }

        if (is_string($positions) && trim($positions) !== '') {
            return [$positions];
        }

        return [];
    }

    private function resolveDepartmentIdFromQuery(?string $value, $departments): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        // Some select modes may send object-like payload (JSON string)
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['id']) && is_numeric($decoded['id'])) {
            return (int) $decoded['id'];
        }

        // Preferred: slug from URL (vi-du-bo-mon-cong-nghe-phan-mem)
        $fromSlug = $departments->first(function ($d) use ($raw) {
            return Str::slug((string) $d->name) === $raw;
        });
        if ($fromSlug) {
            return (int) $fromSlug->id;
        }

        // Fallback: exact name from URL
        $fromName = $departments->first(fn ($d) => (string) $d->name === $raw);
        if ($fromName) {
            return (int) $fromName->id;
        }

        return null;
    }

    public function with(): array
    {
        $keyword = trim($this->search);
        $locale = app()->getLocale() === 'en' ? 'en' : 'vi';
        $positionExpr = "LOWER(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(positions, '$.{$locale}')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(positions, '$.vi')), ''), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(positions, '$.en')), ''), ''))";

        $departments = Department::query()
            ->get(['id', 'name']);

        $departmentId = $this->resolveDepartmentIdFromQuery($this->department, $departments);

        $query = Lecturer::query()
            ->with(['user', 'department'])
            ->whereHas('user', fn ($query) => $query
                ->where('user_type', 'lecturer')
                ->where('is_active', true)
                ->whereNotNull('name')
                ->whereRaw("TRIM(name) <> ''")
            )
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->orderByRaw("CASE
                WHEN {$positionExpr} LIKE '%pho truong khoa%' OR {$positionExpr} LIKE '%phó trưởng khoa%' OR {$positionExpr} LIKE '%vice dean%' OR {$positionExpr} LIKE '%deputy dean%' OR {$positionExpr} LIKE '%associate dean%' THEN 2
                WHEN {$positionExpr} LIKE '%truong khoa%' OR {$positionExpr} LIKE '%trưởng khoa%' OR {$positionExpr} LIKE '%dean%' THEN 1
                WHEN {$positionExpr} LIKE '%truong bo mon%' OR {$positionExpr} LIKE '%trưởng bộ môn%' OR {$positionExpr} LIKE '%head of department%' THEN 3
                WHEN {$positionExpr} LIKE '%pho truong bo mon%' OR {$positionExpr} LIKE '%phó trưởng bộ môn%' OR {$positionExpr} LIKE '%deputy head%' OR {$positionExpr} LIKE '%vice head%' THEN 4
                WHEN {$positionExpr} LIKE '%giang vien%' OR {$positionExpr} LIKE '%giảng viên%' OR {$positionExpr} LIKE '%lecturer%' THEN 5
                 ELSE 9
             END ASC")
            ->orderByRaw("{$positionExpr} ASC");

        $items = $query->get()->filter(function (Lecturer $lecturer) {
            $name = trim((string) ($lecturer->user?->name ?? ''));

            return $name !== '';
        })->values();

        // Tìm kiếm theo cả có dấu và không dấu (vd: "toan" khớp "Toán")
        if ($keyword !== '') {
            $normalizedKeyword = $this->normalizeText($keyword);

            $items = $items->filter(function (Lecturer $lecturer) use ($keyword, $normalizedKeyword) {
                $positionTexts = $this->positionTextsForSearch($lecturer);
                $haystack = implode(' ', [
                    (string) ($lecturer->user?->name ?? ''),
                    (string) ($lecturer->user?->email ?? ''),
                    ...$positionTexts,
                ]);

                $rawMatch = mb_stripos($haystack, $keyword) !== false;
                $normalizedMatch = str_contains($this->normalizeText($haystack), $normalizedKeyword);

                return $rawMatch || $normalizedMatch;
            })->values();
        }

        $page = $this->getPage();
        $total = $items->count();
        $lecturers = new LengthAwarePaginator(
            $items->forPage($page, $this->perPage)->values(),
            $total,
            $this->perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        $departmentOptions = $departments
            ->map(fn ($item) => [
                'id' => Str::slug((string) $item->name),
                'name' => (string) $item->name,
            ])
            ->values()
            ->all();

        return [
            'lecturers' => $lecturers,
            'departments' => $departmentOptions,
        ];
    }

    public function resetFilters(): void
    {
        $this->redirectRoute('client.lecturers.index', [], navigate: true);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        if ($this->department !== null) {
            $this->department = trim($this->department);
            if ($this->department === '') {
                $this->department = null;
            }
        }

        $this->resetPage();
    }
};
?>

<div class="container mx-auto px-4 py-8">
    <x-slot:title>
        {{__('Lecturers - Staff')}}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span class="whitespace-nowrap font-semibold text-slate-700">{{__('Lecturers - Staff')}}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{__('Lecturers - Staff')}}
    </x-slot:titleBreadcrumb>

    <section class="rounded-xl bg-white border border-gray-200 p-6 lg:p-8 shadow-sm mb-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            <div class="lg:col-span-2">
                <x-input
                    icon="o-magnifying-glass"
                    placeholder="{{__('Search by name, email, position...')}}"
                    wire:model.live.debounce.400ms="search"
                    class="w-full"
                    clearable
                    label="{{__('Search lecturers- staff')}}"
                />
            </div>

            <div>
                <x-select
                    label="{{__('Filter by department')}}"
                    wire:model.live="department"
                    :options="$departments"
                    placeholder="{{__('All departments')}}"
                />
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-6 relative">
        @forelse($lecturers as $lecturer)
            @php
                $profileUrl = route('client.lecturers.profile', ['slug' => $lecturer->slug]);
                $avatar = $lecturer->user?->avatar ? asset($lecturer->user->avatar) : asset('/assets/images/default-user-image.png');
                $academicTitleLabel = $this->localizeAcademicTitle($lecturer->academic_title);
                $degreeLabel = $this->localizeDegree($lecturer->degree);
                $positionLabel = $lecturer->positionForLocale(app()->getLocale());
            @endphp

            <article class="bg-white rounded-b-md border border-gray-200 overflow-hidden shadow-sm hover:shadow-md hover:scale-105 hover:[&_a_h2]:text-fita transition">
                <a href="{{ $profileUrl }}" wire:navigate>
                    <img src="{{ $avatar }}" alt="{{ $lecturer->user?->name }}" class="h-120 lg:h-64 w-full object-cover" />
                </a>

                <div class="py-4 px-2">
                    <a href="{{ $profileUrl }}" wire:navigate class="block text-center">
                        <h2 class="text-md uppercase font-semibold text-gray-900 hover:text-fita transition line-clamp-2">
                            @if($academicTitleLabel)
                                {{ $academicTitleLabel }}
                                @if(app()->getLocale() === 'vi')
                                    ,
                                @endif
                            @endif
                            @if($degreeLabel)
                                {{ $degreeLabel }}
                            @endif
                            {{ $lecturer->user?->name }}
                        </h2>
                        @if($positionLabel)
                            <p class="font-medium text-gray-800">{{ $positionLabel }}</p>
                        @endif
                    </a>

                </div>
            </article>
        @empty
            <div class="md:col-span-3 xl:col-span-5 bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-500">
                {{__('No lecturers found matching the search criteria.')}}
            </div>
        @endforelse

        <div wire:loading.delay.short class="absolute inset-0 z-30 bg-white/65 backdrop-blur-[2px] rounded-xl transition-all duration-300">
            <div class="flex flex-col items-center gap-2 mt-20">
                <x-loading class="text-primary loading-lg" />
                <span class="text-md font-medium text-gray-500">{{__('Loading data...')}}</span>
            </div>
        </div>
    </div>

    <div class="mt-8">
        {{ $lecturers->links() }}
    </div>
</div>
