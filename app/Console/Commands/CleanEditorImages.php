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
        $this->info('Bắt đầu quét ảnh rác...');
        $deletedCount = 0;

        // 1. Lấy tất cả các file trong thư mục editor
        $files = Storage::disk('public')->files('uploads/posts/editor');

        foreach ($files as $file) {
            // Lấy URL của file (VD: /storage/uploads/posts/editor/anh1.jpg)
            $url = Storage::url($file);

            // 2. Tìm xem cái URL này có nằm trong cột content_vi hoặc content_en của bài viết nào không
            $isUsed = Post::where('content->vi', 'LIKE', '%' . $url . '%')
                ->orWhere('content->en', 'LIKE', '%' . $url . '%')
                ->exists();

            // 3. Nếu không có bài viết nào dùng ảnh này -> Nó là rác -> XÓA
            if (!$isUsed) {
                Storage::disk('public')->delete($file);
                $this->line("Đã xóa rác: " . $file);
                $deletedCount++;
            }
        }

        $this->info("Hoàn tất! Đã dọn dẹp {$deletedCount} ảnh rác.");
    }
}
