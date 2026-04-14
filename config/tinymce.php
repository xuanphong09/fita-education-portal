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
    'plugins' => 'autosave accordion advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount emoticons codesample directionality pagebreak nonbreaking save visualchars',
//    'plugins' => 'lists link image table code preview',
// 1. ÉP PHÔNG MẶC ĐỊNH CHO VÙNG SOẠN THẢO (Cỡ chữ 16px cho hiện đại, chuẩn web)
    'content_style' => 'body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"; font-size: 16px; line-height: 1.6; color: #1e293b; overflow-wrap: anywhere; } p, ul, ol, blockquote, pre, table, figure { margin: 0 0 1rem; } h1, h2, h3, h4, h5, h6 { margin: 1.25rem 0 0.75rem; line-height: 1.3; font-weight: 700; } h1 { font-size: 2rem; } h2 { font-size: 1.75rem; } h3 { font-size: 1.5rem; } h4 { font-size: 1.25rem; } ul, ol { padding-left: 1.5rem; } img, video, iframe { max-width: 100%; } img, video { height: auto; } table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #e5e7eb; padding: 0.5rem 0.625rem; } figure.image { display: table; margin-left: auto; margin-right: auto; }',

    // 2. KHAI BÁO VÀO MENU ĐỂ NẾU XÓA ĐỊNH DẠNG CŨNG QUAY VỀ ĐÂY ĐƯỢC
    'font_family_formats' => 'Phông hệ thống=system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"; Arial=arial,helvetica,sans-serif; Times New Roman=times new roman,times; Tahoma=tahoma,arial,helvetica,sans-serif;',
    // 4. THANH CÔNG CỤ (Đã sắp xếp khoa học)
    'toolbar' =>
        'restoredraft undo redo | ' .
        'blocks fontfamily fontsize | ' .
        'bold italic underline forecolor removeformat| ' .
        'align lineheight | ' .
        'numlist bullist indent outdent| ' .
        'link image media table codesample | ' . // Nhóm chèn nội dung
        'ltr rtl | ' . // Nhóm hướng văn bản
        'forecolor backcolor strikethrough| ' . // Nhóm màu sắc (Rất quan trọng)
        'emoticons charmap | ' . // Nhóm icon
        'pagebreak nonbreaking anchor | ' .
        'searchreplace visualblocks code fullscreen preview| ' .
        'help',
    'quickbars_selection_toolbar' => 'bold italic underline | forecolor backcolor |align link| removeformat',
    // 5. Cấu hình phụ trợ (Để các nút hoạt động đúng)
//    'image_title' => true,
//    'automatic_uploads' => true,
//    'file_picker_types' => 'image',
//    'paste_data_images' => true,
//    'images_upload_url' => '/admin/post/editor-upload',

//    'autosave_ask_before_unload' => true, // Hỏi trước khi tắt tab
    'autosave_interval' => '10s',         // Cứ 10 giây lưu 1 lần vào trình duyệt
//    'autosave_restore_when_empty' => true, // Nếu lỡ F5 trang trắng bóc, tự động đổ lại chữ vào
    'autosave_retention' => '1440m',
    ];
