<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Post;

class CleanEditorImages extends Command
{
    // Tên lệnh để bạn gõ trong terminal
    protected $signature = 'clean:editor-images';
    protected $description = 'Dọn dẹp các ảnh rác trong thư mục editor không được dùng trong bài viết nào';

    public function handle()
    {
        // Danh sách các thư mục cần quét rác
        $folders = [
            'uploads/posts/editor',
            'uploads/posts/documents'
        ];

        foreach ($folders as $folder) {
            $this->info("--- Đang quét thư mục: {$folder} ---");

            // Dùng allFiles() để quét sạch cả các thư mục con (đệ quy)
            $files = Storage::disk('public')->allFiles($folder);
            $deletedCount = 0;

            foreach ($files as $file) {
                $url = Storage::url($file);
                $path = $file; // Đường dẫn tương đối trong disk

                // 1. Kiểm tra trong nội dung bài viết (Post)
                $usedInPost = Post::where('content->vi', 'LIKE', '%' . $url . '%')
                    ->orWhere('content->en', 'LIKE', '%' . $url . '%')
                    ->exists();

                // 3. Chỉ xóa nếu KHÔNG bài viết nào dùng VÀ KHÔNG môn học nào dùng
                if (!$usedInPost) {
                    Storage::disk('public')->delete($file);
                    $this->line("   [X] Đã xóa: " . $file);
                    $deletedCount++;
                }
            }

            $this->info("Hoàn tất thư mục {$folder}. Đã dọn dẹp {$deletedCount} file rác.");
        }
    }
}
