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

    /**
     * 2. HÀM MỚI: XỬ LÝ FILE TÀI LIỆU (PDF, DOCX, XLSX...)
     */
    public function downloadDocuments(string $content, string $disk= 'public')
    {
        $folder = 'uploads/posts/documents'; // Lưu file tài liệu vào thư mục riêng cho gọn

        // Regex tìm thẻ <a> có thuộc tính href chứa các đuôi file tài liệu phổ biến
        $pattern = '/<a[^>]+href=["\']([^"\']+\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar))["\'][^>]*>/i';
        $matches = [];
        preg_match_all($pattern, $content, $matches);

        $client = new Client(['verify' => false, 'timeout' => 60, 'headers' => ['User-Agent' => 'Mozilla/5.0 Chrome/120.0.0.0']]);

        // Lặp qua các link tìm được (Lưu ý: Mảng $matches[1] chứa URL đầy đủ)
        foreach (array_unique($matches[1]) as $fileUrl) {
            $isExternal = !Str::contains($fileUrl, config('app.url'));
            $isOldWp = Str::contains($fileUrl, 'wp-content/uploads');

            if (Str::startsWith($fileUrl, ['http://', 'https://']) && ($isExternal || $isOldWp)) {
                try {
                    $cleanFileUrl = trim(html_entity_decode($fileUrl));

                    // Lấy tên file gốc
                    $pathOnly = parse_url($cleanFileUrl, PHP_URL_PATH);
                    $originalInfo = pathinfo($pathOnly);

                    // BẮT BUỘC PHẢI CÓ EXTENSION THÌ MỚI LÀ FILE HỢP LỆ
                    if (!isset($originalInfo['extension'])) continue;

                    $extension = strtolower($originalInfo['extension']);
                    $baseName = $originalInfo['filename'];

                    // Giữ lại tên gốc nhưng làm sạch (slug) để không bị lỗi trên Linux
                    $safeName = Str::slug($baseName);
                    $fileName = $safeName . '.' . $extension;
                    $path = $folder . '/' . $fileName;

                    $counter = 1;
                    while (Storage::disk($disk)->exists($path)) {
                        $counter++;
                        $fileName = $safeName . '-' . $counter . '.' . $extension;
                        $path = $folder . '/' . $fileName;
                    }

                    // Tải file về (Thời gian chờ 60s vì file PDF/DOCX có thể rất nặng)
                    $response = $client->get($cleanFileUrl);
                    if ($response->getStatusCode() === 200) {
                        Storage::disk($disk)->put($path, $response->getBody()->getContents());
                        $localUrl = Storage::disk($disk)->url($path);

                        // Thay thế toàn bộ link cũ bằng link local mới tải về
                        $content = str_replace($fileUrl, $localUrl, $content);
                    }
                } catch (\Exception $e) {
                    Log::warning("Lỗi tải File tài liệu: {$fileUrl}. Chi tiết: " . $e->getMessage());
                    continue;
                }
            }
        }
        return $content;
    }
}
