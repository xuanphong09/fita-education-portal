<?php

namespace Database\Seeders;

use App\Models\PostDefaultImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PostDefaultImageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createTemplates();
    }

    private function createTemplates(): void
    {
        // Xóa các template cũ
        PostDefaultImage::query()->delete();

        $templates = [
            [
                'name' => 'Blue Professional',
                'description' => 'Nền xanh dương chuyên nghiệp',
                'bg_color' => '#1e3a8a',
                'text_color' => '#ffffff',
                'text_size' => 52,
                'text_alignment' => 'center',
                'text_y_offset' => 0,
            ],
            [
                'name' => 'Green Nature',
                'description' => 'Nền xanh tự nhiên thân thiện',
                'bg_color' => '#16a34a',
                'text_color' => '#ffffff',
                'text_size' => 52,
                'text_alignment' => 'center',
                'text_y_offset' => 0,
            ],
            [
                'name' => 'Orange Modern',
                'description' => 'Nền cam hiện đại năng động',
                'bg_color' => '#ea580c',
                'text_color' => '#ffffff',
                'text_size' => 52,
                'text_alignment' => 'center',
                'text_y_offset' => 0,
            ],
            [
                'name' => 'Purple Elegant',
                'description' => 'Nền tím thanh lịch cao cấp',
                'bg_color' => '#7c3aed',
                'text_color' => '#ffffff',
                'text_size' => 52,
                'text_alignment' => 'center',
                'text_y_offset' => 0,
            ],
            [
                'name' => 'Red Dynamic',
                'description' => 'Nền đỏ động lực mạnh mẽ',
                'bg_color' => '#dc2626',
                'text_color' => '#ffffff',
                'text_size' => 52,
                'text_alignment' => 'center',
                'text_y_offset' => 0,
            ],
            [
                'name' => 'Slate Dark',
                'description' => 'Nền xám tối hiện đại',
                'bg_color' => '#1e293b',
                'text_color' => '#f1f5f9',
                'text_size' => 52,
                'text_alignment' => 'center',
                'text_y_offset' => 0,
            ],
        ];

        $order = 0;
        foreach ($templates as $template) {
            $imagePath = $this->generateTemplateImage(
                $template['bg_color'],
                $template['name']
            );

            PostDefaultImage::create([
                'name' => $template['name'],
                'description' => $template['description'],
                'image_path' => $imagePath,
                'show_title' => true,
                'text_color' => $template['text_color'],
                'text_size' => $template['text_size'],
                'text_alignment' => $template['text_alignment'],
                'text_y_offset' => $template['text_y_offset'],
                'is_active' => true,
                'order' => $order++,
            ]);
        }
    }

    private function generateTemplateImage(string $bgColor, string $templateName): string
    {
        // Tạo thư mục nếu chưa tồn tại
        $directory = 'public/post-templates';
        $fullDirectory = storage_path('app/' . $directory);

        if (!is_dir($fullDirectory)) {
            mkdir($fullDirectory, 0755, true);
        }

        // Tạo ảnh 1200x630 (tỉ lệ OG Image chuẩn) - sử dụng GD trực tiếp
        $width = 1200;
        $height = 630;

        // Convert hex color to RGB
        $bgColorRgb = $this->hexToRgb($bgColor);

        // Tạo ảnh mới
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $bgColorRgb['r'], $bgColorRgb['g'], $bgColorRgb['b']);
        imagefill($image, 0, 0, $color);

        // Lưu file
        $filename = \Illuminate\Support\Str::slug($templateName) . '.jpg';
        $fullPath = storage_path('app/' . $directory . '/' . $filename);

        imagejpeg($image, $fullPath, 90);
        imagedestroy($image);

        return $directory . '/' . $filename;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
}
