<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class ContentImageService
{
    /**
     * Download external images in HTML content and replace their src with local URLs.
     *
     * @param string $content
     * @param string $disk
     * @param string $folder
     * @return string
     */
    public function downloadAndReplaceExternalImages(string $content, string $disk = 'public', string $folder = 'uploads/posts/editor')
    {
        // Match all <img src="..."> tags
        $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
        $matches = [];
        preg_match_all($pattern, $content, $matches);
        $client = new Client(['verify' => false, 'timeout' => 10]);

        foreach ($matches[1] as $imgUrl) {
            // Only process external images (http/https and not from our domain)
            if (Str::startsWith($imgUrl, ['http://', 'https://']) && !Str::contains($imgUrl, config('app.url'))) {
                try {
                    $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $filename = $folder . '/' . uniqid('img_') . '.' . $ext;
                    $response = $client->get($imgUrl);
                    if ($response->getStatusCode() === 200) {
                        Storage::disk($disk)->put($filename, $response->getBody()->getContents());
                        $localUrl = Storage::disk($disk)->url($filename);
                        // Replace all occurrences of this URL in content
                        $content = str_replace($imgUrl, $localUrl, $content);
                    }
                } catch (\Exception $e) {
                    // Ignore failed downloads, keep original src
                    continue;
                }
            }
        }
        return $content;
    }
}

