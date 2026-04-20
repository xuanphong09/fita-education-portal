<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super Admin bypass — vượt qua mọi permission check
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return route('password.setup', [
                'token' => $token,
                'email' => $user->email,
            ]);
        });

        // Register after all providers so this route overrides Mary package default upload behavior.
        $this->app->booted(function () {
            Route::middleware(['web'])
                ->prefix(config('mary.route_prefix'))
                ->post('/mary/upload', function (Request $request) {
                    if (! Auth::check()) {
                        return response()->json([
                            'message' => 'Ban can dang nhap de tai anh.',
                        ], 401);
                    }

                    $validator = Validator::make($request->all(), [
                        'file' => 'required|file|max:10240',
                        'disk' => 'nullable|string',
                        'folder' => 'nullable|string',
                    ]);

                    if ($validator->fails()) {
                        return response()->json([
                            'message' => 'Upload khong hop le.',
                            'errors' => $validator->errors(),
                        ], 422);
                    }

                    $validated = $validator->validated();

                    $disk = $validated['disk'] ?? 'public';
                    if (! array_key_exists($disk, config('filesystems.disks', []))) {
                        $disk = 'public';
                    }

                    $folderInput = str_replace('\\', '/', (string) ($validated['folder'] ?? 'editor'));
                    $folderParts = array_filter(explode('/', $folderInput), fn ($part) => $part !== '' && $part !== '.' && $part !== '..');
                    $folder = implode('/', $folderParts) ?: 'editor';

                    $file = $request->file('file');
                    if (! $file || ! $file->isValid()) {
                        return response()->json([
                            'message' => 'Khong the doc tep tai len.',
                        ], 422);
                    }

                    $allowedExtensions = [
                        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'heic', 'heif', 'jfif',
                        'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf',
                    ];
                    $allowedMimeTypes = [
                        'application/pdf',
                        'text/plain',
                        'text/csv',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/rtf',
                        'text/rtf',
                    ];

                    $mimeType = strtolower((string) $file->getMimeType());
                    $clientMimeType = strtolower((string) $file->getClientMimeType());
                    $clientExtension = strtolower((string) $file->getClientOriginalExtension());
                    $detectedExtension = strtolower((string) ($file->extension() ?: $clientExtension ?: ''));
                    $isAllowedByMime = str_starts_with($mimeType, 'image/')
                        || str_starts_with($clientMimeType, 'image/')
                        || in_array($mimeType, $allowedMimeTypes, true)
                        || in_array($clientMimeType, $allowedMimeTypes, true);
                    $isAllowedByExtension = in_array($detectedExtension, $allowedExtensions, true)
                        || in_array($clientExtension, $allowedExtensions, true);

                    if (! $isAllowedByMime && ! $isAllowedByExtension) {
                        return response()->json([
                            'message' => 'Chi cho phep tai tep anh hoac tai lieu hop le.',
                            'errors' => ['file' => ['Dinh dang tep khong duoc ho tro.']],
                        ], 422);
                    }

                    $extension = in_array($detectedExtension, $allowedExtensions, true)
                        ? $detectedExtension
                        : (in_array($clientExtension, $allowedExtensions, true) ? $clientExtension : 'txt');

                    $baseName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
                    $baseName = preg_replace('/[\/\\\\]+/', '-', (string) $baseName);
                    $baseName = preg_replace('/\s+/u', ' ', trim((string) $baseName));
                    $baseName = preg_replace('/[^\pL\pN _.-]/u', '', (string) $baseName);
                    $baseName = trim((string) $baseName, '. ');

                    // Clipboard images from TinyMCE are usually named mceclip*, generate a cleaner name.
                    if ($baseName === '' || preg_match('/^mceclip\d*$/i', $baseName)) {
                        $baseName = 'upload-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(4));
                    }

                    $fileName = $baseName . '.' . $extension;
                    $path = $folder . '/' . $fileName;
                    $counter = 1;

                    // Keep original name; only append a number when a duplicate file already exists.
                    while (Storage::disk($disk)->exists($path)) {
                        $counter++;
                        $fileName = $baseName . '-' . $counter . '.' . $extension;
                        $path = $folder . '/' . $fileName;
                    }

                    $storedPath = Storage::disk($disk)->putFileAs($folder, $file, $fileName, ['visibility' => 'public']);

                    $location = $disk === 'public'
                        ? '/storage/' . ltrim((string) $storedPath, '/')
                        : Storage::disk($disk)->url($storedPath);

                    return ['location' => $location];
                })
                ->name('mary.upload');
        });
    }
}
