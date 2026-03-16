<?php

return [
    // 1. Xác nhận bản miễn phí (Bắt buộc)
    'license_key' => 'gpl',
    'language' => 'vi',
    // 2. Chiều cao & Giao diện
    'height' => 500,
    'menubar' => false,
    'branding' => false, // Tắt chữ "Powered by TinyMCE" ở góc dưới
    'promotion' => false, // Tắt nút "Upgrade"
    'font_size_formats' => '8pt 9pt 10pt 11pt 12pt 13pt 14pt 16pt 18pt 20pt 24pt 30pt 36pt 48pt 60pt 72pt 96pt',
    // 3. DANH SÁCH PLUGIN ĐẦY ĐỦ (Đã bổ sung các cái thiếu)
    // Các cái bạn thiếu lúc nãy: emoticons, codesample, directionality, pagebreak, nonbreaking...
    'plugins' => 'accordion advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount emoticons codesample directionality pagebreak nonbreaking save visualchars',
//    'plugins' => 'lists link image table code preview',
    // 4. THANH CÔNG CỤ (Đã sắp xếp khoa học)
    'toolbar' =>
        'undo redo | ' .
        'blocks fontfamily fontsize | ' .
        'preview bold italic underline forecolor| ' .
        'align lineheight | ' .
        'numlist bullist indent outdent| ' .
        'link image media table codesample | ' . // Nhóm chèn nội dung
        'ltr rtl | ' . // Nhóm hướng văn bản
        'forecolor backcolor strikethrough| ' . // Nhóm màu sắc (Rất quan trọng)
        'emoticons charmap | ' . // Nhóm icon
        'pagebreak nonbreaking anchor | ' .
        'searchreplace visualblocks code fullscreen| ' .
        'help',

    // 5. Cấu hình phụ trợ (Để các nút hoạt động đúng)
    'image_title' => true,
    'automatic_uploads' => true,
    'file_picker_types' => 'image',
    'images_upload_url' => '/upload', // Bỏ comment nếu bạn làm chức năng upload ảnh thật
];
