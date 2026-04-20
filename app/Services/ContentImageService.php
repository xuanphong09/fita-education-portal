<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class ContentImageService
{
    public function downloadAndReplaceExternalImages(string $content, string $disk = 'public', string $folder = 'uploads/posts/editor')
    {
        $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
        $matches = [];
        preg_match_all($pattern, $content, $matches);

        $client = new Client([
            'verify' => false,
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]
        ]);

        foreach ($matches[1] as $imgUrl) {
            // Kiểm tra link ngoài hoặc link từ hệ thống WordPress cũ
            $isExternal = !Str::contains($imgUrl, config('app.url'));
            $isOldWp = Str::contains($imgUrl, 'wp-content/uploads');

            if (Str::startsWith($imgUrl, ['http://', 'https://']) && ($isExternal || $isOldWp)) {
                try {
                    $cleanImgUrl = trim(html_entity_decode($imgUrl));

                    // 1. Trích xuất tên file gốc từ URL
                    // Ví dụ: https://fita.vnua.edu.vn/.../Logo-CNPM-Final.jpg?v=1 -> Logo-CNPM-Final.jpg
                    $pathOnly = parse_url($cleanImgUrl, PHP_URL_PATH);
                    $originalInfo = pathinfo($pathOnly);

                    $extension = $originalInfo['extension'] ?? 'jpg';
                    $baseName = $originalInfo['filename'] ?? 'image';

                    // 2. Làm sạch tên file (slug) để an toàn cho filesystem
                    $safeName = Str::slug($baseName);
                    $fileName = $safeName . '.' . $extension;
                    $path = $folder . '/' . $fileName;

                    // 3. Chống ghi đè nếu trùng tên (thêm số -2, -3...)
                    $counter = 1;
                    while (Storage::disk($disk)->exists($path)) {
                        $counter++;
                        $fileName = $safeName . '-' . $counter . '.' . $extension;
                        $path = $folder . '/' . $fileName;
                    }

                    // 4. Tải và lưu file
                    $response = $client->get($cleanImgUrl);
                    if ($response->getStatusCode() === 200) {
                        Storage::disk($disk)->put($path, $response->getBody()->getContents());
                        $localUrl = Storage::disk($disk)->url($path);

                        // Thay thế link trong nội dung
                        $content = str_replace($imgUrl, $localUrl, $content);
                    }

                } catch (\Exception $e) {
                    Log::warning("Lỗi tải ảnh gốc: {$imgUrl}. Chi tiết: " . $e->getMessage());
                    continue;
                }
            }
        }

        // Dọn dẹp srcset của WordPress để tránh trình duyệt gọi lại link cũ
        $content = preg_replace('/\s*(srcset|sizes)=["\'][^"\']*["\']/i', '', $content);

        return $content;
    }
}
