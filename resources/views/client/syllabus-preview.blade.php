<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Course Syllabus') }} - {{  $subject->getTranslation('name', app()->getLocale(), false) ?: $subject->getTranslation('name', 'vi', false) ?: $subject->getTranslation('name', 'en', false) ?: 'N/A' }} - {{$subject->code}} | {{__('Faculty of Information Technology')}}</title>
    <link rel="icon" type="image/png" href="{{asset('assets/images/LogoKhoaCNTT.png')}}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="mx-auto max-w-6xl p-4 md:p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ __('Course Syllabus') }}: {{  $subject->getTranslation('name', app()->getLocale(), false) ?: $subject->getTranslation('name', 'vi', false) ?: $subject->getTranslation('name', 'en', false) ?: 'N/A' }}</h1>
                <p class="text-sm text-gray-600">{{ $subject->code }}</p>
            </div>

            <a href="{{ $downloadUrl }}" download="{{ $downloadFilename ?? basename((string) $subject->syllabus_path) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-md bg-fita2 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                {{ __('Download file') }}
            </a>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            @if($previewType === 'pdf')
                <iframe src="{{ $downloadUrl }}" title="Syllabus Preview" class="h-[77vh] w-full rounded-lg" loading="lazy"></iframe>
            @elseif($previewType === 'office')
                <iframe src="{{ $officeEmbedUrl }}" title="Syllabus Preview" class="h-[77vh] w-full rounded-lg" loading="lazy"></iframe>
                <div class="border-t px-4 py-3 text-sm text-gray-600">
                    {{ __('If preview does not load, please use the download button.') }}
                </div>
            @else
                <div class="p-6 text-sm text-gray-700">
                    <p>{{ __('This file type is not supported for inline preview in browser.') }}</p>
                    <p class="mt-2">{{ __('Please use the download button above.') }}</p>
                </div>
            @endif
        </div>
    </div>
    <livewire:footer-copyright/>
</body>
</html>

